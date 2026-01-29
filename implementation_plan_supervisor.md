# Implementasi Fitur Supervisor Shift

## Goal Description
Memungkinkan pengguna dengan role `supervisor` (atau 'shift_admin') untuk melakukan input data presensi karyawan lain. Data presensi harus mencantumkan informasi `shift` (1, 2, atau 3).

## User Review Required
- **Database Changes**:
    - Master DB: Penambahan kolom `role` pada `m_presensi`.
    - Local DB: Penambahan kolom `shift` dan `recorded_by` pada `presences`.
    - **Risiko**: Mengubah skema master db yang mungkin digunakan aplikasi lain? (Asumsi aman karena file migrasi master ada di repo ini).

## Proposed Changes

### Database
#### [NEW] Migration: Add Role to MPresensi (Master DB)
- Table: `m_presensi` (connection: `pgsql_master`)
- Column: `role` (string, default: 'user') -> values: `user`, `supervisor`.

#### [NEW] Migration: Add Shift and RecordedBy to Presences (Local DB)
- Table: `presences`
- Column: `shift` (string, nullable) -> values: '1', '2', '3'.
- Column: `recorded_by` (unsignedBigInteger, nullable) -> Menyimpan ID user yang melakukan input (jika bukan diri sendiri).

### Models
#### [MODIFY] [MPresensi.php](file:///e:/xampp8/htdocs/presensi/app/Models/MPresensi.php)
- Add `role` to fillable.

#### [MODIFY] [Presence.php](file:///e:/xampp8/htdocs/presensi/app/Models/Presence.php)
- Add `shift`, `recorded_by` to fillable.

### API
#### [NEW] SupervisorPresenceController
- Endpoint: `POST /api/supervisor/presence`
- Logic:
    1. Check if login user has `role === 'supervisor'`.
    2. Validate input: `user_id` (karyawan target), `shift`, `type` (in/out), `photo`, `lat/long`.
    3. Save presence with `recorded_by = auth()->id()`.

## Verification Plan
### Automated Tests
- Create script `test_api_supervisor.php`.
    1. Seed user supervisor.
    2. Login as supervisor.
    3. Post presence for another user with Shift 1.
    4. Verify DB.
