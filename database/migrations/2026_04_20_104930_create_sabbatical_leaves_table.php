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
        // Menyimpan kuota cuti besar setiap kelipatan 5 tahun
        Schema::connection('pgsql')->create('sabbatical_leaves', function (Blueprint $table) {
            $table->id();
            // user_id adalah ID dari tabel m_presensi (database pgsql_master)
            // Tidak menggunakan foreign constraint karena lintas koneksi/database
            $table->unsignedBigInteger('user_id');
            $table->index('user_id');
            $table->integer('quota')->default(10);
            $table->integer('used')->default(0);
            $table->date('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('sabbatical_leaves');
    }
};
