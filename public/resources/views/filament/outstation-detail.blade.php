<!DOCTYPE html>
<html>
<head>
    <style>
        .detail-container {
            padding: 1rem;
        }
        .detail-row {
            display: flex;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }
        .detail-label {
            font-weight: 600;
            width: 150px;
            color: #374151;
        }
        .detail-value {
            flex: 1;
            color: #1f2937;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-info { background-color: #dbeafe; color: #1e40af; }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .attachment-link {
            color: #2563eb;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="detail-container">
        <div class="detail-row">
            <div class="detail-label">Karyawan:</div>
            <div class="detail-value">{{ $record->user->name ?? '-' }}</div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Jenis Tugas:</div>
            <div class="detail-value">
                <span class="badge {{ $record->task_type === 'Perjalanan Dinas' ? 'badge-info' : 'badge-success' }}">
                    {{ $record->task_type }}
                </span>
            </div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Tanggal & Waktu:</div>
            <div class="detail-value">
                {{ $record->start_date->format('d M Y') }} {{ $record->start_time }} 
                s/d 
                {{ $record->end_date->format('d M Y') }} {{ $record->end_time }}
            </div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Lokasi:</div>
            <div class="detail-value">{{ $record->location }}</div>
        </div>
        
        <div class="detail-row">
            <div class="detail-label">Deskripsi:</div>
            <div class="detail-value">{{ $record->description }}</div>
        </div>
        
        @if($record->latitude && $record->longitude)
        <div class="detail-row">
            <div class="detail-label">Koordinat:</div>
            <div class="detail-value">{{ $record->latitude }}, {{ $record->longitude }}</div>
        </div>
        @endif
        
        @if($record->attachment_path)
        <div class="detail-row">
            <div class="detail-label">Lampiran:</div>
            <div class="detail-value">
                <a href="{{ asset('storage/' . $record->attachment_path) }}" target="_blank" class="attachment-link">
                    Lihat File
                </a>
            </div>
        </div>
        @endif
        
        <div class="detail-row">
            <div class="detail-label">Status:</div>
            <div class="detail-value">
                <span class="badge {{ 
                    $record->status === 'pending' ? 'badge-warning' : 
                    ($record->status === 'approved_manager' ? 'badge-info' : 
                    ($record->status === 'approved' ? 'badge-success' : 'badge-danger'))
                }}">
                    {{ 
                        $record->status === 'pending' ? 'Menunggu Manager' : 
                        ($record->status === 'approved_manager' ? 'Menunggu Admin' : 
                        ($record->status === 'approved' ? 'Disetujui' : 'Ditolak'))
                    }}
                </span>
            </div>
        </div>
        
        @if($record->manager_approved_by)
        <div class="detail-row">
            <div class="detail-label">Disetujui Manager:</div>
            <div class="detail-value">
                {{ $record->managerApprover->name ?? '-' }} 
                ({{ $record->manager_approved_at->format('d M Y H:i') }})
            </div>
        </div>
        @endif
        
        @if($record->admin_approved_by)
        <div class="detail-row">
            <div class="detail-label">Disetujui Admin:</div>
            <div class="detail-value">
                {{ $record->adminApprover->name ?? '-' }} 
                ({{ $record->admin_approved_at->format('d M Y H:i') }})
            </div>
        </div>
        @endif
        
        @if($record->rejection_reason)
        <div class="detail-row">
            <div class="detail-label">Alasan Penolakan:</div>
            <div class="detail-value" style="color: #dc2626;">{{ $record->rejection_reason }}</div>
        </div>
        @endif
        
        <div class="detail-row">
            <div class="detail-label">Tanggal Pengajuan:</div>
            <div class="detail-value">{{ $record->created_at->format('d M Y H:i') }}</div>
        </div>
    </div>
</body>
</html>
