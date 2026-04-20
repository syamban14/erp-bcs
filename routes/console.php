<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leave:allocate-anniversary')->daily();

// Safety net: re-scan semua karyawan setiap minggu untuk menangkap
// kasus tgl_masuk dikoreksi atau karyawan baru yang belum dapat kuota
Schedule::command('leave:seed-initial-quota')->weekly();

// Cek kelipatan masa bakti 5 tahun untuk pemberian/pembaruan Cuti Besar
Schedule::command('sabbatical:manage')->dailyAt('00:30');


