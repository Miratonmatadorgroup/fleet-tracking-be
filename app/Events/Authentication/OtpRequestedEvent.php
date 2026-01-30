<?php

namespace App\Events\Authentication;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OtpRequestedEvent
{
    use Dispatchable, SerializesModels;

    public string $channel;
    public string $identifier;
    public string $otp;
    public ?string $name;


    public function __construct(string $channel, string $identifier, string $otp, ?string $name = null)
    {
        $this->channel = $channel;
        $this->identifier = $identifier;
        $this->otp = $otp;
        $this->name = $name;
    }
}
