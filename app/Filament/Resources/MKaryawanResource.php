<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\SuperAdminOnly;
use App\Filament\Resources\MKaryawanResource\Pages;
use App\Models\MKaryawan;
use App\Models\MPresensi;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MKaryawanResource extends Resource
{   
    use SuperAdminOnly;

    protected static ?string $navigationLabel = 'Employee Management';
    protected static ?string $modelLabel = 'Employee';           // judul singular: New Employee
    protected static ?string $pluralModelLabel = 'Employee Management'; // judul plural di halaman list

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    protected static ?string $model = MKaryawan::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\TextInput::make('payroll_id')
                            ->label('Payroll ID / NIK')
                            ->maxLength(30)
                            ->required(),
                            
                        Forms\Components\TextInput::make('nama_karyawan')
                            ->label('Nama Karyawan')
                            ->maxLength(300)
                            ->required(),
                            
                        Forms\Components\Select::make('jenis_kelamin')
                            ->label('Jenis Kelamin')
                            ->options([
                                'MALE' => 'MALE',
                                'FEMALE' => 'FEMALE',
                                'L' => 'Laki-laki (L)',
                                'P' => 'Perempuan (P)',
                            ])
                            ->searchable(),
                            
                        Forms\Components\TextInput::make('tempat_lahir')
                            ->label('Tempat Lahir')
                            ->maxLength(100),
                            
                        Forms\Components\DatePicker::make('tgl_lahir')
                            ->label('Tanggal Lahir'),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Jabatan & Penempatan')
                    ->schema([
                        Forms\Components\Select::make('title')
                            ->label('Jabatan (Title)')
                            ->options(\App\Models\MTitle::query()->pluck('title', 'title_code'))
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Select::make('dept_id')
                            ->label('Departemen')
                            ->options(\App\Models\MDept::query()->pluck('dept_name', 'dept_code'))
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Select::make('div_id')
                            ->label('Divisi')
                            ->options(\App\Models\MDivision::query()->pluck('div_name', 'div_code'))
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\TextInput::make('dir_id')
                            ->label('Direktorat (Dir ID)'),
                            
                        Forms\Components\Select::make('level')
                            ->label('Level')
                            ->options(\App\Models\MLevel::query()->pluck('level', 'level_code'))
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Select::make('grade')
                            ->label('Grade')
                            ->options(\App\Models\MGrade::query()->pluck('grade', 'grade_code'))
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\TextInput::make('point_of_hire')
                            ->label('Point of Hire'),
                            
                        Forms\Components\Select::make('cost_sales_id')
                            ->label('Cost of Sales')
                            ->options(\App\Models\MCostSales::query()->pluck('cost_sales', 'cost_sales_code'))
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Hirarki / Struktur Organisasi')
                    ->description('PERHATIAN: Mengubah hirarki di sini akan berdampak pada SELURUH karyawan lain yang memiliki jabatan (Title) yang sama dengan karyawan ini!')
                    ->schema([
                        Forms\Components\Select::make('atasan_titles')
                            ->label('Atasan (Berdasarkan Jabatan Karyawan Ini)')
                            ->options(function () {
                                $karyawanGrouped = \App\Models\MKaryawan::select('title', 'nama_karyawan')->get()->groupBy('title');
                                return \App\Models\MTitle::query()->select('title_code', 'title')->get()->unique('title_code')->mapWithKeys(function ($t) use ($karyawanGrouped) {
                                    $emps = $karyawanGrouped->get($t->title_code, collect());
                                    $allNames = $emps->pluck('nama_karyawan')->implode(', ');
                                    $label = $allNames ? "{$t->title} — [{$allNames}]" : $t->title;
                                    return [$t->title_code => $label];
                                })->toArray();
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live() // trigger UI update on change
                            ->disableOptionWhen(fn (string $value, $get): bool => in_array($value, (array) $get('bawahan_titles')))
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                if (!$record || !$record->title) return [];
                                // Gunakan unique() agar jika database m_atasan ada duplikat kotor tidak bikin selected field jadi double
                                return \App\Models\MAtasan::where('title_bawahan', $record->title)->pluck('title_atasan')->unique()->toArray();
                            })
                            ->saveRelationshipsUsing(function ($record, $state) {
                                if (!$record->title) return;
                                \App\Models\MAtasan::where('title_bawahan', $record->title)->delete();
                                if (!empty($state)) {
                                    $maxId = \App\Models\MAtasan::max('id') ?? 1000;
                                    $inserts = [];
                                    $uniqueState = array_unique((array) $state);
                                    foreach ($uniqueState as $atasan) {
                                        $maxId++;
                                        $inserts[] = [
                                            'id' => $maxId,
                                            'title_bawahan' => $record->title,
                                            'title_atasan' => $atasan,
                                        ];
                                    }
                                    \App\Models\MAtasan::insert($inserts);
                                }
                            }),
                            
                        Forms\Components\Select::make('bawahan_titles')
                            ->label('Bawahan (Berdasarkan Jabatan Karyawan Ini)')
                            ->options(function () {
                                $karyawanGrouped = \App\Models\MKaryawan::select('title', 'nama_karyawan')->get()->groupBy('title');
                                return \App\Models\MTitle::query()->select('title_code', 'title')->get()->unique('title_code')->mapWithKeys(function ($t) use ($karyawanGrouped) {
                                    $emps = $karyawanGrouped->get($t->title_code, collect());
                                    $allNames = $emps->pluck('nama_karyawan')->implode(', ');
                                    $label = $allNames ? "{$t->title} — [{$allNames}]" : $t->title;
                                    return [$t->title_code => $label];
                                })->toArray();
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live() // trigger UI update on change
                            ->disableOptionWhen(fn (string $value, $get): bool => in_array($value, (array) $get('atasan_titles')))
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                if (!$record || !$record->title) return [];
                                return \App\Models\MAtasan::where('title_atasan', $record->title)->pluck('title_bawahan')->unique()->toArray();
                            })
                            ->saveRelationshipsUsing(function ($record, $state) {
                                if (!$record->title) return;
                                \App\Models\MAtasan::where('title_atasan', $record->title)->delete();
                                if (!empty($state)) {
                                    $maxId = \App\Models\MAtasan::max('id') ?? 1000;
                                    $inserts = [];
                                    $uniqueState = array_unique((array) $state);
                                    foreach ($uniqueState as $bawahan) {
                                        $maxId++;
                                        $inserts[] = [
                                            'id' => $maxId,
                                            'title_atasan' => $record->title,
                                            'title_bawahan' => $bawahan,
                                        ];
                                    }
                                    \App\Models\MAtasan::insert($inserts);
                                }
                            }),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Status Kepegawaian')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'PERMANENT' => 'PERMANENT (TETAP)',
                                'CONTRACT'  => 'CONTRACT (KONTRAK)',
                                'HARIAN'    => 'HARIAN',
                                'INTERNSHIP'=> 'MAGANG / INTERNSHIP',
                                'RESIGN'    => 'RESIGN',
                                'TETAP'     => 'TETAP',
                                'KONTRAK'   => 'KONTRAK',
                                'PKWT'      => 'PKWT',
                            ])
                            ->searchable(),
                            
                        Forms\Components\DatePicker::make('tgl_masuk')
                            ->label('Tanggal Masuk'),
                            
                        Forms\Components\DatePicker::make('tgl_keluar')
                            ->label('Tanggal Keluar'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payroll_id')
                    ->label('Payroll ID')
                    ->searchable()
                    ->copyable()
                    ->formatStateUsing(fn ($state, MKaryawan $record): string =>
                        !empty($record->getRawOriginal('payroll_id'))
                            ? (string) $record->getRawOriginal('payroll_id')
                            : '-'
                    )
                    ->color('gray')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nama_karyawan')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Jabatan / Title')
                    ->searchable()
                    ->placeholder('-')
                    ->formatStateUsing(function ($state, MKaryawan $record) {
                        if (!$state) return '-';
                        return $record->titleInfo?->title ?? $state;
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cost_sales_id')
                    ->label('Cost Sales')
                    ->searchable()
                    ->placeholder('-')
                    ->formatStateUsing(function ($state, MKaryawan $record) {
                        if (!$state) return '-';
                        $cs = $record->costSalesInfo;
                        if (!$cs) return $state;
                        // Ambil deskripsi dari kolom cost_sales
                        return $cs->cost_sales ?? $state;
                    })
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('posisi')
                    ->label('Posisi')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('has_account')
                    ->label('Mobile User')
                    ->boolean()
                    ->state(function (MKaryawan $record): bool {
                        return (bool) $record->presensiAccount;
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('create_account')
                    ->label('Buat Akun')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->rules(['unique:pgsql_master.m_presensi,email'])
                            ->default(fn (MKaryawan $record) => $record->email),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->default('password123'),
                        Forms\Components\Select::make('office_location_id')
                            ->label('Lokasi Kantor (Geofencing)')
                            ->options(\App\Models\OfficeLocation::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->helperText('Wajib ditautkan ke area absen karyawan tersebut.'),
                    ])
                    ->action(function (MKaryawan $record, array $data) {
                        // Check local
                        if (MPresensi::where('karyawan_id', $record->id)->exists()) {
                            Notification::make()->title('Akun sudah ada')->danger()->send();
                            return;
                        }

                        MPresensi::create([
                            'karyawan_id' => $record->id,
                            'name' => $record->nama_karyawan,
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                            'role' => 'user', 
                            'office_location_id' => $data['office_location_id'],
                            'is_active' => true,
                        ]);

                        Notification::make()->title('Akun berhasil dibuat')->success()->send();
                    })
                    ->visible(fn (MKaryawan $record) => ! $record->presensiAccount),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMKaryawans::route('/'),
        ];
    }
}
