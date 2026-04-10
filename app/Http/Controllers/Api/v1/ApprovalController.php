<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalFlow;
use App\Models\Leave;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\OutstationRequest;
use App\Models\AttendanceCorrection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ApprovalController extends Controller
{
    /**
     * Mapping role m_presensi ke level dalam approval_flows
     * Sesuai dengan ApprovalFlow::LEVEL_ROLES
     */
    private const MOBILE_ROLE_TO_LEVEL = [
        'supervisor'      => 1,
        'manager'         => 2,
        'hr'              => 3,
        'general_manager' => 4,
        'direktur'        => 5,
    ];

    /**
     * Mapping type dari setiap model ke label yang ditampilkan di mobile
     */
    private const TYPE_LABELS = [
        Leave::class               => 'Cuti',
        OvertimeRequest::class     => 'Lembur',
        PermissionRequest::class   => 'Izin',
        OutstationRequest::class   => 'Perjalanan Dinas',
        AttendanceCorrection::class => 'Koreksi Absensi',
    ];

    /**
     * Mapping status dari DB ke label yang diinginkan frontend
     */
    private const STATUS_LABELS = [
        'pending'  => 'MENUNGGU',
        'approved' => 'DISETUJUI',
        'rejected' => 'DITOLAK',
    ];

    /**
     * Mendapatkan seluruh daftar title_bawahan secara hierarkis multi-level.
     */
    private function getSubordinateTitles(string $titleAtasan, &$allBawahan = []): array
    {
        $bawahans = \App\Models\MAtasan::where('title_atasan', $titleAtasan)->pluck('title_bawahan')->toArray();
        foreach ($bawahans as $b) {
            if (!in_array($b, $allBawahan)) {
                $allBawahan[] = $b;
                // Recursive call untuk mencari bawahan dari bawahan (Supervisor punya Staff, dll)
                $this->getSubordinateTitles($b, $allBawahan);
            }
        }
        return $allBawahan;
    }

    /**
     * GET /api/approvals
     * Mengambil daftar pengajuan yang menunggu persetujuan dari user (mobile) yang sedang login.
     * User harus memiliki role supervisor, manager, hr, atau direktur.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\MPresensi $mobileUser */
        $mobileUser = $request->user();

        $role = $mobileUser->role ?? 'user';

        // --- FILTERING HIERARKI ---
        $myTitle = $mobileUser->karyawan?->title;
        $bawahanTitles = collect([]);
        if ($myTitle) {
            $allSubT = $this->getSubordinateTitles($myTitle);
            $bawahanTitles = collect($allSubT);
        }

        $globalRoles = ['hr', 'direktur', 'superadmin'];
        if (!in_array($role, $globalRoles) && $bawahanTitles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat daftar persetujuan.',
            ], 403);
        }
        
        $statusParam = $request->input('status', 'pending');
        
        // Sertakan relasi approvable dan nested user dari approvable
        $query = ApprovalFlow::where('level', 1)
            ->with([
                'approvable',
                'approvable.user',
                'approvable.user.karyawan',
            ])
            ->orderByDesc('created_at');

        if ($statusParam !== 'all') {
            $query->where('status', $statusParam);
        }

        $flows = $query->get();
        
        if (!in_array($role, $globalRoles)) {
            // Filter koleksi hasil: hanya yang diajukan oleh bawahan
            $flows = $flows->filter(function ($flow) use ($bawahanTitles) {
                $submitterTitle = $flow->approvable?->user?->karyawan?->title;
                if (!$submitterTitle) return false;
                
                return $bawahanTitles->contains($submitterTitle);
            });
        }

        $approvals = $flows->map(function (ApprovalFlow $flow) {
            $approvable = $flow->approvable;

            if (!$approvable) {
                return null;
            }

            // Cari user/karyawan yang submit
            $submitter      = $approvable->user ?? null;
            $karyawan       = optional($submitter)->karyawan ?? null;

            $employeeName = optional($karyawan)->nama_karyawan
                ?? optional($submitter)->name
                ?? 'Unknown';

            $employeeId = optional($karyawan)->nip
                ?? optional($karyawan)->id
                ?? optional($submitter)->id
                ?? '-';

            $division = optional($karyawan)->dept_id
                ?? optional($submitter)->role
                ?? 'N/A';

            $modelClass = get_class($approvable);
            $typeLabel  = self::TYPE_LABELS[$modelClass] ?? 'Pengajuan';

            // Cari nama spesifik type jika ada (cth: cuti tahunan, lembur proyek)
            // Gunakan flow->created_at sebagai safe fallback jika approvable->created_at gagal/hilang
            $submissionDate = optional($approvable->created_at ?? $flow->created_at)->translatedFormat('d M Y');

            if ($approvable instanceof Leave) {
                $typeLabel = 'Cuti ' . ucfirst($approvable->type ?? '');
                $dateRange = $this->formatDateRange($approvable->start_date, $approvable->end_date);
                $duration  = $approvable->calculateLeaveDays() . ' Hari Kerja';
                $additionalInfo = null;
            } elseif ($approvable instanceof OvertimeRequest) {
                $typeLabel  = 'Lembur' . ($approvable->type ? ' ' . ucfirst($approvable->type) : '');
                $dateRange  = null;
                $duration   = ($approvable->duration_hours ?? 0) . ' Jam';
                $additionalInfo = 'Durasi: ' . $duration;
            } elseif ($approvable instanceof PermissionRequest) {
                $typeLabel  = 'Izin ' . ucfirst($approvable->type ?? '');
                $dateRange  = $this->formatDateRange($approvable->start_date, $approvable->end_date);
                $duration   = null;
                $additionalInfo = $dateRange;
            } elseif ($approvable instanceof OutstationRequest) {
                $typeLabel  = 'Perjalanan Dinas';
                $dateRange  = $this->formatDateRange($approvable->start_date, $approvable->end_date);
                $duration   = null;
                $additionalInfo = optional($approvable->destination)->destination ?? null;
            } elseif ($approvable instanceof \App\Models\ShiftSwapRequest) {
                $typeLabel  = 'Tukar Shift';
                $dateRange  = null;
                $duration   = null;
                $targetName = optional($approvable->target)->name ?? 'Unknown';
                $tglTarget  = optional($approvable->target_date)->translatedFormat('d M Y') ?? '-';
                $additionalInfo = "Tukar shift dengan {$targetName} tgl {$tglTarget}";
            } elseif ($approvable instanceof \App\Models\Loan) {
                $typeLabel  = 'Kasbon / Pinjaman';
                $dateRange  = null;
                $duration   = ($approvable->tenor_months ?? 0) . ' Bulan';
                $nominal    = number_format($approvable->amount ?? 0, 0, ',', '.');
                $additionalInfo = "Nominal: Rp {$nominal}";
            } elseif ($approvable instanceof AttendanceCorrection) {
                $typeLabel  = 'Koreksi Presensi';
                $dateRange  = optional($approvable->date)->translatedFormat('d M Y');
                $duration   = null;
                $typeKor    = strtoupper($approvable->type ?? '');
                $waktuKor   = $approvable->time ?? '';
                $additionalInfo = "Tipe Koreksi: {$typeKor} ({$waktuKor})";
            } else {
                $dateRange  = null;
                $duration   = null;
                $additionalInfo = null;
            }

            // Attachment
            $attachmentName = null;
            if (isset($approvable->attachment_path) && $approvable->attachment_path) {
                $attachmentName = basename($approvable->attachment_path);
            }

            // Composite unique approval id untuk mobile (type-id)
            $approvalId = 'af_' . $flow->id;

            return [
                'id'              => $approvalId,
                'approval_flow_id'=> $flow->id,
                'employee_name'   => $employeeName,
                'employee_id'     => 'ID: ' . $employeeId,
                'division'        => $division,
                'type'            => $typeLabel,
                'status'          => self::STATUS_LABELS[$approvable->status] ?? strtoupper($approvable->status),
                'submission_date' => $submissionDate,
                'date_range'      => $dateRange,
                'duration'        => $duration,
                'additional_info' => $additionalInfo,
                'reason'          => $approvable->reason ?? $approvable->notes ?? null,
                'attachment_name' => $attachmentName,
                'profile_image_url' => null,
            ];
        })->filter()->values();

        $page = $request->input('page', 1);
        $perPage = $request->input('limit', 20);
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $approvals->forPage($page, $perPage)->values(),
            $approvals->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar persetujuan',
            'data'    => $paginated->items(),
            'meta'    => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/approvals/{id}/status
     * Approve atau reject sebuah pengajuan berdasarkan approval_flow_id.
     * Hanya bisa dilakukan oleh user dengan role yang sesuai level.
     */
    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status'         => 'required|in:approved,rejected',
            'rejection_note' => 'nullable|string|max:500',
        ]);

        // Lepas prefix "af_" bila ada
        $flowId = str_starts_with($id, 'af_') ? substr($id, 3) : $id;

        /** @var ApprovalFlow|null $flow */
        $flow = ApprovalFlow::find($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }

        $approvable = $flow->approvable;

        if (!$approvable) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }

        /** @var \App\Models\MPresensi $mobileUser */
        $mobileUser = $request->user();

        // Cek hak akses
        if (!$approvable->canBeApprovedBy($mobileUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui pengajuan ini.',
            ], 403);
        }

        // Cek apakah flow masih pending
        if ($flow->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan ini sudah diproses sebelumnya.',
            ], 422);
        }

        $notes = $request->input('rejection_note', $request->input('notes'));

        if ($request->input('status') === 'approved') {
            $approvable->approve($mobileUser, $notes);

            Log::info('APPROVAL [Mobile Approve]: ' . get_class($approvable) . '#' . $approvable->id . ' disetujui oleh mobile user #' . $mobileUser->id . ' (' . $mobileUser->name . ')');

            return response()->json([
                'success' => true,
                'message' => 'Status persetujuan berhasil diperbarui',
                'data'    => [
                    'id'     => $id,
                    'status' => 'approved',
                ]
            ]);
        } else {
            // rejected
            $reason = $notes ?? 'Ditolak melalui aplikasi mobile.';
            $approvable->reject($mobileUser, $reason);

            Log::info('APPROVAL [Mobile Reject]: ' . get_class($approvable) . '#' . $approvable->id . ' ditolak oleh mobile user #' . $mobileUser->id . ' (' . $mobileUser->name . ')');

            return response()->json([
                'success' => true,
                'message' => 'Status persetujuan berhasil diperbarui',
                'data'    => [
                    'id'     => $id,
                    'status' => 'rejected',
                ]
            ]);
        }
    }

    /**
     * GET /api/approvals/{id}
     * Detail persetujuan
     */
    public function show(Request $request, string $id)
    {
        // Lepas prefix "af_" bila ada
        $flowId = str_starts_with($id, 'af_') ? substr($id, 3) : $id;

        /** @var ApprovalFlow|null $flow */
        $flow = ApprovalFlow::with([
            'approvable',
            'approvable.user',
            'approvable.user.karyawan'
        ])->find($flowId);

        if (!$flow) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }

        $approvable = $flow->approvable;
        if (!$approvable) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }

        /** @var \App\Models\MPresensi $mobileUser */
        $mobileUser = $request->user();
        $role = $mobileUser->role ?? 'user';
        $globalRoles = ['hr', 'direktur', 'superadmin'];
        
        $myTitle = $mobileUser->karyawan?->title;
        $bawahanTitles = collect([]);
        if ($myTitle) {
            $allSubT = [];
            $bawahanTitles = collect($this->getSubordinateTitles($myTitle, $allSubT));
        }

        if (!in_array($role, $globalRoles)) {
            $submitterTitle = $approvable->user?->karyawan?->title;
            if (!$submitterTitle || !$bawahanTitles->contains($submitterTitle)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat pengajuan ini.',
                ], 403);
            }
        }

        // Susun response data
        $submitter = $approvable->user ?? null;
        $karyawan  = optional($submitter)->karyawan ?? null;

        $employeeName = optional($karyawan)->nama_karyawan ?? optional($submitter)->name ?? 'Unknown';
        $employeeId   = optional($karyawan)->nip ?? optional($karyawan)->id ?? optional($submitter)->id ?? '-';
        $division     = optional($karyawan)->dept_id ?? optional($submitter)->role ?? 'N/A';

        $modelClass = get_class($approvable);
        $typeLabel  = self::TYPE_LABELS[$modelClass] ?? 'Pengajuan';

            // Gunakan flow->created_at sebagai safe fallback jika approvable->created_at gagal/hilang
            $submissionDate = optional($approvable->created_at ?? $flow->created_at)->translatedFormat('d M Y');

            if ($approvable instanceof Leave) {
                $typeLabel = 'Cuti ' . ucfirst($approvable->type ?? '');
                $dateRange = $this->formatDateRange($approvable->start_date, $approvable->end_date);
                $duration  = $approvable->calculateLeaveDays() . ' Hari Kerja';
                $additionalInfo = null;
            } elseif ($approvable instanceof OvertimeRequest) {
                $typeLabel  = 'Lembur' . ($approvable->type ? ' ' . ucfirst($approvable->type) : '');
                $dateRange  = null;
                $duration   = ($approvable->duration_hours ?? 0) . ' Jam';
                $additionalInfo = 'Durasi: ' . $duration;
            } elseif ($approvable instanceof PermissionRequest) {
                $typeLabel  = 'Izin ' . ucfirst($approvable->type ?? '');
                $dateRange  = $this->formatDateRange($approvable->start_date, $approvable->end_date);
                $duration   = null;
                $additionalInfo = $dateRange;
            } elseif ($approvable instanceof OutstationRequest) {
                $typeLabel  = 'Perjalanan Dinas';
                $dateRange  = $this->formatDateRange($approvable->start_date, $approvable->end_date);
                $duration   = null;
                $additionalInfo = optional($approvable->destination)->destination ?? null;
            } elseif ($approvable instanceof \App\Models\ShiftSwapRequest) {
                $typeLabel  = 'Tukar Shift';
                $dateRange  = null;
                $duration   = null;
                $targetName = optional($approvable->target)->name ?? 'Unknown';
                $tglTarget  = optional($approvable->target_date)->translatedFormat('d M Y') ?? '-';
                $additionalInfo = "Tukar shift dengan {$targetName} tgl {$tglTarget}";
            } elseif ($approvable instanceof \App\Models\Loan) {
                $typeLabel  = 'Kasbon / Pinjaman';
                $dateRange  = null;
                $duration   = ($approvable->tenor_months ?? 0) . ' Bulan';
                $nominal    = number_format($approvable->amount ?? 0, 0, ',', '.');
                $additionalInfo = "Nominal: Rp {$nominal}";
            } elseif ($approvable instanceof AttendanceCorrection) {
                $typeLabel  = 'Koreksi Presensi';
                $dateRange  = optional($approvable->date)->translatedFormat('d M Y');
                $duration   = null;
                $typeKor    = strtoupper($approvable->type ?? '');
                $waktuKor   = $approvable->time ?? '';
                $additionalInfo = "Tipe Koreksi: {$typeKor} ({$waktuKor})";
            } else {
                $dateRange  = null;
                $duration   = null;
                $additionalInfo = null;
            }

        $attachmentName = null;
        $attachmentUrl  = null;
        if (isset($approvable->attachment_path) && $approvable->attachment_path) {
            $attachmentName = basename($approvable->attachment_path);
            $attachmentUrl  = url('storage/' . $approvable->attachment_path);
        }

        $approvalId = 'af_' . $flow->id;

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $approvalId,
                'employee_name'   => $employeeName,
                'employee_id'     => 'ID: ' . $employeeId,
                'division'        => $division,
                'type'            => $typeLabel,
                'status'          => self::STATUS_LABELS[$approvable->status] ?? strtoupper($approvable->status),
                'submission_date' => $submissionDate,
                'date_range'      => $dateRange,
                'duration'        => $duration,
                'additional_info' => $additionalInfo,
                'reason'          => $approvable->reason ?? $approvable->notes ?? null,
                'attachment_name' => $attachmentName,
                'attachment_url'  => $attachmentUrl,
                'profile_image_url' => null,
            ],
        ]);
    }

    /**
     * Format date range menjadi string yang friendly
     */
    private function formatDateRange($start, $end): ?string
    {
        if (!$start) return null;

        $startStr = \Carbon\Carbon::parse($start)->translatedFormat('d M Y');
        $endStr   = $end ? \Carbon\Carbon::parse($end)->translatedFormat('d M Y') : null;

        if ($endStr && $startStr !== $endStr) {
            return $startStr . ' - ' . $endStr;
        }

        return $startStr;
    }
}
