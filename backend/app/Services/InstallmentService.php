<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\CompanyProfile;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\PaymentInstrument;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Installment plans — "khlas bel taqsit".
 *
 * A plan does not change what is owed, only when it is due, so creating one
 * posts nothing: the receivable already exists from the invoice. Money is
 * recognised when an installment is actually paid, by PaymentService.
 *
 * Rounding rule: installments are rounded to the currency's precision and the
 * last one absorbs the remainder, so the schedule always sums exactly to the
 * financed amount.
 */
class InstallmentService
{
    /** @var array<string,string> frequency → Carbon adder */
    private const STEPS = [
        'weekly' => 'addWeeks',
        'biweekly' => 'addWeeks',
        'monthly' => 'addMonths',
        'quarterly' => 'addMonths',
    ];

    private static function advance(Carbon $from, string $frequency, int $n): Carbon
    {
        $date = $from->copy();

        return match ($frequency) {
            'weekly' => $date->addWeeks($n),
            'biweekly' => $date->addWeeks($n * 2),
            'quarterly' => $date->addMonths($n * 3),
            default => $date->addMonths($n),
        };
    }

    /**
     * Build a plan and its schedule.
     *
     * @param  array<int, array{due_date:string, amount:float|string}>|null  $custom
     *         explicit schedule; when given, count/frequency are ignored.
     */
    public static function createPlan(
        string $referenceType,
        int $referenceId,
        float $totalAmount,
        int $count,
        User $user,
        string $frequency = 'monthly',
        ?string $startDate = null,
        float $downPayment = 0,
        ?array $custom = null,
        string $notes = '',
    ): InstallmentPlan {
        $profile = CompanyProfile::current();
        $decimals = (int) $profile->currency_decimals;

        if ($totalAmount <= 0) {
            throw new InvalidTransition('The financed amount must be greater than zero.');
        }
        if ($downPayment < 0 || $downPayment > $totalAmount) {
            throw new InvalidTransition('The down payment must be between zero and the total.');
        }
        if (! $custom && $count < 1) {
            throw new InvalidTransition('A plan needs at least one installment.');
        }

        [$customerId, $supplierId] = self::partyFor($referenceType, $referenceId);

        return DB::transaction(function () use (
            $referenceType, $referenceId, $totalAmount, $count, $user, $frequency,
            $startDate, $downPayment, $custom, $notes, $decimals, $customerId, $supplierId
        ) {
            // One active plan per document — re-planning means cancelling first.
            $existing = InstallmentPlan::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('status', InstallmentPlan::STATUS_ACTIVE)
                ->exists();
            if ($existing) {
                throw new InvalidTransition('This document already has an active installment plan.');
            }

            $start = Carbon::parse($startDate ?? now()->toDateString());

            $plan = InstallmentPlan::create([
                'number' => DocumentService::nextNumber('PLAN', InstallmentPlan::class),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'customer_id' => $customerId,
                'supplier_id' => $supplierId,
                'total_amount' => round($totalAmount, $decimals),
                'down_payment' => round($downPayment, $decimals),
                'installment_count' => $custom ? count($custom) : $count,
                'frequency' => $custom ? 'custom' : $frequency,
                'start_date' => $start->toDateString(),
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $sequence = 1;

            // The down payment is installment #1, due immediately.
            if ($downPayment > 0) {
                Installment::create([
                    'plan_id' => $plan->id,
                    'sequence' => $sequence++,
                    'due_date' => $start->toDateString(),
                    'amount' => round($downPayment, $decimals),
                    'is_down_payment' => true,
                ]);
            }

            if ($custom) {
                foreach ($custom as $row) {
                    Installment::create([
                        'plan_id' => $plan->id,
                        'sequence' => $sequence++,
                        'due_date' => Carbon::parse($row['due_date'])->toDateString(),
                        'amount' => round((float) $row['amount'], $decimals),
                    ]);
                }
            } else {
                $financed = round($totalAmount - $downPayment, $decimals);
                $each = round($financed / $count, $decimals);
                $allocated = 0.0;

                for ($n = 1; $n <= $count; $n++) {
                    // Last installment absorbs the rounding remainder.
                    $amount = $n === $count ? round($financed - $allocated, $decimals) : $each;
                    $allocated = round($allocated + $amount, $decimals);

                    Installment::create([
                        'plan_id' => $plan->id,
                        'sequence' => $sequence++,
                        'due_date' => self::advance($start, $frequency, $n)->toDateString(),
                        'amount' => $amount,
                    ]);
                }
            }

            $plan->update(['installment_count' => $sequence - 1]);

            return $plan->load('installments');
        });
    }

    /** @return array{0:?int,1:?int} [customer_id, supplier_id] */
    private static function partyFor(string $referenceType, int $referenceId): array
    {
        if ($referenceType === 'sale') {
            return [Sale::find($referenceId)?->customer_id, null];
        }
        if ($referenceType === 'purchase') {
            return [null, PurchaseOrder::find($referenceId)?->supplier_id];
        }

        return [null, null];
    }

    /**
     * Apply money to an installment. Called by PaymentService once the payment
     * itself has posted, so this only maintains the schedule's own state.
     */
    public static function applyPayment(
        Installment $installment,
        float $amount,
        string $method = '',
        ?string $paidAt = null,
    ): Installment {
        $decimals = (int) CompanyProfile::current()->currency_decimals;
        $amount = round($amount, $decimals);

        if ($amount <= 0) {
            throw new InvalidTransition('The payment amount must be greater than zero.');
        }
        if ($installment->status === Installment::STATUS_CANCELLED) {
            throw new InvalidTransition('This installment was cancelled.');
        }
        if ($amount - $installment->remainingAmount() > 0.0005) {
            throw new InvalidTransition(sprintf(
                'Payment (%s) exceeds what is left on this installment (%s).',
                number_format($amount, $decimals, '.', ''),
                number_format($installment->remainingAmount(), $decimals, '.', '')
            ));
        }

        return DB::transaction(function () use ($installment, $amount, $method, $paidAt, $decimals) {
            $paid = round((float) $installment->paid_amount + $amount, $decimals);
            $settled = $paid >= (float) $installment->amount - 0.0005;

            $installment->update([
                'paid_amount' => $paid,
                'status' => $settled ? Installment::STATUS_PAID : Installment::STATUS_PARTIAL,
                'payment_method' => $method ?: $installment->payment_method,
                'paid_at' => $settled ? ($paidAt ?? now()->toDateString()) : $installment->paid_at,
            ]);

            self::refreshPlan($installment->plan);

            return $installment->refresh();
        });
    }

    /** Recompute the plan's paid total and status from its schedule. */
    public static function refreshPlan(InstallmentPlan $plan): InstallmentPlan
    {
        $decimals = (int) CompanyProfile::current()->currency_decimals;
        $plan->load('installments');

        $paid = round((float) $plan->installments->sum('paid_amount'), $decimals);
        $allSettled = $plan->installments->every(
            fn (Installment $i) => in_array($i->status, [Installment::STATUS_PAID, Installment::STATUS_CANCELLED], true)
        );

        $status = $plan->status;
        if ($status === InstallmentPlan::STATUS_ACTIVE && $allSettled) {
            $status = InstallmentPlan::STATUS_COMPLETED;
        } elseif ($status === InstallmentPlan::STATUS_COMPLETED && ! $allSettled) {
            // A bounced cheque can reopen a completed plan.
            $status = InstallmentPlan::STATUS_ACTIVE;
        }

        $plan->update(['paid_amount' => $paid, 'status' => $status]);

        return $plan;
    }

    /**
     * Mark overdue everything past its due date plus the grace period.
     * Idempotent — safe to call from a scheduler or on page load.
     */
    public static function markOverdue(): int
    {
        $grace = (int) CompanyProfile::current()->late_payment_grace_days;
        $cutoff = now()->subDays($grace)->toDateString();

        return Installment::whereIn('status', [Installment::STATUS_PENDING, Installment::STATUS_PARTIAL])
            ->whereDate('due_date', '<', $cutoff)
            ->update(['status' => Installment::STATUS_OVERDUE]);
    }

    /** Overdue installments, most late first. */
    public static function overdue(?int $customerId = null)
    {
        $grace = (int) CompanyProfile::current()->late_payment_grace_days;
        $cutoff = now()->subDays($grace)->toDateString();

        return Installment::with('plan.customer')
            ->whereNotIn('status', [Installment::STATUS_PAID, Installment::STATUS_CANCELLED])
            ->whereDate('due_date', '<', $cutoff)
            ->when($customerId, fn ($q) => $q->whereHas(
                'plan',
                fn ($p) => $p->where('customer_id', $customerId)
            ))
            ->orderBy('due_date');
    }

    /**
     * Customer credit view: what they owe across all plans, what is late, and
     * how much sits in cheques/traites not yet cleared.
     */
    public static function customerCredit(int $customerId): array
    {
        $plans = InstallmentPlan::with('installments')
            ->where('customer_id', $customerId)
            ->get();

        $outstanding = round($plans->sum(fn (InstallmentPlan $p) => $p->remainingAmount()), 3);
        $overdue = round($plans->sum(fn (InstallmentPlan $p) => $p->overdueAmount()), 3);

        $instruments = PaymentInstrument::where('customer_id', $customerId)
            ->whereIn('status', PaymentInstrument::OPEN_STATUSES)
            ->get();
        $bounced = PaymentInstrument::where('customer_id', $customerId)
            ->where('status', PaymentInstrument::STATUS_BOUNCED)
            ->get();

        return [
            'customer_id' => $customerId,
            'plan_count' => $plans->count(),
            'active_plan_count' => $plans->where('status', InstallmentPlan::STATUS_ACTIVE)->count(),
            'total_financed' => round((float) $plans->sum('total_amount'), 3),
            'total_paid' => round((float) $plans->sum('paid_amount'), 3),
            'outstanding_amount' => $outstanding,
            'overdue_amount' => $overdue,
            'instruments_pending_count' => $instruments->count(),
            'instruments_pending_amount' => round((float) $instruments->sum('amount'), 3),
            'bounced_count' => $bounced->count(),
            'bounced_amount' => round((float) $bounced->sum('amount'), 3),
            // Purely descriptive risk hint for the UI. Not a credit decision:
            // scoring rules are a business policy, not something we assume.
            'has_arrears' => $overdue > 0 || $bounced->isNotEmpty(),
            'plans' => $plans->map(fn (InstallmentPlan $p) => $p->toApi(true))->values()->all(),
        ];
    }

    public static function cancelPlan(InstallmentPlan $plan, string $reason = ''): InstallmentPlan
    {
        if ($plan->status !== InstallmentPlan::STATUS_ACTIVE) {
            throw new InvalidTransition("Only active plans can be cancelled (status: {$plan->status}).");
        }

        return DB::transaction(function () use ($plan, $reason) {
            $plan->installments()
                ->whereIn('status', [Installment::STATUS_PENDING, Installment::STATUS_OVERDUE])
                ->update(['status' => Installment::STATUS_CANCELLED]);

            $plan->update([
                'status' => InstallmentPlan::STATUS_CANCELLED,
                'notes' => trim($plan->notes . ($reason !== '' ? "\nCancelled: {$reason}" : '')),
            ]);

            return $plan->refresh()->load('installments');
        });
    }

    // ---------------- instrument hooks ----------------

    /** A cleared cheque/traite settles the installment it was raised against. */
    public static function settleFromInstrument(PaymentInstrument $instrument, User $user): void
    {
        if ($instrument->reference_type !== 'installment' || ! $instrument->reference_id) {
            return;
        }
        $installment = Installment::find($instrument->reference_id);
        if (! $installment || $installment->remainingAmount() <= 0) {
            return;
        }

        $amount = min((float) $instrument->amount, $installment->remainingAmount());
        self::applyPayment(
            $installment,
            $amount,
            $instrument->kind,
            $instrument->cleared_at?->toDateString(),
        );
    }

    /** A bounced instrument puts its installment back on the books. */
    public static function unsettleFromInstrument(PaymentInstrument $instrument): void
    {
        if ($instrument->reference_type !== 'installment' || ! $instrument->reference_id) {
            return;
        }
        $installment = Installment::find($instrument->reference_id);
        if (! $installment) {
            return;
        }

        $decimals = (int) CompanyProfile::current()->currency_decimals;
        $reverted = max(0, round((float) $installment->paid_amount - (float) $instrument->amount, $decimals));

        $installment->update([
            'paid_amount' => $reverted,
            'status' => $reverted > 0 ? Installment::STATUS_PARTIAL : Installment::STATUS_PENDING,
            'paid_at' => null,
        ]);

        self::refreshPlan($installment->plan);
    }

    /** Payments recorded against a plan, newest first. */
    public static function history(InstallmentPlan $plan)
    {
        return Payment::with(['instrument', 'bankAccount'])
            ->whereIn('installment_id', $plan->installments()->pluck('id'))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }
}
