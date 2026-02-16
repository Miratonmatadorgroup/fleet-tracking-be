<?php
namespace App\Services;

use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvestorWithdrawalMessageService
{
    protected TwilioService $whatsappService;
    protected TermiiService $termii;

    public function __construct(TwilioService $whatsappService, TermiiService $termii)
    {
        $this->whatsappService = $whatsappService;
        $this->termii = $termii;
    }

    public function notifyInvestor($investor)
    {
        $message = "Dear {$investor->full_name}, your LoopFreight withdrawal request has been received and is being processed. You’ll be notified once the refund is completed. Thank you for investing with us.";

        try {
            if (!empty($investor->email)) {
                $this->sendEmail($investor->email, $investor->full_name);
            }

            if (!empty($investor->phone)) {
                $this->termii->sendSms($investor->phone, $message);
            }

            if (!empty($investor->whatsapp_number)) {
                $this->whatsappService->sendWhatsAppMessage($investor->whatsapp_number, $message);
            }

        } catch (\Throwable $e) {
            Log::error('InvestorWithdrawalMessageService: Failed to notify investor', [
                'investor_id' => $investor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmail(string $email, string $name)
    {
        $data = [
            'name' => $name,
            'otp' => 'Withdrawal Notification',
        ];

        Mail::send('emails.investor.investor_withdrawal', $data, function ($message) use ($email) {
            $message->to($email)
                ->subject('Your Investment Withdrawal Request');
        });
    }
}
