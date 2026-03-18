<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Jobs\SendAnnouncementNotification;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function afterCreate(): void
    {
        // Panggil Laravel Jobs untuk Broadcast Pengumuman via FCM (agar loading Filament tidak lambat)
        SendAnnouncementNotification::dispatch($this->record);
    }
}
