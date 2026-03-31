<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * FileController — Proxy Aman untuk Akses File Upload
 *
 * Masalah: File yang diupload karyawan (bukti, lampiran, dll) disimpan di
 * storage/app/public/ dan diakses via symlink public/storage. Jika symlink
 * tidak ada atau nginx tidak mengizinkan, atasan mendapat 403 Forbidden.
 *
 * Solusi: Endpoint ini membaca file langsung dari storage (server-side) dan
 * streaming ke client — tidak bergantung pada symlink atau konfigurasi nginx.
 *
 * URL: GET /api/v1/files/{path?} (dilindungi auth:sanctum)
 * Contoh: GET /api/v1/files/corrections/bukti.jpg
 *         GET /api/v1/files/overtime-attachments/spl_xxx.pdf
 *         GET /api/v1/files/permissions/izin_xxx.jpg
 */
class FileController extends Controller
{
    // Folder yang diizinkan diakses via endpoint ini
    private const ALLOWED_FOLDERS = [
        'corrections',
        'overtime-attachments',
        'permissions',
        'presences',
        'leaves',
        'outstation',
    ];

    public function serve(Request $request, string $path)
    {
        // Cegah path traversal attack (../../etc/passwd dll)
        $path = ltrim(str_replace(['..', '\\'], '', $path), '/');

        // Validasi folder: hanya folder yang diizinkan
        $folder = explode('/', $path)[0] ?? '';
        if (!in_array($folder, self::ALLOWED_FOLDERS)) {
            return response()->json([
                'meta' => ['code' => 403, 'status' => 'error', 'message' => 'Akses ke folder ini tidak diizinkan.'],
                'data' => null,
            ], 403);
        }

        // Cek keberadaan file di disk public (storage/app/public/)
        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'meta' => ['code' => 404, 'status' => 'error', 'message' => 'File tidak ditemukan.'],
                'data' => null,
            ], 404);
        }

        // Baca file dan stream ke client
        $fileContents = Storage::disk('public')->get($path);
        $mimeType     = Storage::disk('public')->mimeType($path);
        $filename     = basename($path);

        return response($fileContents, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', "inline; filename=\"{$filename}\"")
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
