<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Services\OverlapValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OvertimeRequestController extends Controller
{
    /**
     * Store overtime request
     */
    public function store(Request $request)
    {
        // Deteksi dini: file melebihi batas upload PHP (sebelum Laravel sempat memvalidasi)
        // Ini penyebab khas 500 error saat upload dari device nyata
        if (empty($_FILES) && $request->isMethod('post') && $request->header('Content-Length') > 0) {
            $maxSize = ini_get('upload_max_filesize');
            \Log::warning('OvertimeRequest: Upload file melebihi batas PHP', [
                'content_length' => $request->header('Content-Length'),
                'upload_max_filesize' => $maxSize,
                'user_id' => optional($request->user())->id,
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => "File terlalu besar. Batas upload server adalah {$maxSize}. Harap kompres file terlebih dahulu.",
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'start_date'  => 'required|date|date_format:Y-m-d',
            'end_date'    => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i',
            'description' => 'required|string|max:1000',
            // Attachment dibuat opsional agar tidak 500 jika file gagal terkirim
            // Validasi ukuran dilonggarkan ke 20MB mengikuti kemampuan kamera HP modern
            'attachment'  => 'nullable|file|mimes:pdf,jpg,jpeg,png,heic,heif|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors()
            ], 422);
        }

        // ── Cek tumpang tindih pengajuan lintas-modul ──
        $conflict = OverlapValidator::check(
            $request->user()->id,
            $request->start_date,
            $request->end_date,
            excludeModule: 'overtime'
        );
        if ($conflict) {
            return response()->json([
                'meta' => ['code' => 422, 'status' => 'error', 'message' => $conflict],
                'data' => null,
            ], 422);
        }

        // Upload file (jika ada)
        $path = null;
        if ($request->hasFile('attachment')) {
            try {
                $file     = $request->file('attachment');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $path     = $file->storeAs('overtime-attachments', $filename, 'public');

                \Log::info('OvertimeRequest: File berhasil diupload', [
                    'user_id'   => $request->user()->id,
                    'filename'  => $filename,
                    'size'      => $file->getSize(),
                    'mime'      => $file->getMimeType(),
                ]);
            } catch (\Exception $e) {
                \Log::error('OvertimeRequest: Gagal upload file', [
                    'user_id' => optional($request->user())->id,
                    'error'   => $e->getMessage(),
                ]);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gagal mengupload file lampiran. Coba kompres file dan kirim ulang.',
                ], 500);
            }
        }

        // Create overtime request
        $overtime = OvertimeRequest::create([
            'user_id' => $request->user()->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'description' => $request->description,
            'attachment_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan lembur berhasil dikirim',
            'data' => [
                'id' => $overtime->id,
                'status' => $overtime->status,
                'created_at' => $overtime->created_at->toIso8601String(),
            ]
        ], 201);
    }
    
    /**
     * Get overtime request history
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $overtimes = OvertimeRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($overtime) {
                return [
                    'id' => $overtime->id,
                    'start_date' => $overtime->start_date->format('Y-m-d'),
                    'end_date' => $overtime->end_date->format('Y-m-d'),
                    'start_time' => $overtime->start_time,
                    'end_time' => $overtime->end_time,
                    'description' => $overtime->description,
                    'total_hours' => $overtime->calculateOvertimeHours(),
                    'status' => $overtime->status,
                    'attachment_url' => $overtime->attachment_url,
                    'rejection_reason' => $overtime->rejection_reason,
                    'created_at' => $overtime->created_at->toIso8601String(),
                ];
            });
        
        return response()->json([
            'status' => 'success',
            'data' => $overtimes
        ]);
    }
}
