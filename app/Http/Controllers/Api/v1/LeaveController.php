<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Services\OverlapValidator;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $leaves = Leave::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($leaves);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();

        // ── Cek tumpang tindih pengajuan lintas-modul ──
        $conflict = OverlapValidator::check(
            $user->id,
            $request->start_date,
            $request->end_date,
            excludeModule: 'leave'
        );
        if ($conflict) {
            return response()->json([
                'meta' => ['code' => 422, 'status' => 'error', 'message' => $conflict],
                'data' => null,
            ], 422);
        }

        // --- VALIDASI: Cuti Besar ---
        // Jika request tipe cuti besar, pastikan sisa saldonya masih cukup
        $rawType = trim($request->type);
        if (strtolower($rawType) === 'cuti besar') {
            $daysReq = \Carbon\Carbon::parse($request->start_date)->diffInDays(\Carbon\Carbon::parse($request->end_date)) + 1;
            if (!$user->hasSabbaticalQuota($daysReq)) {
                return response()->json([
                    'meta' => ['code' => 422, 'status' => 'error', 'message' => 'Saldo Cuti Besar Anda tidak mencukupi atau telah kadaluarsa'],
                    'data' => null,
                ], 422);
            }
        }

        $reqYear = \Carbon\Carbon::parse($request->start_date)->year;
        $reqMonth = \Carbon\Carbon::parse($request->start_date)->month;

        // --- VALIDASI: Aturan Tahunan (Maks 1 kali setahun) ---
        $yearlyLimitedTypes = ['Cuti Haji', 'Cuti Pekerja Melahirkan'];
        if (in_array($rawType, $yearlyLimitedTypes, true)) {
            $hasTakenYearly = Leave::where('user_id', $user->id)
                ->where('type', $rawType)
                ->where('status', '!=', 'rejected')
                ->whereYear('start_date', $reqYear)
                ->exists();
                
            if ($hasTakenYearly) {
                return response()->json([
                    'meta' => ['code' => 422, 'status' => 'error', 'message' => "{$rawType} hanya dapat diajukan 1 kali dalam satu tahun."],
                    'data' => null,
                ], 422);
            }
        }

        // --- VALIDASI: Aturan Bulanan (Maks 1 kali sebulan) ---
        $monthlyLimitedTypes = ['Haid atau Datang Bulan Jika Disertai Rasa Sakit'];
        if (in_array($rawType, $monthlyLimitedTypes, true)) {
            $hasTakenMonthly = Leave::where('user_id', $user->id)
                ->where('type', $rawType)
                ->where('status', '!=', 'rejected')
                ->whereYear('start_date', $reqYear)
                ->whereMonth('start_date', $reqMonth)
                ->exists();
                
            if ($hasTakenMonthly) {
                return response()->json([
                    'meta' => ['code' => 422, 'status' => 'error', 'message' => "Cuti Haid hanya dapat diajukan 1 kali dalam satu bulan."],
                    'data' => null,
                ], 422);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leaves', 'public');
        }

        $leave = Leave::create([
            'user_id' => $request->user()->id,
            'type' => $request->type, // Removed validation array access usage which was buggy
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'pending',
            'attachment_path' => $attachmentPath,
        ]);

        return response()->json([
            'message' => 'Data saved successfully',
            'data' => $leave,
        ], 201);
    }
}
