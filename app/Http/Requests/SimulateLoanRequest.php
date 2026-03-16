<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimulateLoanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
        ];
    }
}
