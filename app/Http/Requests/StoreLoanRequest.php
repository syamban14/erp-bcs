<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // User can only create loan for themselves
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:100000|max:50000000',
            'tenor_months' => 'required|in:3,6,9,12',
            'reason' => 'required|in:health,education,disaster,other',
            'reason_detail' => 'nullable|string|max:500',
        ];
    }
    
    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Jumlah pinjaman wajib diisi',
            'amount.min' => 'Jumlah pinjaman minimal Rp 100.000',
            'amount.max' => 'Jumlah pinjaman maksimal Rp 50.000.000',
            'tenor_months.required' => 'Tenor pinjaman wajib dipilih',
            'tenor_months.in' => 'Tenor harus 3, 6, 9, atau 12 bulan',
            'reason.required' => 'Alasan pinjaman wajib diisi',
            'reason.in' => 'Alasan tidak valid',
        ];
    }
    
    /**
     * Configure the validator instance
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $userId = $this->user()->id;
            $amount = $this->input('amount');
            
            // Check eligibility
            $eligibilityService = app(\App\Services\LoanEligibilityService::class);
            $eligibility = $eligibilityService->checkEligibility($userId);
            
            if (!$eligibility['eligible']) {
                $validator->errors()->add('loan', $eligibility['reason']);
                return;
            }
            
            // Validate amount against available limit
            $amountValidation = $eligibilityService->validateAmount($userId, $amount);
            if (!$amountValidation['valid']) {
                $validator->errors()->add('amount', $amountValidation['message']);
            }
        });
    }
}
