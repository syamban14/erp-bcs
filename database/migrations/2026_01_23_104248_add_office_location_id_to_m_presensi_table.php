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
        Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
            $table->unsignedBigInteger('office_location_id')->nullable()->after('email');
            
            // Note: Cannot add foreign key constraint across different databases
            // office_locations is in presensi_db, m_presensi is in pgsql_master
            // Foreign key will be enforced at application level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
            $table->dropColumn('office_location_id');
        });
    }
};
