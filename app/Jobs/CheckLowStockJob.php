<?php

namespace App\Jobs;

use App\Services\Admin\StockAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckLowStockJob implements ShouldQueue
{
    use Queueable;

    public function handle(StockAlertService $service): void
    {
        $service->check();
    }
}
