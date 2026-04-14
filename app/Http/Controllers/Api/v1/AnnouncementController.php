<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Announcement;
use App\Models\MKaryawan;
use App\Models\Greeting;
use App\Models\UserAnnouncementRead;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // 1. Ambil pengumuman global yang aktif & tayang HARI INI atau sebelumnya
        // DAN belum pernah dibaca oleh user saat ini
        $announcements = Announcement::where('is_active', true)
            ->where('date', '<=', now())
            ->whereNotExists(function ($query) use ($userId) {
                $query->select('id')
                      ->from('user_announcement_reads')
                      ->whereRaw('CAST(announcements.id AS VARCHAR) = user_announcement_reads.announcement_id')
                      ->where('user_id', $userId);
            })
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'content' => strip_tags($item->content),
                    'type' => $item->type,
                    'date' => $item->date->format('Y-m-d H:i:s'),
                    'image_url' => $item->image_url ? url('/api/v1/public/files/' . $item->image_url) : null,
                    'target_user_id' => $item->target_user_id,
                    'greetings_count' => Greeting::where('announcement_id', $item->id)->count(),
                ];
            })->toArray();

        // 2. Cek Karyawan yang berulang tahun hari ini
        // Format tgl_lahir di database biasanya d/m/Y misal 01/07/1986 atau Y-m-d.
        // Kita ambil hari dan bulan saat ini
        $todayDay = date('d');
        $todayMonth = date('m');

        // Menggunakan LIKE '%dd/mm%' atau '%-mm-dd' agar kompatibel dengan berbagai format
        $birthdayEmployees = MKaryawan::with('presensiAccount')
            ->where('tgl_lahir', 'LIKE', $todayDay . '/' . $todayMonth . '/%')
            ->orWhere('tgl_lahir', 'LIKE', '%-' . $todayMonth . '-' . $todayDay)
            ->get();

        $birthdays = collect();
        foreach ($birthdayEmployees as $karyawan) {
            $announcementId = 'BDAY-' . $karyawan->id;
            
            // Cek apakah sudah dibaca user
            $isRead = UserAnnouncementRead::where('user_id', $userId)
                        ->where('announcement_id', $announcementId)
                        ->exists();

            if (!$isRead) {
                $targetUserId = $karyawan->presensiAccount ? (string) $karyawan->presensiAccount->id : null;
                $birthdays->push([
                    'id' => $announcementId,
                    'title' => 'Happy Birthday!',
                    'content' => 'Selamat ulang tahun ' . $karyawan->nama_karyawan . '!',
                    'type' => 'birthday',
                    'date' => now()->startOfDay()->format('Y-m-d H:i:s'),
                    'image_url' => $karyawan->foto ? "https://hris.xyz.co.id/presensi/assets/img/karyawan/{$karyawan->foto}" : null,
                    'target_user_id' => $targetUserId,
                    'greetings_count' => Greeting::where('announcement_id', $announcementId)->count(),
                ]);
            }
        }

        $birthdays = $birthdays->toArray();

        // 3. Gabungkan dan urutkan berdasarkan tanggal terbaru
        $merged = array_merge($announcements, $birthdays);

        usort($merged, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return response()->json([
            'message' => 'Announcements retrieved successfully',
            'data' => $merged,
        ]);
    }

    public function greet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'announcement_id' => 'required',
            'target_user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        $senderUserId = $request->user()->id;
        $targetUserId = $request->target_user_id;
        $announcementId = $request->announcement_id;
        $currentYear = date('Y');

        if ($senderUserId == $targetUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat mengirim ucapan selamat kepada diri sendiri.'
            ], 422); // or 400, but spec says to block it
        }

        // Cek SPAM (apakah sudah dikirim di tahun yang sama ke target ini)
        $existingGreeting = Greeting::where('sender_user_id', $senderUserId)
            ->where('target_user_id', $targetUserId)
            ->where('year', $currentYear)
            ->first();

        if ($existingGreeting) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah mengirimkan ucapan selamat sebelumnya.'
            ], 422);
        }

        // Simpan Ucapan
        Greeting::create([
            'sender_user_id' => $senderUserId,
            'target_user_id' => $targetUserId,
            'announcement_id' => $announcementId,
            'year' => $currentYear,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ucapan selamat berhasil dikirimkan.'
        ], 201);
    }

    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'announcement_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->user()->id;
        $announcementId = $request->announcement_id;

        // Graceful Handling (insertOrIgnore atau firstOrCreate)
        $readReceipt = UserAnnouncementRead::firstOrCreate([
            'user_id' => $userId,
            'announcement_id' => $announcementId,
        ], [
            'read_at' => now(),
        ]);

        if ($readReceipt->wasRecentlyCreated) {
            return response()->json([
                'success' => true,
                'message' => 'Pengumuman ditandai sudah dibaca.'
            ], 201);
        }

        // Sudah pernah ditandai
        return response()->json([
            'success' => true,
            'message' => 'Pengumuman sudah ditandai sebelumnya.'
        ], 200);
    }
}
