<?php

namespace App\Services\EInvoicing;

use App\Models\CompanyProfile;
use App\Models\Sale;
use DOMDocument;
use DOMElement;

/**
 * Builds a TEIF (Tunisian Electronic Invoice Format) document for a sale.
 *
 * This is a faithful *subset* of the TTN TEIF 1.8.8 structure — the segments
 * that carry the fiscal meaning of a simple sales invoice: the sender/receiver
 * matricules, the document reference and date, the two partners, the invoice
 * lines, and the monetary + tax totals. It is well-formed, deterministic XML
 * that a certifying signer/adapter can extend; it is not itself a certified
 * generator. Segment names (Bgm, Dtm, Moa, Lin, PartnerSection…) mirror TEIF so
 * the shape is recognisable to anyone who has seen the real format.
 *
 * Sale line prices are VAT-inclusive, so amounts are derived per line and then
 * grouped by rate for the tax breakdown.
 */
class TeifDocument
{
    /** TEIF partner function codes: seller / buyer. */
    private const FN_SELLER = 'I-62';
    private const FN_BUYER = 'I-64';

    /** TEIF amount (MOA) type codes. */
    private const MOA_TOTAL_WITH_TAX = 'I-176';
    private const MOA_TOTAL_WITHOUT_TAX = 'I-179';
    private const MOA_TAX = 'I-180';

    public static function forSale(Sale $sale): string
    {
        return (new self)->build($sale);
    }

