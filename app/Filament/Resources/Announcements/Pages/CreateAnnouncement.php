<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Jobs\SendAnnouncementNotification;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user()->load('karyawan.division');

        if ($user && $user->karyawan) {
            $data['author_name']     = $user->karyawan->nama_karyawan;
            // Prioritas: divisi → departemen → fallback
            $karyawan = $user->karyawan;
            if ($karyawan->division) {
                $data['author_division'] = $karyawan->division->div_name;
            } elseif ($karyawan->dept_id) {
                $data['author_division'] = \App\Models\MDept::where('dept_code', $karyawan->dept_id)->value('dept_name') ?? 'Manajemen';
            } else {
                $data['author_division'] = 'Manajemen';
            }
        } else {
            $data['author_name']     = $user->name ?? 'Admin / HRD';
            $data['author_division'] = 'Manajemen';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Panggil Laravel Jobs untuk Broadcast Pengumuman via FCM (agar loading Filament tidak lambat)
        SendAnnouncementNotification::dispatch($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
