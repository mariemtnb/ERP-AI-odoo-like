<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Emails a sale (quote or invoice) to the customer, with the PDF attached and
 * a link to the public portal page where they can view it any time.
 */
class SaleDocumentMail extends Mailable
{
    public function __construct(
        public Sale $sale,
        public string $portalUrl,
        public string $pdf,          // raw PDF bytes
        public string $docLabel,     // "Invoice" | "Quote"
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->docLabel} {$this->sale->number}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sale-document');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, "{$this->sale->number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
