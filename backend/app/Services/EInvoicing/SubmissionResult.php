<?php

namespace App\Services\EInvoicing;

/** Outcome of submitting an e-invoice to the platform. */
class SubmissionResult
{
    private function __construct(
        public readonly bool $accepted,
        public readonly ?string $reference,
        public readonly ?string $error,
    ) {}

    public static function accepted(string $reference): self
    {
        return new self(true, $reference, null);
    }

    public static function rejected(string $error, ?string $reference = null): self
    {
        return new self(false, $reference, $error);
    }
}
