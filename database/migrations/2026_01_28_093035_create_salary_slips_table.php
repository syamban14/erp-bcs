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
        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // ID dari m_presensi
            $table->date('period'); // First day of month (e.g., 2024-09-01)
            $table->integer('work_days')->default(0);
            
            // Employee Info (denormalized for historical accuracy)
            $table->string('employee_nik', 50)->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_position')->nullable();
            $table->string('employee_division')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            
            // Fixed Allowances (Romawi I)
            $table->decimal('basic_salary', 15, 2)->default(0); // Upah Pokok
            $table->decimal('professional_allowance', 15, 2)->default(0); // Tunj. Kontribusi Profesi
            $table->decimal('performance_allowance', 15, 2)->default(0); // Tunj. Prestasi
            $table->decimal('position_allowance', 15, 2)->default(0); // Tunj. Jabatan
            
            // Variable Allowances (Romawi II)
            $table->decimal('meal_allowance', 15, 2)->default(0); // Makan
            $table->decimal('transport_allowance', 15, 2)->default(0); // Transport
            $table->decimal('relocation_allowance', 15, 2)->default(0); // Tunj. Relokasi
            $table->decimal('skill_allowance', 15, 2)->default(0); // Tunj. Skill
            $table->decimal('other_allowance', 15, 2)->default(0); // Tunj. Lain-lain
            $table->decimal('incentive_10th', 15, 2)->default(0); // Incentive tgl 10
            $table->decimal('communication_allowance', 15, 2)->default(0); // Tunj. Komunikasi
            $table->decimal('incentive', 15, 2)->default(0); // Insentif
            $table->decimal('shift_allowance', 15, 2)->default(0); // Shift
            $table->integer('shift_count')->default(0); // Jumlah shift
            $table->decimal('overtime_allowance', 15, 2)->default(0); // Lembur
            $table->decimal('overtime_hours', 5, 2)->default(0); // Jam lembur
            $table->decimal('khk_allowance', 15, 2)->default(0); // Khk
            $table->integer('khk_count')->default(0);
            
            // Deductions (Romawi III)
            $table->decimal('zakat', 15, 2)->default(0); // Zakat, Infak, Sodaqoh
            $table->decimal('tax', 15, 2)->default(0); // Pajak/PPH.21
            $table->decimal('bpjs', 15, 2)->default(0); // BPJS
            $table->decimal('union_fee', 15, 2)->default(0); // Iuran SP-BCS
            $table->decimal('absence_deduction', 15, 2)->default(0); // Alpa/Absen
            $table->integer('absence_days')->default(0);
            $table->decimal('cooperative', 15, 2)->default(0); // Koperasi
            $table->decimal('bpr_installment', 15, 2)->default(0); // Angsuran BPR
            $table->decimal('other_deduction', 15, 2)->default(0); // Lain-lain
            
            // Summary
            $table->decimal('gross_salary', 15, 2)->default(0); // Penerimaan Bruto
            $table->decimal('total_deductions', 15, 2)->default(0); // Total Potongan
            $table->decimal('net_salary', 15, 2)->default(0); // Total Penerimaan
            $table->text('salary_in_words')->nullable(); // Terbilang
            
            // PDF
            $table->string('pdf_path')->nullable(); // Path to generated PDF
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['user_id', 'period']);
            $table->index('user_id');
            $table->index('period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
    }
};
