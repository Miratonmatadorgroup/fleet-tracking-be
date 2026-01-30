<?php

namespace App\Providers;

use App\Events\Ratings\DriverRated;
use App\Events\Driver\RideAcceptedEvent;
use App\Events\Delivery\DeliveryCompleted;
use App\Events\BookRide\RidePoolDriverRated;
use App\Events\Wallet\DataPurchaseCompleted;
use App\Events\Rewards\RewardCampaignCreated;
use App\Events\Delivery\DeliveryAssignedEvent;
use App\Events\Wallet\WalletPurchaseCompleted;
use App\Listeners\Driver\RideAcceptedListener;
use App\Events\Wallet\AirtimePurchaseCompleted;
use App\Events\Wallet\CableTvPurchaseCompleted;
use App\Listeners\Ratings\NotifyDriverOfRating;
use App\Listeners\Rewards\EvaluateDriverRewards;
use App\Listeners\Delivery\LogDeliveryAssignment;
use App\Events\Wallet\ElectricityPurchaseCompleted;
use App\Listeners\Rewards\NotifyAdminOfNewCampaign;
use App\Listeners\Wallet\SendDataPurchaseNotifications;
use App\Listeners\Wallet\SendAirtimePurchaseNotifications;
use App\Listeners\Wallet\SendCableTvPurchaseNotifications;
use App\Listeners\Ratings\SendRidePoolDriverRatedNotification;
use App\Listeners\Wallet\SendElectricityPurchaseNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        DeliveryAssignedEvent::class => [
            LogDeliveryAssignment::class,
        ],

        DriverRated::class => [
            NotifyDriverOfRating::class,
        ],

        RewardCampaignCreated::class => [
            NotifyAdminOfNewCampaign::class,
        ],

        DeliveryCompleted::class => [
            EvaluateDriverRewards::class,
        ],

        RideAcceptedEvent::class => [
            RideAcceptedListener::class,
        ],

        RidePoolDriverRated::class => [
            SendRidePoolDriverRatedNotification::class,
        ],
       

        AirtimePurchaseCompleted::class => [
            SendAirtimePurchaseNotifications::class,
        ],

        // Cable TV
        CableTvPurchaseCompleted::class => [
            SendCableTvPurchaseNotifications::class,
        ],

        DataPurchaseCompleted::class => [
            SendDataPurchaseNotifications::class,
        ],

        ElectricityPurchaseCompleted::class => [
            SendElectricityPurchaseNotifications::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
