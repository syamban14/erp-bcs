<?php

namespace App\Models\Concerns;

use App\Models\ApprovalFlow;
use App\Models\MPresensi;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait HasApprovalFlow
 *
 * Gunakan trait ini pada model yang memerlukan alur approval 4 tingkatan.
 * Setiap model yang menggunakan trait ini harus memiliki kolom:
 * - status              (string: pending | approved | rejected)
 * - current_approval_level (tinyInt: 1-4)
 *
 * Usage:
 *   use App\Models\Concerns\HasApprovalFlow;
 *   class Leave extends Model {
 *       use HasApprovalFlow;
 *   }
 */
trait HasApprovalFlow
{
    /**
     * Boot the trait and register observers
     */
    public static function bootHasApprovalFlow()
    {
        static::created(function ($model) {
            $model->initApprovalFlow();
        });
    }

    /**
     * Relasi ke semua record alur approval
     */
    public function approvalFlows(): MorphMany
    {
        return $this->morphMany(ApprovalFlow::class, 'approvable')->orderBy('level');
    }

    /**
     * Dapatkan flow untuk level tertentu
     */
    public function getFlowForLevel(int $level): ?ApprovalFlow
    {
        return $this->approvalFlows()->where('level', $level)->first();
    }

    /**
     * Cek apakah user ini dapat approve record ini
     * Hanya bisa approve jika:
     * 1. Record masih pending
     * 2. Bukan record miliknya sendiri
     * 3. (Keamanan visibilitas record ditanggung oleh Endpoint/Resource)
     */
    public function canBeApprovedBy(MPresensi $user): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        if ($this->user && $this->user->id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Cek apakah user ini dapat reject record ini
     */
    public function canBeRejectedBy(MPresensi $user): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        if ($user->role === 'superadmin') {
            return true;
        }

        if ($this->user && $this->user->id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Approve pada level saat ini.
     * Jika ini level terakhir (4), set status = 'approved'.
     * Jika masih ada level berikutnya, naikkan ke level+1.
     */
    public function approve(MPresensi $approver, ?string $notes = null): self
    {
        $currentLevel = $this->current_approval_level ?? 1;

        // Catat approval di tabel approval_flows
        $this->approvalFlows()->updateOrCreate(
            ['level' => $currentLevel],
            [
                'status'      => ApprovalFlow::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'notes'       => $notes,
            ]
        );

        // Setelah di-approve oleh atasan mana pun (single-level), langsung setujui secara penuh
        $this->update([
            'status'                 => 'approved',
            'approved_by'            => $approver->id,
            'approved_at'            => now(),
        ]);

        // Tambahkan pemotongan kuota khusus untuk Cuti Tahunan setelah disetujui penuh
        if ($this instanceof \App\Models\Leave && $this->isLeaveType()) {
            $days = $this->calculateLeaveDays();
            $year = date('Y', strtotime($this->start_date));
            if ($this->user) {
                $this->user->deductLeaveQuota($days, $year);
            }
        }

        // Notifikasi ke Pengaju
        try {
            if ($this->user && !empty($this->user->fcm_token)) {
                $typeLabel = 'Pengajuan';
                if ($this instanceof \App\Models\Leave) $typeLabel = 'Cuti';
                elseif ($this instanceof \App\Models\OvertimeRequest) $typeLabel = 'Lembur';
                elseif ($this instanceof \App\Models\OutstationRequest) $typeLabel = 'Dinas';
                elseif ($this instanceof \App\Models\PermissionRequest) $typeLabel = 'Izin';
                elseif ($this instanceof \App\Models\AttendanceCorrection) $typeLabel = 'Koreksi Absen';
                
                \App\Services\FcmService::sendNotification(
                    $this->user->fcm_token,
                    "{$typeLabel} Disetujui! ✅",
                    "{$typeLabel} Anda telah disetujui oleh {$approver->name}." . ($notes ? " (Catatan: {$notes})" : "")
                );
            }
        } catch (\Exception $e) {}

        return $this->fresh();
    }

    /**
     * Reject pada level saat ini.
     * Langsung set status = 'rejected' dan hentikan alur.
     */
    public function reject(MPresensi $rejector, string $reason): self
    {
        $currentLevel = $this->current_approval_level ?? 1;

        // Catat rejection
        $this->approvalFlows()->updateOrCreate(
            ['level' => $currentLevel],
            [
                'status'      => ApprovalFlow::STATUS_REJECTED,
                'approved_by' => $rejector->id,
                'approved_at' => now(),
                'notes'       => $reason,
            ]
        );

        // Update model
        $this->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        // Notifikasi ke Pengaju
        try {
            if ($this->user && !empty($this->user->fcm_token)) {
                $typeLabel = 'Pengajuan';
                if ($this instanceof \App\Models\Leave) $typeLabel = 'Cuti';
                elseif ($this instanceof \App\Models\OvertimeRequest) $typeLabel = 'Lembur';
                elseif ($this instanceof \App\Models\OutstationRequest) $typeLabel = 'Dinas';
                elseif ($this instanceof \App\Models\PermissionRequest) $typeLabel = 'Izin';
                elseif ($this instanceof \App\Models\AttendanceCorrection) $typeLabel = 'Koreksi Absen';
                
                \App\Services\FcmService::sendNotification(
                    $this->user->fcm_token,
                    "{$typeLabel} Ditolak ❌",
                    "{$typeLabel} Anda ditolak oleh {$rejector->name}. Alasan: {$reason}"
                );
            }
        } catch (\Exception $e) {}

        return $this->fresh();
    }

    /**
     * Apakah sudah disetujui penuh (semua 4 level approve)?
     */
    public function isFullyApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Label level approval saat ini untuk tampilan UI
     */
    public function getApprovalProgressLabelAttribute(): string
    {
        if ($this->status === 'approved') {
            return 'Disetujui';
        }

        if ($this->status === 'rejected') {
            return 'Ditolak';
        }

        return "Menunggu Persetujuan Atasan";
    }

    /**
     * Inisialisasi flow saat record baru dibuat
     * Panggil ini di dalam Observer atau boot() model
     */
    public function initApprovalFlow(): void
    {
        $this->update(['current_approval_level' => 1]);

        // Buat record pending tunggal (merepresentasikan 1 tahap Atasan)
        $this->approvalFlows()->updateOrCreate(
            ['level' => 1],
            ['status' => ApprovalFlow::STATUS_PENDING]
        );

        // Notifikasi ke Atasan
        try {
            if ($this->user && $this->user->karyawan && $this->user->karyawan->title) {
                // Cari title atasan dari MAtasan
                $atasanTitles = \App\Models\MAtasan::where('title_bawahan', $this->user->karyawan->title)
                                ->pluck('title_atasan')->toArray();
                
                // Jika tidak ada atasan spesifik, fallback ke HRD/Admin (sebagai global approver)
                // Sementara biarkan kosong jika hierarki ketat.

                if (!empty($atasanTitles)) {
                    $approversTokens = \App\Models\MPresensi::whereHas('karyawan', function($q) use ($atasanTitles) {
                        $q->whereIn('title', $atasanTitles);
                    })->whereNotNull('fcm_token')->where('fcm_token', '!=', '')->pluck('fcm_token')->toArray();

                    if (!empty($approversTokens)) {
                        $typeLabel = 'Pengajuan Baru';
                        if ($this instanceof \App\Models\Leave) $typeLabel = 'Pengajuan Cuti';
                        elseif ($this instanceof \App\Models\OvertimeRequest) $typeLabel = 'Pengajuan Lembur';
                        elseif ($this instanceof \App\Models\OutstationRequest) $typeLabel = 'Pengajuan Dinas';
                        elseif ($this instanceof \App\Models\PermissionRequest) $typeLabel = 'Pengajuan Izin';
                        elseif ($this instanceof \App\Models\AttendanceCorrection) $typeLabel = 'Koreksi Absen';

                        \App\Services\FcmService::sendNotification(
                            array_values(array_unique($approversTokens)),
                            $typeLabel,
                            "Karyawan {$this->user->name} mengirim {$typeLabel}. Harap periksa di menu Approval."
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Init Approval FCM Failed: " . $e->getMessage());
        }
    }
}
