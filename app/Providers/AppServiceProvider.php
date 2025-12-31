<?php

namespace App\Providers;

use App\Repositories\GiftCodeRepository;
use App\Repositories\WebhookEventRepository;
use App\Services\RedeemService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GiftCodeRepository::class, function () {
            return new GiftCodeRepository(config('giftflow.codes_file'));
        });

        $this->app->singleton(WebhookEventRepository::class, function () {
            return new WebhookEventRepository(config('giftflow.events_file'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(RedeemService $service): void
    {
        if (!app()->runningUnitTests()) {
            $codesFile = config('giftflow.codes_file');

            if (!file_exists($codesFile) || filesize($codesFile) === 0) {
                $service->seedDefaults();
            }
        }
    }
}
