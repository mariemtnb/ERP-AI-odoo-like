<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Notification;
use App\Models\PaymentInstrument;
use App\Models\Product;
use App\Models\PurchaseOrder;

/**
 * Turns the current state of the business into notifications.
 *
 * Every detector uses a stable dedupe key, so this can run on a schedule (or be
 * triggered by hand) as often as you like without creating duplicates. It
 * notifies the people who can act — managers and admins for money and stock,
 * admins for approvals.
 */
class NotificationScanner
{
    /** A cheque/traite this many days from its due date is "due soon". */
    private const DUE_SOON_DAYS = 7;

    private const MANAGERS = ['admin', 'manager'];
    private const ADMINS = ['admin'];

    /**
     * Run every detector.
     *
     * @return array<string,int>  how many notifications each created
     */
    public static function scan(): array
    {
        return [
            'instruments_due' => self::instrumentsDueSoon(),
            'instruments_bounced' => self::instrumentsBounced(),
            'installments_overdue' => self::installmentsOverdue(),
            'low_stock' => self::lowStock(),
            'purchases_pending' => self::purchasesPendingApproval(),
        ];
    }

    // ---------------- detectors ----------------

    /** Cheques and traites due within the next few days and still open. */
    private static function instrumentsDueSoon(): int
    {
        $until = now()->addDays(self::DUE_SOON_DAYS)->toDateString();
        $created = 0;

        $rows = PaymentInstrument::with(['customer', 'supplier'])
            ->whereIn('status', PaymentInstrument::OPEN_STATUSES)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $until)
            ->whereDate('due_date', '>=', now()->toDateString())
            ->get();

        foreach ($rows as $i) {
            $word = $i->kind === PaymentInstrument::KIND_CHEQUE ? 'Cheque' : 'Traite';
            $created += NotificationService::notifyRoles(self::MANAGERS, [
                'type' => 'instrument.due',
                'category' => 'treasury',
                'severity' => Notification::WARNING,
                'title' => "{$word} due on {$i->due_date->format('Y-m-d')}",
                'body' => "{$i->number} — {$i->counterpartyLabel()} — {$i->amount}. Due soon.",
                'link' => '/instruments',
                'subject_type' => 'instrument',
                'subject_id' => $i->id,
                'dedupe_key' => "instrument_due:{$i->id}",
            ]);
        }

        return $created;
    }

    /** Cheques/traites that came back unpaid and aren't settled. */
    private static function instrumentsBounced(): int
    {
        $created = 0;
        foreach (PaymentInstrument::where('status', PaymentInstrument::STATUS_BOUNCED)->get() as $i) {
            $word = $i->kind === PaymentInstrument::KIND_CHEQUE ? 'Cheque' : 'Traite';
            $created += NotificationService::notifyRoles(self::MANAGERS, [
                'type' => 'instrument.bounced',
                'category' => 'treasury',
                'severity' => Notification::CRITICAL,
                'title' => "{$word} bounced",
                'body' => "{$i->number} — {$i->counterpartyLabel()} — {$i->amount} was returned unpaid.",
                'link' => '/instruments',
                'subject_type' => 'instrument',
                'subject_id' => $i->id,
                'dedupe_key' => "instrument_bounced:{$i->id}",
            ]);
        }

        return $created;
    }

    /** Instalments past their due date and not fully paid. */
    private static function installmentsOverdue(): int
    {
        $created = 0;
        $rows = Installment::with('plan.customer')
            ->whereNotIn('status', [Installment::STATUS_PAID, Installment::STATUS_CANCELLED])
            ->whereDate('due_date', '<', now()->toDateString())
            ->get();

        foreach ($rows as $i) {
            if (! $i->isOverdue()) {
                continue;   // respects the company's grace period
            }
            $who = $i->plan?->customer?->name ?? 'a customer';
            $created += NotificationService::notifyRoles(self::MANAGERS, [
                'type' => 'installment.overdue',
                'category' => 'treasury',
                'severity' => Notification::WARNING,
                'title' => 'Instalment overdue',
                'body' => "{$i->plan?->number} #{$i->sequence} — {$who} — "
                    . number_format($i->remainingAmount(), 3, '.', '') . ' still due.',
                'link' => '/installments',
                'subject_type' => 'installment',
                'subject_id' => $i->id,
                'dedupe_key' => "installment_overdue:{$i->id}",
            ]);
        }

        return $created;
    }

    /** Products at or below their minimum stock level. */
    private static function lowStock(): int
    {
        $created = 0;
        $rows = Product::where('is_active', true)
            ->whereColumn('quantity_in_stock', '<=', 'min_stock_level')
            ->get();

        foreach ($rows as $p) {
            $created += NotificationService::notifyRoles(self::MANAGERS, [
                'type' => 'stock.low',
                'category' => 'inventory',
                'severity' => Notification::WARNING,
                'title' => "Low stock: {$p->name}",
                'body' => "{$p->sku} — " . (float) $p->quantity_in_stock . ' left '
                    . '(minimum ' . (float) $p->min_stock_level . '). Consider restocking.',
                'link' => '/products',
                'subject_type' => 'product',
                'subject_id' => $p->id,
                'dedupe_key' => "stock_low:{$p->id}",
            ]);
        }

        return $created;
    }

    /** Purchase orders waiting for an admin to approve them. */
    private static function purchasesPendingApproval(): int
    {
        $created = 0;
        $rows = PurchaseOrder::with('supplier')
            ->where('status', PurchaseOrder::STATUS_PENDING_APPROVAL)
            ->get();

        foreach ($rows as $po) {
            $created += NotificationService::notifyRoles(self::ADMINS, [
                'type' => 'purchase.pending_approval',
                'category' => 'purchasing',
                'severity' => Notification::INFO,
                'title' => 'Purchase order needs approval',
                'body' => "{$po->number} — {$po->supplier?->name} — {$po->total_amount}.",
                'link' => '/purchases',
                'subject_type' => 'purchase',
                'subject_id' => $po->id,
                'dedupe_key' => "po_pending:{$po->id}",
            ]);
        }

        return $created;
    }
}
