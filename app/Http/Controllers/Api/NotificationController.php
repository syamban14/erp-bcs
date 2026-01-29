<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List notifications
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'List notification retrieved successfully'
            ],
            'data' => $notifications->map(function($notif) {
                return [
                    'id' => $notif->id,
                    'title' => $notif->title,
                    'message' => $notif->message,
                    'type' => $notif->type,
                    'read_at' => $notif->read_at?->toISOString(),
                    'created_at' => $notif->created_at->toISOString(),
                    'data' => $notif->reference_id ? [
                        'reference_id' => $notif->reference_id,
                        'reference_type' => $notif->reference_type,
                    ] : null,
                ];
            })
        ]);
    }
    
    /**
     * Mark as read (single)
     */
    public function markAsRead($id, Request $request)
    {
        $user = $request->user();
        
        // Extract numeric ID if string format like "leave-8"
        if (is_string($id) && preg_match('/(\d+)$/', $id, $matches)) {
            $id = (int) $matches[1];
        }
        
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$notification) {
            return response()->json([
                'meta' => [
                    'code' => 404,
                    'status' => 'error',
                    'message' => 'Notification not found'
                ],
                'data' => false
            ], 404);
        }
        
        $notification->markAsRead();
        
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Notification marked as read'
            ],
            'data' => true
        ]);
    }
    
    /**
     * Mark all as read
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'All notifications marked as read'
            ],
            'data' => true
        ]);
    }
}
