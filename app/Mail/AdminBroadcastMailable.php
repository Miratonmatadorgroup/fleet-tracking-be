<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminBroadcastMailable extends Mailable
{
    use Queueable, SerializesModels;

    public string $messageText;
    public string $subjectLine;
    public string $recipientName;

    public string $recipientEmail;

    public function __construct(string $messageText, string $recipientName, string $recipientEmail, ?string $subjectLine = null)
    {
        $this->messageText = $messageText;
        $this->recipientName = $recipientName;
        $this->recipientEmail = $recipientEmail;
        $this->subjectLine = $subjectLine ?? 'Admin Broadcast';
    }

    public function build()
    {
        return $this
            ->to($this->recipientEmail)
            ->subject($this->subjectLine)
            ->view('emails.admin-broadcast')
            ->with([
                'messageText' => $this->messageText,
                'name' => $this->recipientName,
                'subjectLine' => $this->subjectLine,
            ]);
    }
}
