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
        Schema::connection('pgsql')->create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            
            // Loan Amount Details
            $table->decimal('amount', 15, 2); // Jumlah pinjaman
            $table->smallInteger('tenor_months'); // 3, 6, 9, 12
            $table->decimal('interest_rate_percent', 5, 2)->default(1.0); // 1% flat
            $table->decimal('interest_amount_per_month', 15, 2); // Bunga per bulan
            $table->decimal('admin_fee', 15, 2)->default(25000); // Biaya admin
            $table->decimal('monthly_installment', 15, 2); // Cicilan per bulan
            $table->decimal('total_repayment', 15, 2); // Total yang harus dibayar
            $table->decimal('disbursement_amount', 15, 2); // Jumlah cair (amount - admin_fee)
            $table->decimal('remaining_amount', 15, 2); // Sisa yang belum dibayar
            
            // Loan Reason
            $table->string('reason', 50); // health, education, disaster, other
            $table->text('reason_detail')->nullable();
            
            // Status & Approval
            $table->string('status', 50)->default('pending_approval');
            // pending_approval, approved, active, rejected, paid_off, cancelled
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Loan Period
            $table->date('start_date')->nullable(); // Tanggal mulai cicilan
            $table->date('end_date')->nullable(); // Tanggal akhir cicilan
            
            // Bank Info
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->date('disbursement_date')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index('status', 'idx_status');
            $table->index(['start_date', 'end_date'], 'idx_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
