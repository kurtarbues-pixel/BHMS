<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:generate')->monthlyOn(1, '01:00');
Schedule::command('invoices:mark-overdue')->dailyAt('02:00');
Schedule::command('leases:sync-expired')->dailyAt('00:05');

