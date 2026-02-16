<?php

namespace App\Actions\Dispute;

use App\Models\User;
use App\Models\Dispute;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Mail\DisputeSubmittedMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\DTOs\Dispute\ReportDisputeDTO;

use Illuminate\Support\Facades\Notification;
use App\Notifications\Admin\NewDisputeSubmittedNotification;

class ReportDisputeAction
{
    protected TwilioService $twilioService;
    protected TermiiService $termiiService;

    public function __construct(TwilioService $twilioService, TermiiService $termiiService)
    {
        $this->twilioService = $twilioService;
        $this->termiiService = $termiiService;
    }

    public function execute(ReportDisputeDTO $dto): Dispute
    {
        $attachmentPath = null;

        if (request()->hasFile('attachment')) {
            $attachmentPath = request()->file('attachment')->store('disputes', 'public');
        }

        $dispute = Dispute::create([
            'user_id' => $dto->user_id,
            'title' => $dto->title,
            'description' => $dto->description,
            'tracking_number' => $dto->tracking_number,
            'driver_contact' => $dto->driver_contact,
            'status' => 'pending',
            'attachment_path' => $attachmentPath,
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        Notification::send(User::role('admin')->get(), new NewDisputeSubmittedNotification($dispute));

         //In-app Notification for user
        $user->notify(new \App\Notifications\User\DisputeSubmittedNotification($dispute));

        // Notify user via email
        $user = Auth::user();
        Mail::to($user->email)->send(new DisputeSubmittedMail($dispute, $user->name));

        // Send SMS to user using TextNg
        if ($user->phone) {
            $smsMessage = "Hi {$user->name}, your LoopFreight dispute has been received. We'll review it shortly.";
            $this->termiiService->sendSms($user->phone, $smsMessage);
        }

        // Send WhatsApp message to user
        if ($user->phone) {
            $whatsappMessage = "Hello {$user->name}, your LoopFreight dispute titled \"{$dispute->title}\" has been received. We'll get back to you soon.";
            $this->twilioService->sendWhatsAppMessage($user->whatsapp_number, $whatsappMessage);
        }

        return $dispute;
    }
}
