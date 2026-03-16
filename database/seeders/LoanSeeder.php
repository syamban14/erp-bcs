<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\MPresensi;
use App\Services\LoanCalculationService;
use Carbon\Carbon;

class LoanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $calculationService = new LoanCalculationService();
        
        // Get some users for testing
        $users = MPresensi::whereNotNull('karyawan_id')->limit(10)->get();
        
        if ($users->isEmpty()) {
            $this->command->error('No users found with karyawan_id. Please seed users first.');
            return;
        }
        
        $this->command->info('Creating loan dummy data...');
        
        // Loan 1: Pending Approval
        $loan1 = Loan::create([
            'user_id' => $users[0]->id,
            'amount' => 5000000,
            'tenor_months' => 6,
            'interest_rate_percent' => 1.0,
            'interest_amount_per_month' => 50000,
            'admin_fee' => 25000,
            'monthly_installment' => 883333.33,
            'total_repayment' => 5300000,
            'disbursement_amount' => 4975000,
            'remaining_amount' => 5300000,
            'reason' => 'health',
            'reason_detail' => 'Biaya operasi darurat',
            'status' => 'pending_approval',
        ]);
        $this->command->info("✓ Created pending loan: Rp 5,000,000 (6 months)");
        
        // Loan 2: Approved (waiting disbursement)
        $loan2 = Loan::create([
            'user_id' => $users[1]->id,
            'amount' => 10000000,
            'tenor_months' => 12,
            'interest_rate_percent' => 1.0,
            'interest_amount_per_month' => 100000,
            'admin_fee' => 25000,
            'monthly_installment' => 1100000,
            'total_repayment' => 11200000,
            'disbursement_amount' => 9975000,
            'remaining_amount' => 11200000,
            'reason' => 'education',
            'reason_detail' => 'Biaya kuliah anak',
            'status' => 'approved',
            'approved_by' => $users[0]->id,
            'approved_at' => Carbon::now()->subDays(2),
            'start_date' => Carbon::now()->addDays(5),
            'end_date' => Carbon::now()->addDays(5)->addMonths(11),
            'bank_account_number' => '1234567890',
            'bank_name' => 'BCA',
        ]);
        
        // Create installments for approved loan
        $calculationService->createInstallments($loan2);
        $this->command->info("✓ Created approved loan: Rp 10,000,000 (12 months) with installments");
        
        // Loan 3: Active (disbursed, ongoing payments)
        $loan3 = Loan::create([
            'user_id' => $users[2]->id,
            'amount' => 6000000,
            'tenor_months' => 6,
            'interest_rate_percent' => 1.0,
            'interest_amount_per_month' => 60000,
            'admin_fee' => 25000,
            'monthly_installment' => 1060000,
            'total_repayment' => 6360000,
            'disbursement_amount' => 5975000,
            'remaining_amount' => 4240000, // 4 installments remaining
            'reason' => 'health',
            'reason_detail' => 'Biaya rawat inap',
            'status' => 'active',
            'approved_by' => $users[0]->id,
            'approved_at' => Carbon::now()->subMonths(3),
            'start_date' => Carbon::now()->subMonths(2),
            'end_date' => Carbon::now()->addMonths(4),
            'bank_account_number' => '9876543210',
            'bank_name' => 'Mandiri',
            'disbursement_date' => Carbon::now()->subMonths(2)->subDays(5),
        ]);
        
        // Create installments for active loan (2 paid, 4 pending)
        for ($i = 1; $i <= 6; $i++) {
            $dueDate = Carbon::parse($loan3->start_date)->addMonths($i - 1);
            $isPaid = $i <= 2; // First 2 installments are paid
            
            LoanInstallment::create([
                'loan_id' => $loan3->id,
                'installment_number' => $i,
                'amount' => $loan3->monthly_installment,
                'due_date' => $dueDate,
                'paid_date' => $isPaid ? $dueDate : null,
                'status' => $isPaid ? 'paid' : 'pending',
            ]);
        }
        $this->command->info("✓ Created active loan: Rp 6,000,000 (2/6 paid)");
        
        // Loan 4: Rejected
        $loan4 = Loan::create([
            'user_id' => $users[3]->id,
            'amount' => 15000000,
            'tenor_months' => 12,
            'interest_rate_percent' => 1.0,
            'interest_amount_per_month' => 150000,
            'admin_fee' => 25000,
            'monthly_installment' => 1650000,
            'total_repayment' => 16800000,
            'disbursement_amount' => 14975000,
            'remaining_amount' => 16800000,
            'reason' => 'other',
            'reason_detail' => 'Renovasi rumah',
            'status' => 'rejected',
            'approved_by' => $users[0]->id,
            'approved_at' => Carbon::now()->subDays(1),
            'rejection_reason' => 'Jumlah pinjaman melebihi limit maksimal berdasarkan gaji pokok',
        ]);
        $this->command->info("✓ Created rejected loan: Rp 15,000,000");
        
        // Loan 5: Paid Off (completed)
        $loan5 = Loan::create([
            'user_id' => $users[4]->id,
            'amount' => 3000000,
            'tenor_months' => 3,
            'interest_rate_percent' => 1.0,
            'interest_amount_per_month' => 30000,
            'admin_fee' => 25000,
            'monthly_installment' => 1030000,
            'total_repayment' => 3090000,
            'disbursement_amount' => 2975000,
            'remaining_amount' => 0,
            'reason' => 'disaster',
            'reason_detail' => 'Perbaikan rumah pasca banjir',
            'status' => 'paid_off',
            'approved_by' => $users[0]->id,
            'approved_at' => Carbon::now()->subMonths(5),
            'start_date' => Carbon::now()->subMonths(4),
            'end_date' => Carbon::now()->subMonths(1),
            'bank_account_number' => '5555666677',
            'bank_name' => 'BNI',
            'disbursement_date' => Carbon::now()->subMonths(4)->subDays(3),
        ]);
        
        // Create all paid installments
        for ($i = 1; $i <= 3; $i++) {
            $dueDate = Carbon::parse($loan5->start_date)->addMonths($i - 1);
            
            LoanInstallment::create([
                'loan_id' => $loan5->id,
                'installment_number' => $i,
                'amount' => $loan5->monthly_installment,
                'due_date' => $dueDate,
                'paid_date' => $dueDate->copy()->addDays(2),
                'status' => 'paid',
            ]);
        }
        $this->command->info("✓ Created paid off loan: Rp 3,000,000 (3/3 paid)");
        
        // Loan 6: Active with overdue installment
        if ($users->count() > 5) {
            $loan6 = Loan::create([
                'user_id' => $users[5]->id,
                'amount' => 8000000,
                'tenor_months' => 9,
                'interest_rate_percent' => 1.0,
                'interest_amount_per_month' => 80000,
                'admin_fee' => 25000,
                'monthly_installment' => 977777.78,
                'total_repayment' => 8720000,
                'disbursement_amount' => 7975000,
                'remaining_amount' => 6793333.34, // 7 installments remaining (1 overdue)
                'reason' => 'education',
                'reason_detail' => 'Biaya pendidikan S2',
                'status' => 'active',
                'approved_by' => $users[0]->id,
                'approved_at' => Carbon::now()->subMonths(4),
                'start_date' => Carbon::now()->subMonths(3),
                'end_date' => Carbon::now()->addMonths(6),
                'bank_account_number' => '7777888899',
                'bank_name' => 'BRI',
                'disbursement_date' => Carbon::now()->subMonths(3)->subDays(2),
            ]);
            
            // Create installments (1 paid, 1 overdue, 7 pending)
            for ($i = 1; $i <= 9; $i++) {
                $dueDate = Carbon::parse($loan6->start_date)->addMonths($i - 1);
                $status = 'pending';
                $paidDate = null;
                
                if ($i == 1) {
                    $status = 'paid';
                    $paidDate = $dueDate->copy()->addDays(1);
                } elseif ($i == 2) {
                    // Overdue (due date passed but not paid)
                    $status = 'pending';
                }
                
                LoanInstallment::create([
                    'loan_id' => $loan6->id,
                    'installment_number' => $i,
                    'amount' => $loan6->monthly_installment,
                    'due_date' => $dueDate,
                    'paid_date' => $paidDate,
                    'status' => $status,
                ]);
            }
            $this->command->info("✓ Created active loan with overdue: Rp 8,000,000 (1 overdue)");
        }
        
        // Summary
        $this->command->info('');
        $this->command->info('=== Loan Seeder Summary ===');
        $this->command->info('Total loans created: ' . Loan::count());
        $this->command->info('Total installments created: ' . LoanInstallment::count());
        $this->command->info('');
        $this->command->info('Status breakdown:');
        $this->command->info('- Pending approval: ' . Loan::where('status', 'pending_approval')->count());
        $this->command->info('- Approved: ' . Loan::where('status', 'approved')->count());
        $this->command->info('- Active: ' . Loan::where('status', 'active')->count());
        $this->command->info('- Rejected: ' . Loan::where('status', 'rejected')->count());
        $this->command->info('- Paid off: ' . Loan::where('status', 'paid_off')->count());
    }
}
