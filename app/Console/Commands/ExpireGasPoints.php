<?php

namespace App\Console\Commands;

use App\Services\GasPointsService;
use Illuminate\Console\Command;

class ExpireGasPoints extends Command
{
    protected $signature = 'gaspoints:expire';

    protected $description = 'Expire GasPoints that have reached their configured expiry date.';

    public function handle(GasPointsService $gasPoints): int
    {
        $expired = $gasPoints->expireAllEligiblePoints();

        $this->info("Expired {$expired} GasPoints.");

        return self::SUCCESS;
    }
}
