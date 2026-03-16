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
        Schema::connection('pgsql')->create('salary_deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salary_slip_id');
            $table->string('type', 50); // LOAN_INSTALLMENT, TAX, BPJS, etc.
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('reference_id')->nullable(); // FK to loans.id, etc.
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('salary_slip_id')
                ->references('id')
                ->on('salary_slips')
                ->onDelete('cascade');
            
            // Indexes
            $table->index(['salary_slip_id', 'type'], 'idx_slip_type');
            $table->index('reference_id', 'idx_reference');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('salary_deductions');
    }
};
