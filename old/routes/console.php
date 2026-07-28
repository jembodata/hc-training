<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sistem akan mengecek setiap hari jam 00:00
// Cari data yang sudah > 30 hari di sampah, lalu HAPUS PERMANEN.
Schedule::command('model:prune')->daily();