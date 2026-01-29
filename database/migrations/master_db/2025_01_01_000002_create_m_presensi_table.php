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
        if (!Schema::connection('pgsql_master')->hasTable('m_presensi')) {
            Schema::connection('pgsql_master')->create('m_presensi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('karyawan_id')->constrained('m_karyawan')->onDelete('cascade');
                $table->string('email')->unique(); // For login
                $table->string('password');
                $table->string('photo')->nullable();
                $table->string('device_token')->nullable();
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_master')->dropIfExists('m_presensi');
    }
};
