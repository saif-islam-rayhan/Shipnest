<?php

namespace App\Mail;

use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MerchantApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Merchant $merchant,
        public string $action,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->action === 'approved'
            ? 'Your ShipNest shop has been approved!'
            : 'Your ShipNest shop application was not approved';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.merchants.application',
            with: [
                'merchant' => $this->merchant,
                'action' => $this->action,
                'reason' => $this->reason,
            ],
        );
    }
}
