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
        // Default connection (presensi_db)
        if (!Schema::hasTable('presences')) {
            Schema::create('presences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id'); // ID from m_presensi (master_db)
                $table->date('date');
                $table->time('clock_in')->nullable();
                $table->time('clock_out')->nullable();
                $table->string('latitude_in')->nullable();
                $table->string('longitude_in')->nullable();
                $table->string('latitude_out')->nullable();
                $table->string('longitude_out')->nullable();
                $table->string('status')->DEFAULT('present'); // present, late, alpha
                $table->string('face_photo_in')->nullable();
                $table->string('face_photo_out')->nullable();
                $table->timestamps();

                // Index for faster queries
                $table->index(['user_id', 'date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
