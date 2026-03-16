<?php

namespace App\Console\Commands;

use App\Models\SalarySlip;
use App\Services\LoanDeductionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessLoanDeductions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan:process-deductions {period? : The period to process (video YYYY-MM)} {--dry-run : Only show what would be processed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process loan deductions for salary slips in a specific period';

    /**
     * Execute the console command.
     */
    public function handle(LoanDeductionService $deductionService)
    {
        $periodInput = $this->argument('period') ?? now()->format('Y-m');
        
        try {
            $period = Carbon::createFromFormat('Y-m', $periodInput);
        } catch (\Exception $e) {
            $this->error("Invalid period format. Please use YYYY-MM (e.g. 2026-02)");
            return 1;
        }

        $this->info("Processing loan deductions for period: {$period->format('F Y')}");

        // Find salary slips for this period
        $slips = SalarySlip::whereYear('period', $period->year)
            ->whereMonth('period', $period->month)
            ->get();

        if ($slips->isEmpty()) {
            $this->warn("No salary slips found for this period.");
            return 0;
        }

        $this->info("Found {$slips->count()} salary slips.");
        
        $bar = $this->output->createProgressBar($slips->count());
        $processedCount = 0;
        $totalAmount = 0;

        foreach ($slips as $slip) {
            if ($this->option('dry-run')) {
                // Dry run logic
                $deductions = $deductionService->getDeductionsForUser($slip->user_id, $period);
                if (!empty($deductions)) {
                    $this->line("");
                    $this->info("User ID {$slip->user_id}: Found " . count($deductions) . " pending deductions.");
                    foreach ($deductions as $deduction) {
                        $this->line(" - {$deduction['description']}: " . number_format($deduction['amount']));
                    }
                }
            } else {
                // Real processing
                DB::transaction(function () use ($deductionService, $slip, &$processedCount, &$totalAmount) {
                    // Check initial deductions count
                    $initialCount = $slip->deductions()->count();
                    
                    // Process deductions
                    $deductionService->processLoanDeductions($slip);
                    
                    // Refresh to see new deductions
                    $slip->refresh();
                    $newCount = $slip->deductions()->count();
                    
                    if ($newCount > $initialCount) {
                        $processedCount++;
                        // Update net salary logic
                        // In SalarySlip model we added getNetSalaryAfterDeductionsAttribute
                        // But we might want to update the DB column too if it exists
                        $slip->total_deductions = $slip->total_deductions_with_dynamic;
                        $slip->net_salary = $slip->net_salary_after_deductions;
                        $slip->save();
                    }
                });
            }
            $bar->advance();
        }

        $bar->finish();
        $this->line("");
        
        if ($this->option('dry-run')) {
            $this->info("Dry run completed.");
        } else {
            $this->info("Processing completed.");
            $this->info("Processed {$processedCount} slips with new deductions.");
        }

        return 0;
    }
}
