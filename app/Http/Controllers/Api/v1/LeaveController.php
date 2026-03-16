<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $leaves = Leave::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($leaves);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leaves', 'public');
        }

        $leave = Leave::create([
            'user_id' => $request->user()->id,
            'type' => $request->type, // Removed validation array access usage which was buggy
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'pending',
            'attachment_path' => $attachmentPath,
        ]);

        return response()->json([
            'message' => 'Data saved successfully',
            'data' => $leave,
        ], 201);
    }
}
