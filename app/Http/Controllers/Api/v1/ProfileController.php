<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\MKaryawan;
use App\Models\MPresensi;

class ProfileController extends Controller
{
    /**
     * Get Employee Information
     */
    public function info(Request $request)
    {
        $user = $request->user(); // This is instance of MPresensi
        
        // Eager load relationships
        $user->load(['karyawan', 'officeLocation']);
        
        $karyawan = $user->karyawan;
        
        // Fallback: Try to find Karyawan by email if relation is broken/null
        if (!$karyawan && $user->email) {
            $karyawan = MKaryawan::where('email', $user->email)->first();
        }

        // Determine profile image URL
        $profileImageUrl = $user->photo 
            ? url('storage/' . $user->photo) 
            : 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random';

        return response()->json([
            'message' => 'Success',
            'data' => [
                'department' => $karyawan?->dept_name ?? 'Divisi Belum Diatur',
                'employment_status' => $user->employment_type ?? 'Tetap',
                'join_date' => $karyawan?->tanggal_masuk ?? 'Informasi Belum Tersedia',
                'work_location' => $user->officeLocation?->name ?? 'Lokasi Belum Diatur',
                'position' => $karyawan?->posisi ?? 'Staff',
                'profile_image_url' => $profileImageUrl,
                'name' => $user->name ?? '-',
                'email' => $user->email ?? '-',
            ]
        ]);
    }

    /**
     * Update Profile (phone, address, photo)
     */
    public function update(Request $request)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // 2MB
        ]);

        $user = $request->user();

        // Update phone and address
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        
        if ($request->has('address')) {
            $user->address = $request->address;
        }

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo && \Storage::disk('public')->exists($user->photo)) {
                \Storage::disk('public')->delete($user->photo);
            }

            // Store new photo
            $path = $request->file('photo')->store('avatars', 'public');
            $user->photo = $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'address' => $user->address,
                'profile_image_url' => $user->photo 
                    ? url('storage/' . $user->photo) 
                    : 'https://ui-avatars.com/api/?name=' . urlencode($user->name) . '&background=random',
            ]
        ]);
    }

    /**
     * Change Password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed|different:current_password',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }
}
