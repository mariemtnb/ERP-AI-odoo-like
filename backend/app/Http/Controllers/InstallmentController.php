<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Exceptions\UnbalancedEntry;
use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Services\InstallmentService;
use App\Services\PaymentService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Installment plans — "khlas bel taqsit" — and their settlement. */
class InstallmentController extends Controller
{
    public function index(Request $request)
    {
        $query = InstallmentPlan::with(['customer', 'supplier', 'installments'])
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer')) {
            $query->where('customer_id', $customerId);
        }
        if ($referenceType = $request->query('reference_type')) {
            $query->where('reference_type', $referenceType);
        }
        if ($referenceId = $request->query('reference_id')) {
            $query->where('reference_id', $referenceId);
        }
        if ($search = $request->query('search')) {
            $query->where('number', 'ilike', "%{$search}%");
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (InstallmentPlan $p) => $p->toApi(true))
        );
    }

    public function show(InstallmentPlan $plan)
    {
        return response()->json(
            $plan->load(['customer', 'supplier', 'installments'])->toApi(true)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'reference_type' => ['required', Rule::in(['sale', 'purchase'])],
            'reference_id' => ['required', 'integer'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'installment_count' => ['required_without:installments', 'integer', 'min:1', 'max:120'],
            'frequency' => ['sometimes', Rule::in(InstallmentPlan::FREQUENCIES)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'down_payment' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            // Custom schedule: explicit dates and amounts.
            'installments' => ['sometimes', 'array', 'min:1'],
            'installments.*.due_date' => ['required_with:installments', 'date'],
            'installments.*.amount' => ['required_with:installments', 'numeric', 'gt:0'],
        ]);

        // A custom schedule must add up to what is being financed.
        if (! empty($data['installments'])) {
            $scheduled = array_sum(array_map(fn ($i) => (float) $i['amount'], $data['installments']));
            $financed = (float) $data['total_amount'] - (float) ($data['down_payment'] ?? 0);
            if (abs($scheduled - $financed) > 0.005) {
                return response()->json([
                    'detail' => sprintf(
                        'The schedule totals %s but %s is being financed.',
                        number_format($scheduled, 3, '.', ''),
                        number_format($financed, 3, '.', '')
                    ),
                ], 422);
            }
        }

        try {
            $plan = InstallmentService::createPlan(
                referenceType: $data['reference_type'],
                referenceId: $data['reference_id'],
                totalAmount: (float) $data['total_amount'],
                count: (int) ($data['installment_count'] ?? 0),
                user: $request->user(),
                frequency: $data['frequency'] ?? 'monthly',
                startDate: $data['start_date'] ?? null,
                downPayment: (float) ($data['down_payment'] ?? 0),
                custom: $data['installments'] ?? null,
                notes: $data['notes'] ?? '',
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($plan->load(['customer', 'supplier'])->toApi(true), 201);
    }

    public function cancel(Request $request, InstallmentPlan $plan)
    {
        $data = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:255']]);

        try {
            $plan = InstallmentService::cancelPlan($plan, $data['reason'] ?? '');
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($plan->toApi(true));
    }

    /** Payments recorded against this plan. */
    public function history(InstallmentPlan $plan, Request $request)
    {
        return response()->json(
            DrfPagination::paginate(
                InstallmentService::history($plan),
                $request,
                fn (Payment $p) => $p->toApi()
            )
        );
    }

    // ---------- individual installments ----------

    public function overdue(Request $request)
    {
        $query = InstallmentService::overdue($request->query('customer'));

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Installment $i) => $i->toApi() + [
                'plan_number' => $i->plan?->number,
                'customer_name' => $i->plan?->customer?->name,
            ])
        );
    }

    /**
     * Settle one installment. `method=cheque|traite` with an `instrument_id`
     * records the promise; the money only lands when that instrument clears.
     */
    public function pay(Request $request, Installment $installment)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'instrument_id' => ['sometimes', 'nullable', 'integer', 'exists:payment_instruments,id'],
            'date' => ['sometimes', 'nullable', 'date'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        try {
            $payment = PaymentService::settleInstallment(
                installment: $installment->load('plan'),
                amount: (float) $data['amount'],
                method: $data['method'],
                user: $request->user(),
                bankAccountId: $data['bank_account_id'] ?? null,
                instrumentId: $data['instrument_id'] ?? null,
                date: $data['date'] ?? null,
                reference: $data['reference'] ?? '',
            );
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json([
            'payment' => $payment->toApi(),
            'installment' => $installment->refresh()->toApi(),
            'plan' => $installment->plan->refresh()->load('installments')->toApi(true),
        ], 201);
    }

    /** Customer credit view: exposure, arrears and pending instruments. */
    public function customerCredit(int $customerId)
    {
        return response()->json(InstallmentService::customerCredit($customerId));
    }

    /** Recompute overdue flags — safe to call repeatedly. */
    public function refreshOverdue()
    {
        return response()->json(['updated' => InstallmentService::markOverdue()]);
    }
}
