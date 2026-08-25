<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Creating and reading in-app notifications.
 *
 * Creating is best-effort and de-duplicated: a notification with a non-empty
 * `dedupe_key` is created once per user, so the scanner can run as often as it
 * likes without spamming. Delivery today is in-app only; the shape below is
 * where an email or SMS channel would hook in.
 */
class NotificationService
{
    /**
     * Create a notification for one user, unless an unread one with the same
     * dedupe key already exists.
     *
     * @param  array<string,mixed>  $attrs
     */
    public static function notify(User $user, array $attrs): ?Notification
    {
        try {
            $key = $attrs['dedupe_key'] ?? '';
            if ($key !== '' && self::hasOpen($user->id, $key)) {
                return null;
            }

            return Notification::create($attrs + ['user_id' => $user->id]);
            // (An email/SMS channel would be dispatched here, e.g.
            //  if ($user->wants_email) Mail::to($user)->queue(...) — not built.)
        } catch (\Throwable) {
            // A notification must never break the operation that triggered it.
            return null;
        }
    }

    /**
     * Fan a notification out to every active user holding one of these roles.
     *
     * @param  string[]  $roleKeys
     * @param  array<string,mixed>  $attrs
     * @return int  how many were actually created
     */
    public static function notifyRoles(array $roleKeys, array $attrs): int
    {
        $created = 0;
        foreach (self::usersWithRoles($roleKeys) as $user) {
            if (self::notify($user, $attrs)) {
                $created++;
            }
        }

        return $created;
    }

    /** An unread, un-dismissed notice with this key already exists for the user. */
    private static function hasOpen(int $userId, string $key): bool
    {
        return Notification::where('user_id', $userId)
            ->where('dedupe_key', $key)
            ->whereNull('read_at')
            ->exists();
    }

    /** @return Collection<int, User> */
    private static function usersWithRoles(array $roleKeys): Collection
    {
        return User::whereIn('role', $roleKeys)->where('is_active', true)->get();
    }

    // ---------------- reading ----------------

    public static function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    public static function markRead(Notification $notification): Notification
    {
        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    public static function markAllRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
