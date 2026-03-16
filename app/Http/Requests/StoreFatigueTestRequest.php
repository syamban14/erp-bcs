<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\FatigueTest;

class StoreFatigueTestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // TODO: Re-enable this check after testing
        // User can only submit test for themselves
        // return $this->user() && $this->input('user_id') == $this->user()->id;
        
        // Temporarily allow any user_id for testing
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:pgsql_master.m_presensi,id',
            'test_datetime' => 'required|date',
            'memory_score' => 'required|integer|min:0|max:3',
            'sleep_time' => 'required|date_format:H:i',
            'reaction_avg_ms' => 'required|integer|min:1',
            'reaction_times' => 'required|array|min:1',
            'reaction_times.*' => 'integer|min:1',
            'fatigue_level' => 'required|in:normal,moderate,severe',
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'ID user wajib diisi',
            'user_id.exists' => 'ID user tidak valid',
            'memory_score.min' => 'Skor memory harus antara 0-3',
            'memory_score.max' => 'Skor memory harus antara 0-3',
            'sleep_time.date_format' => 'Format jam tidur harus HH:mm',
            'reaction_avg_ms.min' => 'Waktu reaksi harus lebih dari 0',
            'reaction_times.required' => 'Data waktu reaksi wajib diisi',
            'reaction_times.array' => 'Data waktu reaksi harus berupa array',
            'fatigue_level.in' => 'Level kelelahan harus: normal, moderate, atau severe',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if already tested today and passed
            $latestTest = FatigueTest::getLatestToday($this->input('user_id'));
            
            if ($latestTest && $latestTest->can_work) {
                $validator->errors()->add('test', 'Anda sudah lulus tes hari ini');
            }
            
            // Check rate limit (max 2 tests per day)
            $testCount = FatigueTest::countToday($this->input('user_id'));
            
            if ($testCount >= 2) {
                $validator->errors()->add('test', 'Anda sudah mencapai batas maksimal tes hari ini (2x)');
            }
            
            // Validate reaction_avg_ms matches average of reaction_times
            if ($this->has('reaction_times') && $this->has('reaction_avg_ms')) {
                $times = $this->input('reaction_times');
                $calculatedAvg = count($times) > 0 ? array_sum($times) / count($times) : 0;
                $submittedAvg = $this->input('reaction_avg_ms');
                
                // Allow 5ms tolerance for rounding
                if (abs($calculatedAvg - $submittedAvg) > 5) {
                    $validator->errors()->add('reaction_avg_ms', 'Rata-rata waktu reaksi tidak sesuai dengan data');
                }
            }
        });
    }
}
