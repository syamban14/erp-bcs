<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop foreign key constraint approved_by → users pada tabel attendance_corrections.
     *
     * Constraint ini tidak valid karena admin yang approve menggunakan akun dari
     * tabel m_presensi (pgsql_master), bukan tabel users (pgsql).
     * Foreign key ditegakkan di Application Level, bukan DB Level.
     */
    public function up(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            // Drop FK dengan try-catch agar tidak error jika constraint sudah tidak ada
            try {
                $table->dropForeign(['approved_by']);
            } catch (\Exception $e) {
                // Constraint mungkin sudah tidak ada — lanjutkan
            }
        });

        // Pastikan dengan raw SQL untuk PostgreSQL (nama constraint eksplisit)
        DB::statement('ALTER TABLE attendance_corrections DROP CONSTRAINT IF EXISTS attendance_corrections_approved_by_foreign');
    }

    public function down(): void
    {
        // Tidak perlu di-restore — fk lintas database tidak boleh dipakai
    }
};
