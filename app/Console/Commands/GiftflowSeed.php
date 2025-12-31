<?php

namespace App\Console\Commands;

use App\Services\RedeemService;
use Illuminate\Console\Command;

class GiftflowSeed extends Command
{
    protected $signature = 'giftflow:seed';

    protected $description = 'Seed the default gift codes';

    public function handle(RedeemService $service): int
    {
        $service->seedDefaults();

        $this->info('Gift codes seeded.');

        return self::SUCCESS;
    }
}
