<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFatigueTestRequest;
use App\Models\FatigueTest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class FatigueTestController extends Controller
{
    /**
     * Submit fatigue test result
     * POST /api/fatigue-tests
     */
    public function store(StoreFatigueTestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Calculate retry_after if fatigue_level is severe
        $retryAfter = null;
        if ($validated['fatigue_level'] === 'severe') {
            $cooldownHours = config('fatigue.retry_cooldown_hours', 3);
            $retryAfter = Carbon::parse($validated['test_datetime'])->addHours($cooldownHours);
        }
        
        // Check if this is a retry
        $latestTest = FatigueTest::getLatestToday($validated['user_id']);
        $isRetry = $latestTest && $latestTest->fatigue_level === 'severe';
        
        // Create test record
        $test = FatigueTest::create([
            'user_id' => $validated['user_id'],
            'test_datetime' => $validated['test_datetime'],
            'memory_score' => $validated['memory_score'],
            'sleep_time' => $validated['sleep_time'],
            'reaction_avg_ms' => $validated['reaction_avg_ms'],
            'reaction_times' => $validated['reaction_times'],
            'fatigue_level' => $validated['fatigue_level'],
            'is_retry' => $isRetry,
            'retry_after' => $retryAfter,
        ]);
        
        // TODO: Send notification to admin if severe
        if ($test->fatigue_level === 'severe' && config('fatigue.severe_notification_enabled', true)) {
            // Implement notification logic here
            // event(new FatigueSevereDetected($test));
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Hasil tes berhasil disimpan',
            'data' => [
                'id' => $test->id,
                'user_id' => $test->user_id,
                'fatigue_level' => $test->fatigue_level,
                'can_work' => $test->can_work,
                'retry_after' => $test->retry_after?->toIso8601String(),
                'created_at' => $test->created_at->toIso8601String(),
            ],
        ], 201);
    }
    
    /**
     * Get today's fatigue test status for authenticated user
     * GET /api/fatigue-tests/today
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        // Get latest test today
        $latestTest = FatigueTest::getLatestToday($userId);
        
        // Case 1: Belum tes hari ini
        if (!$latestTest) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_tested_today' => false,
                    'can_work' => null,
                    'needs_retry' => false,
                    'can_retry_now' => false,
                ],
            ]);
        }
        
        // Base response
        $response = [
            'has_tested_today' => true,
            'latest_test' => [
                'id' => $latestTest->id,
                'fatigue_level' => $latestTest->fatigue_level,
                'tested_at' => $latestTest->test_datetime->toIso8601String(),
                'memory_score' => $latestTest->memory_score,
                'reaction_avg_ms' => $latestTest->reaction_avg_ms,
            ],
            'can_work' => $latestTest->can_work,
            'needs_retry' => $latestTest->fatigue_level === 'severe',
            'can_retry_now' => false,
        ];
        
        // Case 2: Sudah tes, hasil Normal/Moderate
        if ($latestTest->can_work) {
            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        }
        
        // Case 3 & 4: Sudah tes, hasil Severe
        $response['can_retry_now'] = $latestTest->can_retry_now;
        $response['retry_after'] = $latestTest->retry_after?->toIso8601String();
        
        if (!$latestTest->can_retry_now) {
            $response['retry_countdown_minutes'] = $latestTest->retry_countdown_minutes;
        }
        
        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }
}
