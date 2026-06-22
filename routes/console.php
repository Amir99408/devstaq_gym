<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate monthly payment rows for all active members on the 1st of each month at midnight
Schedule::command('payments:generate-monthly')->monthlyOn(1, '00:01');

// Mark pending payments past their due date as overdue — runs daily
Schedule::command('payments:mark-overdue')->dailyAt('00:05');
