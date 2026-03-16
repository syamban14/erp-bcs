<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Move PIN from users to m_presensi
        if (Schema::hasColumn('users', 'pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pin');
            });
        }

        if (!Schema::connection('pgsql_master')->hasColumn('m_presensi', 'pin')) {
            Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
                $table->string('pin')->nullable()->after('password');
            });
        }

        // Modifikasi UserDevices foreign key DIBATALKAN karena tabel sudah diresmikan di pgsql_master pada file migrasi pertama
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse
        if (!Schema::hasColumn('users', 'pin')) {
             Schema::table('users', function (Blueprint $table) {
                $table->string('pin')->nullable();
            });
        }
        
        if (Schema::connection('pgsql_master')->hasColumn('m_presensi', 'pin')) {
            Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
                $table->dropColumn('pin');
            });
        }
    }
};
