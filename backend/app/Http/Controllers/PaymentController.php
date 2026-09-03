<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Exceptions\UnbalancedEntry;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Payments: cash, transfers, deposits, withdrawals and advances. */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['customer', 'supplier', 'bankAccount', 'instrument', 'journalEntry'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        foreach (['direction', 'method'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }
        if ($customerId = $request->query('customer')) {
            $query->where('customer_id', $customerId);
        }
        if ($supplierId = $request->query('supplier')) {
            $query->where('supplier_id', $supplierId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('payment_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('payment_date', '<=', $to);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('number', 'ilike', "%{$search}%")
                ->orWhere('reference', 'ilike', "%{$search}%"));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (Payment $p) => $p->toApi())
        );
    }

    public function show(Payment $payment)
    {
        return response()->json(
            $payment->load(['customer', 'supplier', 'bankAccount', 'instrument', 'journalEntry'])->toApi()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(Payment::DIRECTIONS)],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['sometimes', 'nullable', 'date'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'instrument_id' => ['sometimes', 'nullable', 'integer', 'exists:payment_instruments,id'],
            'installment_id' => ['sometimes', 'nullable', 'integer', 'exists:installments,id'],
            'reference_type' => ['sometimes', 'nullable', Rule::in(['sale', 'purchase', 'advance', 'manual', ''])],
            'reference_id' => ['sometimes', 'nullable', 'integer'],
            'is_advance' => ['sometimes', 'boolean'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        foreach (['reference_type', 'reference', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = '';
            }
        }

        try {
            $payment = PaymentService::record($data, $request->user());
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json(
            $payment->load(['customer', 'supplier', 'bankAccount', 'journalEntry'])->toApi(),
            201
        );
    }

    /** Settle a foreign-currency receivable/payable, posting realized FX gain/loss. */
    public function settleForeign(Request $request)
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(Payment::DIRECTIONS)],
            'method' => ['required', Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_CARD])],
            'currency_code' => ['required', 'string', 'size:3'],
            'foreign_amount' => ['required', 'numeric', 'gt:0'],
            'book_rate' => ['required', 'numeric', 'gt:0'],
            'settlement_rate' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['sometimes', 'nullable', 'date'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'reference_type' => ['sometimes', 'nullable', Rule::in(['sale', 'purchase', 'manual', ''])],
            'reference_id' => ['sometimes', 'nullable', 'integer'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        try {
            $payment = PaymentService::recordForeignSettlement($data, $request->user());
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json(
            $payment->load(['customer', 'supplier', 'bankAccount', 'journalEntry'])->toApi(),
            201
        );
    }

    /** Pay a supplier net of withholding tax (retenue à la source). */
    public function withholdSupplier(Request $request)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_CARD])],
            'gross_amount' => ['required', 'numeric', 'gt:0'],
            'withholding_rate' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'lt:100'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'payment_date' => ['sometimes', 'nullable', 'date'],
            'reference_type' => ['sometimes', 'nullable', Rule::in(['purchase', 'manual', ''])],
            'reference_id' => ['sometimes', 'nullable', 'integer'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        try {
            $payment = PaymentService::recordSupplierWithholding($data, $request->user());
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json(
            $payment->load(['supplier', 'bankAccount', 'journalEntry'])->toApi(),
            201
        );
    }

    /** Cash vs bank collections over a period. */
    public function summary(Request $request)
    {
        return response()->json(PaymentService::collectionSummary(
            $request->query('from'),
            $request->query('to'),
        ));
    }
}