    private function build(Sale $sale): string
    {
        $sale->loadMissing(['customer', 'lines.product', 'invoice']);
        $company = CompanyProfile::current();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $teif = $dom->createElement('TEIF');
        $teif->setAttribute('version', '1.8.8');
        $teif->setAttribute('controlingAgency', 'TTN');
        $dom->appendChild($teif);

        // --- header: the matricules of both parties ---
        $header = $dom->createElement('InvoiceHeader');
        $header->appendChild($this->el($dom, 'MessageSenderIdentifier', $company->tax_id ?? '', ['type' => 'I-01']));
        $header->appendChild($this->el($dom, 'MessageRecieverIdentifier', $sale->customer?->tax_id ?? '', ['type' => 'I-01']));
        $teif->appendChild($header);

        $body = $dom->createElement('InvoiceBody');
        $teif->appendChild($body);

        // --- document reference and date (Bgm / Dtm) ---
        $number = $sale->invoice?->number ?? $sale->number;
        $bgm = $dom->createElement('Bgm');
        $bgm->appendChild($this->el($dom, 'DocumentIdentifier', $number));
        $bgm->appendChild($this->el($dom, 'DocumentType', 'Facture', ['code' => 'I-11']));
        $body->appendChild($bgm);

        $date = ($sale->invoice?->issued_at ?? $sale->sale_date)?->format('dmY') ?? '';
        $dtm = $dom->createElement('Dtm');
        $dtm->appendChild($this->el($dom, 'DateText', $date, ['format' => 'ddMMyyyy', 'functionCode' => 'I-31']));
        $body->appendChild($dtm);

        // --- partners ---
        $partners = $dom->createElement('PartnerSection');
        $partners->appendChild($this->partner($dom, self::FN_SELLER, $company->legal_name ?? $company->trade_name ?? '', $company->tax_id ?? '', trim(($company->address ?? '').' '.($company->city ?? ''))));
        $partners->appendChild($this->partner($dom, self::FN_BUYER, $sale->customer?->name ?? '', $sale->customer?->tax_id ?? '', $sale->customer?->address ?? ''));
        $body->appendChild($partners);

        // --- lines, and the per-rate tax accumulator ---
        $lin = $dom->createElement('LinSection');
        $byRate = [];   // rate => ['net' => x, 'tax' => y]
        $totalTtc = 0.0;
        $totalTax = 0.0;

        foreach ($sale->lines as $i => $line) {
            $net = $line->netAmount();
            $tax = $line->vatAmount();
            $ttc = $line->subtotal();
            $rate = (float) $line->tax_rate;

            $totalTtc += $ttc;
            $totalTax += $tax;
            $byRate[(string) $rate] ??= ['net' => 0.0, 'tax' => 0.0];
            $byRate[(string) $rate]['net'] += $net;
            $byRate[(string) $rate]['tax'] += $tax;

            $el = $dom->createElement('Lin');
            $el->setAttribute('lineNumber', (string) ($i + 1));
            $el->appendChild($this->el($dom, 'ItemIdentifier', $line->product?->sku ?? ''));
            $el->appendChild($this->el($dom, 'ItemDescription', $line->product?->name ?? ''));
            $el->appendChild($this->el($dom, 'Quantity', $this->num($line->quantity, 3)));
            $el->appendChild($this->el($dom, 'UnitPrice', $this->num($line->unit_price)));
            $el->appendChild($this->moa($dom, self::MOA_TOTAL_WITHOUT_TAX, $net));
            $tx = $dom->createElement('LinTax');
            $tx->appendChild($this->el($dom, 'TaxTypeName', 'TVA', ['code' => 'I-1602']));
            $tx->appendChild($this->el($dom, 'TaxRate', $this->num($rate)));
            $tx->appendChild($this->moa($dom, self::MOA_TAX, $tax));
            $el->appendChild($tx);
            $lin->appendChild($el);
        }
        $body->appendChild($lin);

        // --- invoice monetary totals ---
        $moa = $dom->createElement('InvoiceMoa');
        $moa->appendChild($this->moa($dom, self::MOA_TOTAL_WITHOUT_TAX, round($totalTtc - $totalTax, 2)));
        $moa->appendChild($this->moa($dom, self::MOA_TAX, round($totalTax, 2)));
        $moa->appendChild($this->moa($dom, self::MOA_TOTAL_WITH_TAX, round($totalTtc, 2)));
        $body->appendChild($moa);

        // --- tax breakdown per rate ---
        $tax = $dom->createElement('InvoiceTax');
        ksort($byRate, SORT_NUMERIC);
        foreach ($byRate as $rate => $sums) {
            $grp = $dom->createElement('InvoiceTaxDetails');
            $grp->appendChild($this->el($dom, 'TaxTypeName', 'TVA', ['code' => 'I-1602']));
            $grp->appendChild($this->el($dom, 'TaxRate', $this->num((float) $rate)));
            $grp->appendChild($this->moa($dom, self::MOA_TOTAL_WITHOUT_TAX, round($sums['net'], 2)));
            $grp->appendChild($this->moa($dom, self::MOA_TAX, round($sums['tax'], 2)));
            $tax->appendChild($grp);
        }
        $body->appendChild($tax);

        return $dom->saveXML();
    }

    private function partner(DOMDocument $dom, string $fn, string $name, string $taxId, string $address): DOMElement
    {
        $p = $dom->createElement('PartnerDetails');
        $p->setAttribute('functionCode', $fn);
        $p->appendChild($this->el($dom, 'PartnerName', $name));
        $p->appendChild($this->el($dom, 'PartnerIdentifier', $taxId, ['type' => 'I-01']));
        if ($address !== '') {
            $p->appendChild($this->el($dom, 'PartnerAddress', $address));
        }

        return $p;
    }

    private function moa(DOMDocument $dom, string $code, float $amount): DOMElement
    {
        return $this->el($dom, 'Moa', $this->num($amount), ['amountTypeCode' => $code, 'currencyCode' => 'TND']);
    }

    /** @param array<string,string> $attrs */
    private function el(DOMDocument $dom, string $name, string $value, array $attrs = []): DOMElement
    {
        $el = $dom->createElement($name);
        $el->appendChild($dom->createTextNode($value));
        foreach ($attrs as $k => $v) {
            $el->setAttribute($k, $v);
        }

        return $el;
    }

    private function num(float|string $n, int $decimals = 2): string
    {
        return number_format((float) $n, $decimals, '.', '');
    }
}
