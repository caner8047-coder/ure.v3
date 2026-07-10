<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Her 30 saniyede bir Telegram AI polling
Schedule::command('telegram:ai-poll')->everyThirtySeconds()->withoutOverlapping();

// Her sabah 08:00'de gunluk uretim raporu
Schedule::command('telegram:daily-report')->dailyAt('08:00')->withoutOverlapping();

// Her Pazar gecesi 23:59'da haftalık AI Talep Tahmini hesaplama
Schedule::command('forecast:run')->weeklyOn(0, '23:59')->withoutOverlapping();
