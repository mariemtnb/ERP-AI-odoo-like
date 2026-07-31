<?php

namespace App\Services;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * The audit trail.
 *
 * Records who changed what, from where, and — where the caller supplies it —
 * why. Writing is best-effort by design: an audit failure must never take down
 * the business transaction that triggered it, because a lost log line is a far
 * smaller problem than a rolled-back invoice.
 */
class AuditService
{
    /** Never store these, whatever a model's attributes happen to contain. */
    private const REDACTED = [
        'password', 'password_hash', 'remember_token', 'jwt_secret',
        'api_key', 'secret', 'token',
    ];

    /** Set while a bulk operation runs so its rows can be grouped. */
    private static ?string $batchId = null;

    /** Actor override — set to 'agent' while the AI is acting. */
    private static string $actor = AuditEntry::ACTOR_USER;

    private static string $reason = '';

    // ---------------- context ----------------

    /** Group everything written inside the callback under one batch id. */
    public static function batch(callable $callback, ?string $label = null): mixed
    {
        $previous = self::$batchId;
        self::$batchId = strtoupper(substr(md5(uniqid((string) $label, true)), 0, 12));

        try {
            return $callback(self::$batchId);
        } finally {
            self::$batchId = $previous;
        }
    }

    /** Attribute everything written inside the callback to the AI agent. */
    public static function asAgent(callable $callback): mixed
    {
        $previous = self::$actor;
        self::$actor = AuditEntry::ACTOR_AGENT;

        try {
            return $callback();
        } finally {
            self::$actor = $previous;
        }
    }

    /** Attach a business reason to everything written inside the callback. */
    public static function because(string $reason, callable $callback): mixed
    {
        $previous = self::$reason;
        self::$reason = $reason;

        try {
            return $callback();
        } finally {
            self::$reason = $previous;
        }
    }

    public static function currentBatchId(): ?string
    {
        return self::$batchId;
    }

    // ---------------- writing ----------------

    /**
     * Record an event against a model.
     *
     * @param  array<string,mixed>|null  $old
     * @param  array<string,mixed>|null  $new
     */
    public static function record(
        string $event,
        Model $subject,
        ?array $old = null,
        ?array $new = null,
        ?User $user = null,
        string $reason = '',
    ): ?AuditEntry {
        try {
            $old = $old !== null ? self::redact($old) : null;
            $new = $new !== null ? self::redact($new) : null;

            $changed = [];
            if ($old !== null && $new !== null) {
                foreach ($new as $key => $value) {
                    // Loose compare: Eloquent hands back "10.00" where the
                    // original was 10.0, and that is not a change worth logging.
                    if (! array_key_exists($key, $old) || $old[$key] != $value) {
                        $changed[] = $key;
                    }
                }
                if (! $changed) {
                    return null;   // nothing actually moved
                }
            }

            return AuditEntry::create([
                'event' => $event,
                'auditable_type' => class_basename($subject),
                'auditable_id' => $subject->getKey(),
                'label' => self::labelFor($subject),
                'user_id' => ($user ?? self::currentUser())?->id,
                'actor' => self::$actor,
                'old_values' => $old,
                'new_values' => $new,
                'changed_fields' => $changed ?: null,
                'reason' => $reason ?: self::$reason,
                'ip' => self::requestValue(fn () => Request::ip()),
                'user_agent' => substr(self::requestValue(fn () => Request::userAgent()), 0, 255),
                'url' => substr(self::requestValue(fn () => Request::fullUrl()), 0, 255),
                'method' => self::requestValue(fn () => Request::method()),
                'batch_id' => self::$batchId ?? '',
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Auditing must never break the operation it is observing.
            return null;
        }
    }

    /** Log a data export — who took what out of the system. */
    public static function recordExport(string $what, int $rowCount, ?User $user = null): ?AuditEntry
    {
        try {
            return AuditEntry::create([
                'event' => AuditEntry::EVENT_EXPORTED,
                'auditable_type' => $what,
                'auditable_id' => null,
                'label' => "{$rowCount} rows",
                'user_id' => ($user ?? self::currentUser())?->id,
                'actor' => self::$actor,
                'ip' => self::requestValue(fn () => Request::ip()),
                'user_agent' => substr(self::requestValue(fn () => Request::userAgent()), 0, 255),
                'url' => substr(self::requestValue(fn () => Request::fullUrl()), 0, 255),
                'method' => self::requestValue(fn () => Request::method()),
                'batch_id' => self::$batchId ?? '',
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    // ---------------- reading ----------------

    /** Full history of one record, oldest last. */
    public static function timelineFor(Model $subject)
    {
        return AuditEntry::with('user')
            ->where('auditable_type', class_basename($subject))
            ->where('auditable_id', $subject->getKey())
            ->orderByDesc('id');
    }

    // ---------------- helpers ----------------

    private static function currentUser(): ?User
    {
        try {
            return Auth::guard('api')->user() ?? Auth::user();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Request facades throw outside an HTTP context (console, queue, tests). */
    private static function requestValue(callable $get): string
    {
        try {
            return (string) ($get() ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param array<string,mixed> $values */
    private static function redact(array $values): array
    {
        foreach (array_keys($values) as $key) {
            foreach (self::REDACTED as $secret) {
                if (str_contains(strtolower((string) $key), $secret)) {
                    $values[$key] = '[redacted]';
                }
            }
        }

        return $values;
    }

    /** Best human name for a record, for timelines that outlive the row. */
    private static function labelFor(Model $subject): string
    {
        foreach (['number', 'name', 'code', 'title', 'label', 'email'] as $attribute) {
            $value = $subject->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                return substr($value, 0, 200);
            }
        }

        return class_basename($subject) . ' #' . $subject->getKey();
    }
}
