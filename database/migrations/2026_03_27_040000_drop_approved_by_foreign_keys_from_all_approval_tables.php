<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop semua foreign key yang mereferensikan tabel 'users'
     * pada kolom approved_by di approval_flows dan tabel terkait.
     *
     * Root Cause: Admin yang melakukan approve menggunakan akun dari
     * m_presensi (pgsql_master), BUKAN dari tabel users (pgsql).
     * FK tidak bisa lintas database — ditegakkan di Application Level saja.
     */
    public function up(): void
    {
        // 1. Drop FK di approval_flows (penyebab utama error lembur/pengajuan)
        DB::statement('ALTER TABLE approval_flows DROP CONSTRAINT IF EXISTS approval_flows_approved_by_foreign');

        // 2. Drop FK di attendance_corrections (jika belum terdrop)
        DB::statement('ALTER TABLE attendance_corrections DROP CONSTRAINT IF EXISTS attendance_corrections_approved_by_foreign');

        // 3. Drop FK di permission_requests (jika ada)
        DB::statement('ALTER TABLE permission_requests DROP CONSTRAINT IF EXISTS permission_requests_approved_by_foreign');

        // 4. Drop FK di shift_swap_requests (jika ada)
        DB::statement('ALTER TABLE shift_swap_requests DROP CONSTRAINT IF EXISTS shift_swap_requests_approved_by_foreign');
    }

    public function down(): void
    {
        // Tidak perlu di-restore — FK lintas database tidak valid
    }
};
