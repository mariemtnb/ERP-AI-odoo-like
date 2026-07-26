<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Company fiscal identity and localization settings — a single row.
 *
 * Everything here is data, not code: tax identifiers, VAT rate, stamp duty,
 * numbering format and payment terms are edited in Settings so a change in
 * administrative practice never requires a deployment.
 */
class CompanyProfile extends Model
{
    public const REGIMES = ['reel', 'forfaitaire', 'export', 'exempt'];
    public const LOCALES = ['fr', 'ar', 'en'];

    protected $fillable = [
        'legal_name', 'trade_name', 'address', 'city', 'postal_code', 'country',
        'phone', 'email',
        'tax_id', 'vat_code', 'category_code', 'establishment_code',
        'trade_register', 'customs_code',
        'fiscal_regime', 'vat_registered', 'default_vat_rate', 'withholding_rate',
        'stamp_duty_amount', 'stamp_duty_enabled', 'fiscal_year_start_month',
        'currency', 'currency_decimals', 'locale',
        'invoice_number_format', 'default_payment_terms_days', 'late_payment_grace_days',
        'enforce_legal_validation',
    ];

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'stamp_duty_enabled' => 'boolean',
            'enforce_legal_validation' => 'boolean',
            'default_vat_rate' => 'decimal:2',
            'withholding_rate' => 'decimal:2',
            'stamp_duty_amount' => 'decimal:3',
        ];
    }

    /** The singleton profile, created on demand so the API never 404s. */
    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrCreate([], ['country' => 'TN', 'currency' => 'TND']);
    }

    /**
     * Full fiscal identifier as it is usually written on Tunisian invoices:
     * matricule / VAT code / category / establishment. Purely presentational —
     * the parts are stored separately and any of them may be blank.
     */
    public function fullTaxId(): string
    {
        $parts = array_filter([
            $this->tax_id, $this->vat_code, $this->category_code, $this->establishment_code,
        ], fn ($p) => $p !== null && $p !== '');

        return implode('/', $parts);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'tax_id' => $this->tax_id,
            'vat_code' => $this->vat_code,
            'category_code' => $this->category_code,
            'establishment_code' => $this->establishment_code,
            'full_tax_id' => $this->fullTaxId(),
            'trade_register' => $this->trade_register,
            'customs_code' => $this->customs_code,
            'fiscal_regime' => $this->fiscal_regime,
            'vat_registered' => $this->vat_registered,
            'default_vat_rate' => (string) $this->default_vat_rate,
            'withholding_rate' => (string) $this->withholding_rate,
            'stamp_duty_amount' => (string) $this->stamp_duty_amount,
            'stamp_duty_enabled' => $this->stamp_duty_enabled,
            'fiscal_year_start_month' => $this->fiscal_year_start_month,
            'currency' => $this->currency,
            'currency_decimals' => $this->currency_decimals,
            'locale' => $this->locale,
            'invoice_number_format' => $this->invoice_number_format,
            'default_payment_terms_days' => $this->default_payment_terms_days,
            'late_payment_grace_days' => $this->late_payment_grace_days,
            'enforce_legal_validation' => $this->enforce_legal_validation,
        ];
    }
}
