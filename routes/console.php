<?php

use App\Console\Commands\ExpireRiderAcceptance;
use App\Jobs\CheckCertificationExpiryJob;
use App\Jobs\CheckLowStockJob;
use App\Jobs\CheckRiderDelaysJob;
use App\Jobs\CheckStalePendingOrdersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ExpireRiderAcceptance::class)->everyMinute()->withoutOverlapping();
Schedule::job(new CheckLowStockJob)->everyFifteenMinutes();
Schedule::job(new CheckRiderDelaysJob)->everyFiveMinutes();
Schedule::job(new CheckStalePendingOrdersJob)->everyFiveMinutes();
Schedule::job(new CheckCertificationExpiryJob)->monthly();
