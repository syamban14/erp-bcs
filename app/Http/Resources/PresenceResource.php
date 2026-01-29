<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PresenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'date' => $this->date ? $this->date->format('Y-m-d') : null,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'latitude_in' => $this->latitude_in,
            'longitude_in' => $this->longitude_in,
            'latitude_out' => $this->latitude_out,
            'longitude_out' => $this->longitude_out,
            'status' => $this->status,
            'face_photo_in' => $this->face_photo_in,
            'face_photo_out' => $this->face_photo_out,
            'shift' => $this->shift_code ? [
                'code' => $this->shift_code,
                'name' => $this->shiftCode?->name,
                'time_in' => $this->shiftCode ? substr($this->shiftCode->time_in, 0, 5) : null,
                'time_out' => $this->shiftCode ? substr($this->shiftCode->time_out, 0, 5) : null,
            ] : null,
            'attendance_status' => [
                'late_minutes' => $this->late_minutes ?? 0,
                'overtime_minutes' => $this->overtime_minutes ?? 0,
                'working_hours' => $this->working_hours,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
