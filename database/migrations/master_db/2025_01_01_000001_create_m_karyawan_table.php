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
        if (!Schema::connection('pgsql_master')->hasTable('m_karyawan')) {
            Schema::connection('pgsql_master')->create('m_karyawan', function (Blueprint $table) {
                $table->id();
                $table->string('nik')->unique()->nullable();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('position')->nullable();
                $table->string('department')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_master')->dropIfExists('m_karyawan');
    }
};
