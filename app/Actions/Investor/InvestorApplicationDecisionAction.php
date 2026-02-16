<?php

namespace App\Actions\Investor;


use App\Models\Investor;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvestorApplicationDecision;
use App\Enums\InvestorApplicationStatusEnums;
use App\DTOs\Investor\AdminApproveOrRejectInvestorDTO;

class InvestorApplicationDecisionAction
{

    public function __construct(
        protected TwilioService $twilio,
        protected TermiiService $termii
    ) {}
    public function execute(AdminApproveOrRejectInvestorDTO $dto): Investor
    {
        $investor = Investor::findOrFail($dto->investorId);

        if (
            ($investor->application_status === InvestorApplicationStatusEnums::APPROVED && $dto->action === 'reject') ||
            ($investor->application_status === InvestorApplicationStatusEnums::REJECTED && $dto->action === 'approve')
        ) {
            throw new \Exception('This application has already been decided and cannot be changed.');
        }

        $action = $dto->action;


        $investor->application_status = $dto->action === 'approve'
            ? InvestorApplicationStatusEnums::APPROVED
            : InvestorApplicationStatusEnums::REJECTED;

        //Assign investor role if approved
        if ($dto->action === 'approve') {
            $user = $investor->user;

            if (! $user->hasRole('investor')) {
                $user->assignRole('investor');
            }
        }

        $investor->save();

        $message = match ($action) {
            'approve' => "Hi {$investor->name}, your LoopFreight investor application has been approved. Welcome aboard!",
            'reject' => "Hi {$investor->name}, unfortunately, your LoopFreight investor application has been rejected. Please contact support for more information.",
        };


        //in-app notification for a user as an investor
        if ($investor->user) {
            $investor->user->notify(
                new \App\Notifications\User\InvestorApplicationDecisionNotification($investor, $dto->action)
            );
        }

        //Email
        if ($investor->email) {
            try {
                // Send notification email
                Mail::to($investor->email)->send(new InvestorApplicationDecision($investor, $dto->action));
            } catch (\Throwable $e) {
                logError('Email send failed', $e);
            }
        }
        //SMS
        if ($investor->phone) {
            try {
                $this->termii->sendSms($investor->phone, $message);
            } catch (\Throwable $e) {
                logError('SMS failed', $e);
            }
        }
        //WhatsApp
        if ($investor->whatsapp_number) {
            try {
                $this->twilio->sendWhatsAppMessage($investor->whatsapp_number, $message);
            } catch (\Throwable $e) {
                logError('WhatsApp failed', $e);
            }
        }


        return $investor;
    }
}
