<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\MPresensi;
use App\Services\LoanCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LoanController extends Controller
{
    protected $calculationService;
    
    public function __construct(LoanCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }
    
    /**
     * GET /api/admin/loans
     * List all loan requests with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Loan::with(['user', 'approver']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        
        $perPage = $request->input('limit', 20);
        $loans = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        $data = $loans->map(function ($loan) {
            return [
                'id' => $loan->id,
                'user' => [
                    'id' => $loan->user->id,
                    'name' => $loan->user->name,
                    'email' => $loan->user->email,
                ],
                'amount' => $loan->amount,
                'tenor_months' => $loan->tenor_months,
                'monthly_installment' => $loan->monthly_installment,
                'total_repayment' => $loan->total_repayment,
                'disbursement_amount' => $loan->disbursement_amount,
                'remaining_amount' => $loan->remaining_amount,
                'reason' => $loan->reason,
                'reason_detail' => $loan->reason_detail,
                'status' => $loan->status,
                'approved_by' => $loan->approver ? [
                    'id' => $loan->approver->id,
                    'name' => $loan->approver->name,
                ] : null,
                'approved_at' => $loan->approved_at?->toIso8601String(),
                'rejection_reason' => $loan->rejection_reason,
                'start_date' => $loan->start_date?->format('Y-m-d'),
                'end_date' => $loan->end_date?->format('Y-m-d'),
                'disbursement_date' => $loan->disbursement_date?->format('Y-m-d'),
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
     * PUT /api/admin/loans/{id}/approve
     * Approve loan request
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
        ]);
        
        $loan = Loan::findOrFail($id);
        
        if ($loan->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Pinjaman ini tidak dalam status pending approval',
            ], 400);
        }
        
        $startDate = Carbon::parse($request->start_date);
        $endDate = $startDate->copy()->addMonths($loan->tenor_months - 1);
        
        // Update loan status
        $loan->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'bank_account_number' => $request->bank_account_number,
            'bank_name' => $request->bank_name,
        ]);
        
        // Generate installment schedule
        $this->calculationService->createInstallments($loan);
        
        // TODO: Send notification to employee
        
        return response()->json([
            'success' => true,
            'message' => 'Pinjaman berhasil disetujui',
            'data' => [
                'id' => $loan->id,
                'status' => $loan->status,
                'start_date' => $loan->start_date->format('Y-m-d'),
                'end_date' => $loan->end_date->format('Y-m-d'),
            ],
        ]);
    }
    
    /**
     * PUT /api/admin/loans/{id}/reject
     * Reject loan request
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);
        
        $loan = Loan::findOrFail($id);
        
        if ($loan->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Pinjaman ini tidak dalam status pending approval',
            ], 400);
        }
        
        $loan->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        
        // TODO: Send notification to employee
        
        return response()->json([
            'success' => true,
            'message' => 'Pinjaman berhasil ditolak',
            'data' => [
                'id' => $loan->id,
                'status' => $loan->status,
                'rejection_reason' => $loan->rejection_reason,
            ],
        ]);
    }
    
    /**
     * PUT /api/admin/loans/{id}/disburse
     * Mark loan as disbursed (money transferred)
     */
    public function disburse(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'disbursement_date' => 'required|date',
        ]);
        
        $loan = Loan::findOrFail($id);
        
        if ($loan->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pinjaman yang sudah disetujui yang bisa dicairkan',
            ], 400);
        }
        
        $loan->update([
            'status' => 'active',
            'disbursement_date' => $request->disbursement_date,
        ]);
        
        // TODO: Send notification to employee
        
        return response()->json([
            'success' => true,
            'message' => 'Pinjaman berhasil dicairkan',
            'data' => [
                'id' => $loan->id,
                'status' => $loan->status,
                'disbursement_date' => $loan->disbursement_date->format('Y-m-d'),
            ],
        ]);
    }
    
    /**
     * GET /api/admin/loans/{id}/installments
     * View installment schedule
     */
    public function installments(Request $request, int $id): JsonResponse
    {
        $loan = Loan::with('installments')->findOrFail($id);
        
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
                'salary_slip_id' => $installment->salary_slip_id,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'loan_id' => $loan->id,
                'total_installments' => $loan->tenor_months,
                'paid_count' => $loan->paid_installments_count,
                'remaining_count' => $loan->remaining_installments_count,
                'installments' => $installments,
            ],
        ]);
    }
}
