<?php

namespace App\Providers;

use App\Models\ApiClient;
use App\Services\PricingService;
use App\Services\MockPaymentGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Services\Payments\ShanonoPayService;
use App\Services\Payments\MockPaymentService;
use App\Services\Payments\PaymentServiceInterface;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PricingService::class);

         // Bind MockPaymentGateway
        $this->app->bind(PaymentServiceInterface::class, function ($app) {
        $gateway = config('payments.gateway', 'mock'); // default mock

        return match ($gateway) {
            'shanono' => new ShanonoPayService(),
            'mock'    => new MockPaymentService(),
            default   => new MockPaymentService(),
        };
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::macro('apiClient', function (): ?ApiClient {
            return request()->attributes->get('api_client');
        });

        $this->app->bind(ApiClient::class, function () {
            return request()->attributes->get('api_client');
        });
    }
}
