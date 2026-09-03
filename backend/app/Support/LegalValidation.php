<?php

namespace App\Support;

use App\Models\CompanyProfile;

/**
 * Advisory checks on Tunisian fiscal/banking identifiers.
 *
 * Deliberately separated from system behaviour: these return *warnings*, and
 * the system keeps working when they fail. Administrative formats change and
 * we cannot verify them from inside the app, so blocking a user on one would
 * be wrong. A company that wants hard enforcement flips
 * `enforce_legal_validation` in its profile, and the controllers turn these
 * same warnings into 422s.
 *
 * Every rule here is a *shape* check (length, digits) — never a claim about
 * what the law requires.
 */
class LegalValidation
{
    /** RIB is written as 20 digits in common Tunisian practice. */
    public const RIB_LENGTH = 20;

    /** @return string[] warnings, empty when nothing looks off */
    public static function checkRib(?string $rib): array
    {
        $rib = preg_replace('/[\s-]/', '', (string) $rib);
        if ($rib === '') {
            return [];
        }

        $warnings = [];
        if (! ctype_digit($rib)) {
            $warnings[] = 'RIB should contain digits only.';
        }
        if (strlen($rib) !== self::RIB_LENGTH) {
            $warnings[] = sprintf(
                'RIB is usually %d digits (got %d) - check with the bank.',
                self::RIB_LENGTH,
                strlen($rib)
            );
        }

        return $warnings;
    }

    /** @return string[] */
    public static function checkIban(?string $iban): array
    {
        $iban = strtoupper(preg_replace('/[\s-]/', '', (string) $iban));
        if ($iban === '') {
            return [];
        }
        if (! preg_match('/^[A-Z]{2}[0-9A-Z]+$/', $iban)) {
            return ['IBAN should start with a two-letter country code.'];
        }

        return [];
    }

    /**
     * Tax identifier (matricule fiscal). We check only that it is non-empty
     * and free of spaces — the composition of the parts is captured by the
     * separate vat/category/establishment fields on the profile.
     *
     * @return string[]
     */
    public static function checkTaxId(?string $taxId): array
    {
        $taxId = trim((string) $taxId);
        if ($taxId === '') {
            return [];
        }
        if (preg_match('/\s/', $taxId)) {
            return ['Tax identifier should not contain spaces.'];
        }

        return [];
    }

    /**
     * A cheque or traite whose due date precedes its issue date is a data
     * entry error in any jurisdiction — this one is safe to assert.
     *
     * @return string[]
     */
    public static function checkInstrumentDates(?string $issueDate, ?string $dueDate): array
    {
        if (! $issueDate || ! $dueDate) {
            return [];
        }

        return strtotime($dueDate) < strtotime($issueDate)
            ? ['Due date is before the issue date.']
            : [];
    }

    /** True when the company asked for warnings to be enforced as errors. */
    public static function isEnforced(): bool
    {
        return (bool) CompanyProfile::current()->enforce_legal_validation;
    }
}
