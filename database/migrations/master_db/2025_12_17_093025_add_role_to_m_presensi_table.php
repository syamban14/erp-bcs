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
            $table->string('role')->default('user'); // user, supervisor
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_master')->table('m_presensi', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
