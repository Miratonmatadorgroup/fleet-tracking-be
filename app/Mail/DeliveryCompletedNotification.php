<?php

namespace App\Mail;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryCompletedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $delivery;
    public $recipientType;

    public function __construct(Delivery $delivery, string $recipientType)
    {
        $this->delivery = $delivery;
        $this->recipientType = $recipientType; 
    }

    public function build()
    {
        return $this->subject('Delivery Completed')
                    ->view('emails.delivery_completed_notification')
                    ->with([
                        'delivery' => $this->delivery,
                        'driver' => $this->delivery->driver,
                        'customer' => $this->delivery->customer,
                        'transport' => $this->delivery->transport ?? null,
                        'recipientType' => $this->recipientType,
                    ]);
    }
}
