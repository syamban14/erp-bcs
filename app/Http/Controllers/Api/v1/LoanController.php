<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\SimulateLoanRequest;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Services\LoanCalculationService;
use App\Services\LoanEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LoanController extends Controller
{
    protected $calculationService;
    protected $eligibilityService;
    
    public function __construct(
        LoanCalculationService $calculationService,
        LoanEligibilityService $eligibilityService
    ) {
        $this->calculationService = $calculationService;
        $this->eligibilityService = $eligibilityService;
    }
    
    /**
     * GET /api/loans/summary
     * Get loan summary & eligibility
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        // Get eligibility info
        $eligibility = $this->eligibilityService->checkEligibility($userId);
        
        // Get active loan
        $activeLoan = Loan::forUser($userId)->active()->first();
        
        $data = [
            'max_limit' => $eligibility['max_limit'],
            'available_limit' => $eligibility['available_limit'],
            'active_loan' => null,
            'can_request_new' => $eligibility['eligible'],
            'rejection_reason' => $eligibility['reason'],
        ];
        
        if ($activeLoan) {
            $nextInstallment = $activeLoan->next_installment;
            
            $data['active_loan'] = [
                'id' => $activeLoan->id,
                'amount' => $activeLoan->amount,
                'remaining_amount' => $activeLoan->remaining_amount,
                'status' => $activeLoan->status,
                'start_date' => $activeLoan->start_date?->format('Y-m-d'),
                'end_date' => $activeLoan->end_date?->format('Y-m-d'),
                'next_installment' => $nextInstallment ? [
                    'amount' => $nextInstallment->amount,
                    'due_date' => $nextInstallment->due_date->format('Y-m-d'),
                    'installment_number' => $nextInstallment->installment_number,
                    'total_installments' => $activeLoan->tenor_months,
                ] : null,
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
    
    /**
     * POST /api/loans/simulate
     * Simulate loan calculation
     */
    public function simulate(SimulateLoanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $calculation = $this->calculationService->calculateInstallment(
            $validated['amount'],
            $validated['tenor_months']
        );
        
        return response()->json([
            'success' => true,
            'data' => $calculation,
        ]);
    }
    
    /**
     * POST /api/loans
     * Create new loan request
     */
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;
        
        // Calculate loan details
        $calculation = $this->calculationService->calculateInstallment(
            $validated['amount'],
            $validated['tenor_months']
        );
        
        // Create loan record
        $loan = Loan::create([
            'user_id' => $userId,
            'amount' => $calculation['amount'],
            'tenor_months' => $calculation['tenor_months'],
            'interest_rate_percent' => $calculation['interest_rate_percent'],
            'interest_amount_per_month' => $calculation['interest_amount_per_month'],
            'admin_fee' => $calculation['admin_fee'],
            'monthly_installment' => $calculation['monthly_installment'],
            'total_repayment' => $calculation['total_repayment'],
            'disbursement_amount' => $calculation['disbursement_amount'],
            'remaining_amount' => $calculation['total_repayment'],
            'reason' => $validated['reason'],
            'reason_detail' => $validated['reason_detail'] ?? null,
            'status' => 'pending_approval',
        ]);
        
        // TODO: Send notification to HR/Finance for approval
        
        return response()->json([
            'success' => true,
            'message' => 'Pengajuan pinjaman berhasil dikirim',
            'data' => [
                'id' => $loan->id,
                'status' => $loan->status,
                'created_at' => $loan->created_at->toIso8601String(),
            ],
        ], 201);
    }
    
    /**
     * GET /api/loans
     * Get loan history
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = $request->input('limit', 10);
        
        $loans = Loan::forUser($userId)
            ->with(['installments' => function ($query) {
                $query->orderBy('installment_number');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        $data = $loans->map(function ($loan) {
            return [
                'id' => $loan->id,
                'amount' => $loan->amount,
                'tenor_months' => $loan->tenor_months,
                'monthly_installment' => $loan->monthly_installment,
                'total_repayment' => $loan->total_repayment,
                'remaining_amount' => $loan->remaining_amount,
                'disbursement_amount' => $loan->disbursement_amount,
                'reason' => $loan->reason,
                'reason_detail' => $loan->reason_detail,
                'status' => $loan->status,
                'start_date' => $loan->start_date?->format('Y-m-d'),
                'end_date' => $loan->end_date?->format('Y-m-d'),
                'disbursement_date' => $loan->disbursement_date?->format('Y-m-d'),
                'rejection_reason' => $loan->rejection_reason,
                'paid_installments' => $loan->paid_installments_count,
                'remaining_installments' => $loan->remaining_installments_count,
                'created_at' => $loan->created_at->toIso8601String(),
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ],
        ]);
    }
    
    /**
     * GET /api/loans/{id}
     * Get loan detail with installments
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        
        $loan = Loan::forUser($userId)
            ->with(['installments' => function ($query) {
                $query->orderBy('installment_number');
            }])
            ->findOrFail($id);
        
        $installments = $loan->installments->map(function ($installment) {
            return [
                'id' => $installment->id,
                'installment_number' => $installment->installment_number,
                'amount' => $installment->amount,
                'due_date' => $installment->due_date->format('Y-m-d'),
                'paid_date' => $installment->paid_date?->format('Y-m-d'),
                'status' => $installment->status,
                'is_overdue' => $installment->is_overdue,
                'days_overdue' => $installment->days_overdue,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $loan->id,
                'amount' => $loan->amount,
                'tenor_months' => $loan->tenor_months,
                'interest_rate_percent' => $loan->interest_rate_percent,
                'interest_amount_per_month' => $loan->interest_amount_per_month,
                'admin_fee' => $loan->admin_fee,
                'monthly_installment' => $loan->monthly_installment,
                'total_repayment' => $loan->total_repayment,
                'disbursement_amount' => $loan->disbursement_amount,
                'remaining_amount' => $loan->remaining_amount,
                'reason' => $loan->reason,
                'reason_detail' => $loan->reason_detail,
                'status' => $loan->status,
                'start_date' => $loan->start_date?->format('Y-m-d'),
                'end_date' => $loan->end_date?->format('Y-m-d'),
                'disbursement_date' => $loan->disbursement_date?->format('Y-m-d'),
                'bank_account_number' => $loan->bank_account_number,
                'bank_name' => $loan->bank_name,
                'rejection_reason' => $loan->rejection_reason,
                'approved_at' => $loan->approved_at?->toIso8601String(),
                'created_at' => $loan->created_at->toIso8601String(),
                'installments' => $installments,
            ],
        ]);
    }
}
