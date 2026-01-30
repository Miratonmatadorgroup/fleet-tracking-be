<?php
// app/Services/TwilioService.php
namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TwilioService
{
    public function sendWhatsAppMessage($to, $message)
    {
        $twilioSid = config('services.twilio.sid');
        $twilioToken = config('services.twilio.token');
        $whatsappFrom = config('services.twilio.whatsapp_from');

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json";

        $data = [
            'From' => $whatsappFrom,
            'To' => "whatsapp:$to",
            'Body' => $message,
        ];

        $response = Http::withBasicAuth($twilioSid, $twilioToken)
            ->withoutVerifying()
            ->asForm()
            ->post($url, $data);

        return $response->json();
    }


    public function sendSmsMessage($to, $message)
    {
        $twilioSid = config('services.twilio.sid');
        $twilioToken = config('services.twilio.token');
        $smsFrom = config('services.twilio.from'); 

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json";

        $data = [
            // 'MessagingServiceSid' => config('services.twilio.messaging_service_sid'),
            'From' => $smsFrom,
            'To' => $to,  // Example: +2348012345678
            'Body' => $message,
        ];

        Log::info('Sending SMS via Twilio', [
            'from' => $smsFrom,
            'to' => $to,
            'sid' => $twilioSid,
        ]);


        $response = Http::withBasicAuth($twilioSid, $twilioToken)
            ->withoutVerifying()
            ->asForm()
            ->post($url, $data);

        return $response->json();
    }
}
