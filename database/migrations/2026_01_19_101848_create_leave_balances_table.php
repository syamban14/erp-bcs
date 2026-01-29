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
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('year'); // 2025, 2026, 2027, dst
            $table->integer('quota')->default(12); // Jatah cuti tahunan
            $table->integer('used')->default(0); // Jatah yang sudah terpakai
            $table->timestamps();
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Unique constraint: satu user hanya punya 1 record per tahun
            $table->unique(['user_id', 'year']);
            
            // Indexes untuk performance
            $table->index('user_id');
            $table->index('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
