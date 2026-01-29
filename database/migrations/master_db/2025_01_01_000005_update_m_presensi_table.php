<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
            if (!Schema::connection('pgsql_master')->hasColumn('m_presensi', 'karyawan_id')) {
                $table->foreignId('karyawan_id')->nullable()->constrained('m_karyawan')->onDelete('cascade');
            }
            if (!Schema::connection('pgsql_master')->hasColumn('m_presensi', 'device_token')) {
                $table->string('device_token')->nullable();
            }
            if (!Schema::connection('pgsql_master')->hasColumn('m_presensi', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::connection('pgsql_master')->hasColumn('m_presensi', 'photo')) {
                $table->string('photo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
            $table->dropColumn(['karyawan_id', 'device_token', 'is_active', 'photo']);
        });
    }
};
