<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Services\InstallmentService;
use App\Services\InstrumentService;
use App\Services\PaymentService;
use App\Services\ReconciliationService;
use Illuminate\Http\Request;

/**
 * Treasury overview — one call backing the finance cards on the dashboard,
 * so the frontend does not fan out to six endpoints on every page load.
 */
class TreasuryController extends Controller
{
    public function dashboard(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $instruments = InstrumentService::summary();
        $reconciliation = ReconciliationService::pendingSummary();
        $collections = PaymentService::collectionSummary($from, $to);

        $overdue = InstallmentService::overdue()->get();
        $activePlans = InstallmentPlan::where('status', InstallmentPlan::STATUS_ACTIVE)->count();

        $upcoming = Installment::whereNotIn('status', [
            Installment::STATUS_PAID, Installment::STATUS_CANCELLED,
        ])
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays(30)->toDateString())
            ->get();

        return response()->json([
            'date_from' => $from,
            'date_to' => $to,
            'instruments' => $instruments,
            'reconciliation' => $reconciliation,
            'collections' => $collections,
            'installments' => [
                'active_plans' => $activePlans,
                'overdue_count' => $overdue->count(),
                'overdue_amount' => round($overdue->sum(fn (Installment $i) => $i->remainingAmount()), 3),
                'due_next_30_days_count' => $upcoming->count(),
                'due_next_30_days_amount' => round($upcoming->sum(fn (Installment $i) => $i->remainingAmount()), 3),
            ],
            'bank_accounts' => BankAccount::with('bank')->where('is_active', true)->get()
                ->map(fn (BankAccount $a) => [
                    'id' => $a->id,
                    'label' => $a->label,
                    'bank_name' => $a->bank?->short_name,
                    'currency' => $a->currency,
                    'current_balance' => (string) $a->current_balance,
                    'last_reconciled_at' => $a->last_reconciled_at?->format('Y-m-d'),
                ])->values()->all(),
        ]);
    }
}
