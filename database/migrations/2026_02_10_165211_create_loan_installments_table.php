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
        Schema::connection('pgsql')->create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->smallInteger('installment_number'); // 1, 2, 3, ...
            $table->decimal('amount', 15, 2); // Jumlah cicilan
            $table->date('due_date'); // Tanggal jatuh tempo
            $table->date('paid_date')->nullable(); // Tanggal dibayar
            $table->string('status', 50)->default('pending'); // pending, paid, overdue
            
            // Link to salary slip for automatic deduction
            $table->unsignedBigInteger('salary_slip_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['loan_id', 'status'], 'idx_loan_status');
            $table->index('due_date', 'idx_due_date');
            $table->index('status', 'idx_installment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
