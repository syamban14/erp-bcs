<?php

namespace App\Observers;

use App\Models\LeaveBalance;
use App\Models\MKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MKaryawanObserver
{
    /**
     * Dipanggil setiap kali record MKaryawan di-update.
     * Jika tgl_masuk berubah (atau saat pertama kali disimpan),
     * cek apakah karyawan kini sudah >= 1 tahun masa kerja.
     * Jika iya, buat LeaveBalance untuk setiap tahun yang belum ada.
     */
    public function updated(MKaryawan $karyawan): void
    {
        // 1. Sinkronisasi perubahan Nama ke akun Mobile (M_Presensi)
        if ($karyawan->wasChanged('nama_karyawan')) {
            $user = $karyawan->presensiAccount;
            if ($user && $user->name !== $karyawan->nama_karyawan) {
                $user->update(['name' => $karyawan->nama_karyawan]);
                Log::info("MKaryawanObserver: Nama disinkronkan ke M_Presensi ID {$user->id} menjadi {$karyawan->nama_karyawan}");
            }
        }

        // 2. Cek perhitungan Kuota Cuti (Hanya jika tgl_masuk berubah)
        if ($karyawan->wasChanged('tgl_masuk')) {
            $this->syncLeaveQuota($karyawan);
        }
    }

    public function created(MKaryawan $karyawan): void
    {
        // Saat karyawan baru dibuat, langsung sinkronkan jika sudah layak
        $this->syncLeaveQuota($karyawan);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function syncLeaveQuota(MKaryawan $karyawan): void
    {
        $raw = trim((string) ($karyawan->tgl_masuk ?? ''));

        if (empty($raw) || in_array($raw, ['0000-00-00', '00/00/0000'])) {
            return;
        }

        // Parse tanggal masuk (dua format utama: ISO dan DD/MM/YYYY)
        $joinDate = null;
        try {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw)) {
                $joinDate = Carbon::parse($raw);
            } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
                $joinDate = Carbon::createFromFormat('d/m/Y', "{$m[1]}/{$m[2]}/{$m[3]}");
            }
        } catch (\Exception $e) {
            return;
        }

        if (! $joinDate || $joinDate->year < 1970) {
            return;
        }

        $currentYear  = Carbon::now()->year;
        $yearsWorked  = $currentYear - $joinDate->year;

        // Belum genap 1 tahun → tidak perlu kuota
        if ($yearsWorked < 1) {
            return;
        }

        // Pastikan karyawan punya akun presensi (Mobile User)
        $user = $karyawan->presensiAccount;
        if (! $user) {
            return;
        }

        $firstEligibleYear = $joinDate->year + 1;
        $created           = 0;

        for ($year = $firstEligibleYear; $year <= $currentYear; $year++) {
            $exists = LeaveBalance::where('user_id', $user->id)
                ->where('year', $year)
                ->exists();

            if ($exists) {
                continue;
            }

            LeaveBalance::create([
                'user_id' => $user->id,
                'year'    => $year,
                'quota'   => 12,
                'used'    => 0,
            ]);

            $created++;
        }

        if ($created > 0) {
            Log::info("MKaryawanObserver: Dibuat {$created} record kuota cuti untuk {$karyawan->nama_karyawan} (user_id={$user->id}).");
        }
    }
}
