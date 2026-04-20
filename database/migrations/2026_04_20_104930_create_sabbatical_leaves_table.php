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
            $table->foreignId('user_id')->constrained('m_presensi')->onDelete('cascade');
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
