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
        Schema::create('shift_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // M4, P2, S1, X, Off, CTK10, dll
            $table->string('name', 100); // MALAM 5, PAGI 2, SIANG 1, dll
            $table->text('description')->nullable(); // Keterangan lengkap
            $table->time('time_in')->nullable(); // Jam masuk
            $table->time('time_out')->nullable(); // Jam pulang
            $table->boolean('is_off')->default(false); // Apakah kode ini = libur/cuti
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_codes');
    }
};
