<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            
            // Requester (yang mengajukan)
            $table->unsignedBigInteger('requester_id');
            $table->date('requester_date');
            $table->string('requester_shift_code', 10)->nullable();
            
            // Target (yang diajak tukar)
            $table->unsignedBigInteger('target_id');
            $table->date('target_date');
            $table->string('target_shift_code', 10)->nullable();
            
            // Request details
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            
            // Approval info
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['requester_id', 'status']);
            $table->index(['target_id', 'status']);
            $table->index(['requester_date', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
    }
};
