<?php

namespace App\Events\Investor;

use App\Models\User;
use App\Models\Investor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvestorApplicationMail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use App\Notifications\Admin\NewInvestorApplicationNotification;

class InvestorApplicationSubmitted
{
    use Dispatchable, SerializesModels;

    public Investor $application;

    public function __construct(Investor $application)
    {
        $this->application = $application;

        /** @var \App\Models\User $user */
        $user = Auth::user();

        //In-app Notification for user as an investor
        $user->notify(new \App\Notifications\User\InvestorApplicationReceivedNotification($user->name));

        //Send confirmation to the investor
        Mail::to($application->email)->send(new InvestorApplicationMail($application));

        //Notify all users with 'admin' role
        User::role('admin')->get()->each(function (User $admin) {
            $admin->notify(new NewInvestorApplicationNotification($this->application));
        });
    }
}
