<?php

namespace App\Support;

use App\Exceptions\UnbalancedEntry;
use App\Models\AccountMapping;

/**
 * Resolves semantic account keys to chart-of-accounts codes.
 *
 * Posting services never name an account code directly — they ask for
 * `cheques_receivable` and this class looks up whatever the company has
 * mapped that to. Re-pointing the mapping (or adopting the Tunisian chart)
 * is therefore a settings change, not a deployment.
 */
class AccountMap
{
    /** @var array<string,string>|null in-request memo, cleared on write */
    private static ?array $cache = null;

    /** @return array<string,string> key => account code */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = AccountMapping::query()->pluck('account_code', 'key')->all();
        }

        return self::$cache;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * Account code for a semantic key.
     *
     * @throws UnbalancedEntry when the key is unmapped — failing loudly beats
     *         silently posting to the wrong account.
     */
    public static function code(string $key): string
    {
        $code = self::all()[$key] ?? null;
        if (! $code) {
            throw new UnbalancedEntry(
                "No account mapped for '{$key}'. Set it in Accounting → Localization settings."
            );
        }

        return $code;
    }

    /** Like code(), but returns null instead of throwing (optional postings). */
    public static function codeOrNull(string $key): ?string
    {
        return self::all()[$key] ?? null;
    }
}
