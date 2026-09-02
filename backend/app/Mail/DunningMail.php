<?php

namespace App\Mail;

use App\Models\DunningLevel;
use App\Models\Sale;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A payment reminder for an overdue invoice, at a given dunning level. */
class DunningMail extends Mailable
{
    public function __construct(
        public Sale $sale,
        public DunningLevel $dunningLevel,
        public float $outstanding,
        public int $daysOverdue,
        public string $portalUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->dunningLevel->name}: invoice {$this->sale->number}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.dunning');
    }
}
