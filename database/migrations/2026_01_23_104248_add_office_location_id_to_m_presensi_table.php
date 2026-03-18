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
        if (Schema::connection('pgsql_master')->hasTable('m_presensi')) {
            Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
                if (!Schema::connection('pgsql_master')->hasColumn('m_presensi', 'office_location_id')) {
                    $table->unsignedBigInteger('office_location_id')->nullable()->after('email');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('pgsql_master')->hasTable('m_presensi') && Schema::connection('pgsql_master')->hasColumn('m_presensi', 'office_location_id')) {
            Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
                $table->dropColumn('office_location_id');
            });
        }
    }
};
