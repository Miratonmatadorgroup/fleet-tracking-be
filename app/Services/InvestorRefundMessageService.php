<?php
namespace App\Services;

use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvestorRefundMessageService
{
    protected TwilioService $whatsappService;
    protected TermiiService $termiiService;

    public function __construct(TwilioService $whatsappService, TermiiService $termiiService)
    {
        $this->whatsappService = $whatsappService;
        $this->termiiService = $termiiService;
    }

    public function notifyInvestor($investor)
    {
        $message = "Dear {$investor->full_name}, your LoopFreight withdrawal refund has been successfully processed. "
            . "The funds should reflect in your account shortly. Thank you for investing with us.";

        try {
            //Send Email
            if (!empty($investor->email)) {
                $this->sendEmail($investor->email, $investor->full_name);
            }

            // Send SMS
            if (!empty($investor->phone)) {
                $this->termiiService->sendSms($investor->phone, $message);
            }

            // Send WhatsApp message
            if (!empty($investor->whatsapp_number)) {
                $this->whatsappService->sendWhatsAppMessage($investor->whatsapp_number, $message);
            }

        } catch (\Throwable $e) {
            Log::error('InvestorRefundMessageService: Failed to notify investor', [
                'investor_id' => $investor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmail(string $email, string $name)
    {
        $data = ['name' => $name];

        Mail::send('emails.investor.investor_refund', $data, function ($message) use ($email) {
            $message->to($email)
                ->subject('Your Investment Refund Has Been Processed');
        });
    }
}
