<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\MPresensi;
use App\Models\MKaryawan;
use App\Models\SalarySlip;

class LoanEligibilityService
{
    /**
     * HARD CAP: Maximum loan limit regardless of salary
     * Set to 3 million as per business requirement
     */
    const HARD_CAP_LIMIT = 3000000; // Rp 3,000,000
    
    /**
     * Check if user is eligible for new loan
     */
    public function checkEligibility(int $userId): array
    {
        // Check if has active loan (strict rule: 1 active loan only)
        // User request says "current_active_installment = sum(active_loans...)" which implies multiple loans might be allowed
        // BUT strict rule "1 active loan" is safer for v1. Let's keep strict rule for now unless requested otherwise.
        // Wait, if DSR is used, usually it allows multiple loans as long as DSR is okay.
        // However, the previous requirement was strict. Let's stick to DSR calculation but check strict "pending" rule.
        
        // Check if has pending approval
        $pendingLoan = Loan::forUser($userId)->pending()->first();
        
        if ($pendingLoan) {
            return [
                'eligible' => false,
                'reason' => 'Anda masih memiliki pengajuan pinjaman yang menunggu persetujuan',
                'max_limit' => 0,
                'available_limit' => 0,
            ];
        }
        
        // Calculate limits based on DSR
        $limits = $this->calculateLimits($userId);
        $maxLimit = $limits['max_limit'];
        $availableLimit = $limits['available_limit'];
        
        if ($maxLimit <= 0) {
            return [
                'eligible' => false,
                'reason' => 'Data gaji tidak ditemukan atau belum diset. Silakan hubungi HRD',
                'max_limit' => 0,
                'available_limit' => 0,
            ];
        }

        if ($availableLimit < 100000) { // Call it 0 if very small
             return [
                'eligible' => false,
                'reason' => 'Limit pinjaman Anda sudah habis (Debt Service Ratio maksimal 30% gaji)',
                'max_limit' => $maxLimit,
                'available_limit' => 0,
            ];
        }
        
        return [
            'eligible' => true,
            'reason' => null,
            'max_limit' => $maxLimit,
            'available_limit' => $availableLimit,
        ];
    }


    /**
     * Calculate limits based on DSR (Debt Service Ratio)
     * Limit = (30% Last Net Salary - Current Installments) * Max Tenor
     * CAPPED at HARD_CAP_LIMIT (3 million)
     */
    public function calculateLimits(int $userId): array
    {
        // PENTING: Gunakan Histori Slip Gaji Terakhir (Net Salary)
        // Sesuai request: "Ambil Histori Slip Gaji Terakhir... Gunakan net_salary sebagai basis"
        
        $lastSlip = SalarySlip::where('user_id', $userId)
            ->latest('period')
            ->first();
            
        if (!$lastSlip) {
            // Karyawan baru / belum ada slip gaji -> Limit 0
            return ['max_limit' => 0, 'available_limit' => 0];
        }

        // Basis perhitungan: Gaji Bersih Terakhir
        // Note: net_salary di DB sudah dikurangi potongan pinjaman sebelumnya (jika ada).
        // Jadi idealnya kita pakai (net_salary + potongan loan bulan itu) untuk dapat "Disposable Income" murni sebelum loan.
        // Tapi request user spesifik: "Gunakan net_salary作为 basis perhitungan limit".
        // Mari kita stick to request, tapi tambahkan logic safety: 
        // Jika net_salary sudah kecil, jangan kasih pinjaman.
        
        $baseSalary = $lastSlip->net_salary;
        
        // 1. Hitung Max Installment (DSR 30%)
        $dsrPercentage = 0.30;
        $maxMonthlyInstallment = $baseSalary * $dsrPercentage;

        // 2. Hitung Current Active Installments
        $activeLoans = Loan::forUser($userId)->active()->get();
        $currentMonthlyInstallment = $activeLoans->sum('monthly_installment');

        // 3. Hitung Available Installment Room
        $availableInstallmentRoom = max(0, $maxMonthlyInstallment - $currentMonthlyInstallment);

        // 4. Konversi ke Limit (Plafond)
        $maxTenor = 12; // Asumsi tenor max 12 bulan
        
        // Max limit total (Theoretical max if no debt)
        $maxLimitTotal = $maxMonthlyInstallment * $maxTenor;
        
        // Available limit saat ini
        $availableLimit = $availableInstallmentRoom * $maxTenor;

        // ⭐ APPLY HARD CAP: Limit maksimal 3 juta
        $maxLimitTotal = min($maxLimitTotal, self::HARD_CAP_LIMIT);
        $availableLimit = min($availableLimit, self::HARD_CAP_LIMIT);

        return [
            'max_limit' => $maxLimitTotal,
            'available_limit' => $availableLimit,
        ];
    }
    
    /**
     * Get maximum loan limit for user
     */
    public function getMaxLimit(int $userId): float
    {
        $limits = $this->calculateLimits($userId);
        return $limits['max_limit'];
    }
    
    /**
     * Get available loan limit for user
     */
    public function getAvailableLimit(int $userId): float
    {
        $limits = $this->calculateLimits($userId);
        return $limits['available_limit'];
    }
    
    /**
     * Validate loan amount against available limit
     */
    public function validateAmount(int $userId, float $amount): array
    {
        $limits = $this->calculateLimits($userId);
        $availableLimit = $limits['available_limit'];
        
        if ($amount > $availableLimit) {
            return [
                'valid' => false,
                'message' => "Jumlah pinjaman melebihi limit yang tersedia (Rp " . number_format($availableLimit, 0, ',', '.') . ") berdasarkan analisa rasio gaji (DSR 30%)",
            ];
        }
        
        return [
            'valid' => true,
            'message' => null,
        ];
    }
}
