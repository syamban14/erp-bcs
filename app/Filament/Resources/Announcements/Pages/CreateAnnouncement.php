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
        $user = auth()->user()->load('karyawan.department');
        
        if ($user && $user->karyawan) {
            $data['author_name'] = $user->karyawan->nama_karyawan;
            $data['author_division'] = $user->karyawan->department ? $user->karyawan->department->div_name : 'Manajemen';
        } else {
            $data['author_name'] = $user->name ?? 'Admin / HRD';
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
