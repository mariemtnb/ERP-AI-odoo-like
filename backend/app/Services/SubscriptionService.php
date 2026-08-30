<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recurring billing. A subscription carries a next-invoice date; running
 * billing issues an invoice for every active subscription whose date has
 * arrived (possibly catching up several missed periods) and advances the date.
 * A unique (subscription, period) guarantees a period is never billed twice.
 */
class SubscriptionService
{
    public static function create(
        int $customerId,
        string $description,
        float $amount,
        string $interval,
        string $startDate,
        User $user,
    ): Subscription {
        if (! in_array($interval, Subscription::INTERVALS, true)) {
            throw new InvalidTransition("Unknown interval: {$interval}.");
        }
        if ($amount <= 0) {
            throw new InvalidTransition('Subscription amount must be positive.');
        }

        return Subscription::create([
            'number' => DocumentService::nextNumber('SUB', Subscription::class),
            'customer_id' => $customerId,
            'description' => $description,
            'amount' => round($amount, 2),
            'interval' => $interval,
            'start_date' => $startDate,
            'next_invoice_date' => $startDate,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Bill every active subscription due on or before $asOf. Returns the
     * invoices generated. Safe to run repeatedly — an already-billed period is
     * skipped by the unique constraint.
     *
     * @return array<int,SubscriptionInvoice>
     */
    public static function runBilling(?string $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? now()->toDateString());
        $due = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereDate('next_invoice_date', '<=', $asOf->toDateString())
            ->get();

        $generated = [];
        foreach ($due as $sub) {
            DB::transaction(function () use ($sub, $asOf, &$generated) {
                $sub = Subscription::lockForUpdate()->find($sub->id);
                $next = Carbon::parse($sub->next_invoice_date);
                // catch up every period that is due, one invoice each
                while ($next->lte($asOf)) {
                    $exists = SubscriptionInvoice::where('subscription_id', $sub->id)
                        ->whereDate('period_start', $next->toDateString())->exists();
                    if (! $exists) {
                        $generated[] = SubscriptionInvoice::create([
                            'subscription_id' => $sub->id,
                            'period_start' => $next->toDateString(),
                            'amount' => $sub->amount,
                        ]);
                    }
                    $next = $sub->advance($next);
                }
                $sub->update(['next_invoice_date' => $next->toDateString()]);
            });
        }

        return $generated;
    }

    public static function setStatus(Subscription $sub, string $status): Subscription
    {
        if ($sub->status === Subscription::STATUS_CANCELLED) {
            throw new InvalidTransition('A cancelled subscription cannot change status.');
        }
        if (! in_array($status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAUSED, Subscription::STATUS_CANCELLED], true)) {
            throw new InvalidTransition("Unknown status: {$status}.");
        }
        $sub->update(['status' => $status]);

        return $sub;
    }
}
