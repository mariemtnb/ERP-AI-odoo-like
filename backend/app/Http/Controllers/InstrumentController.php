<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Exceptions\UnbalancedEntry;
use App\Models\Attachment;
use App\Models\PaymentInstrument;
use App\Services\InstrumentService;
use App\Support\DrfPagination;
use App\Support\LegalValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Cheques and effets de commerce (traites / kembyelet).
 *
 * Transitions are POST actions rather than a status field on PATCH: each one
 * posts to the ledger, so they must not be reachable by a blind update.
 */
class InstrumentController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentInstrument::with(['customer', 'supplier', 'bankAccount', 'draweeBank'])
            ->orderByRaw('due_date is null, due_date')
            ->orderByDesc('id');

        foreach (['kind', 'direction', 'status'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }
        if ($request->boolean('outstanding')) {
            $query->whereIn('status', PaymentInstrument::OPEN_STATUSES);
        }
        if ($request->boolean('overdue')) {
            $query->whereIn('status', PaymentInstrument::OPEN_STATUSES)
                ->whereDate('due_date', '<', now()->toDateString());
        }
        if ($customerId = $request->query('customer')) {
            $query->where('customer_id', $customerId);
        }
        if ($supplierId = $request->query('supplier')) {
            $query->where('supplier_id', $supplierId);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('number', 'ilike', "%{$search}%")
                ->orWhere('instrument_reference', 'ilike', "%{$search}%")
                ->orWhere('counterparty_name', 'ilike', "%{$search}%"));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (PaymentInstrument $i) => $i->toApi())
        );
    }

    public function show(PaymentInstrument $instrument)
    {
        return response()->json(
            $instrument->load(['customer', 'supplier', 'bankAccount', 'draweeBank', 'events.creator', 'events.journalEntry'])
                ->toApi(withEvents: true)
        );
    }

    public function summary()
    {
        return response()->json(InstrumentService::summary());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(PaymentInstrument::KINDS)],
            'direction' => ['required', Rule::in(PaymentInstrument::DIRECTIONS)],
            'instrument_reference' => ['sometimes', 'nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'issue_date' => ['required', 'date'],
            // A traite without a due date is meaningless; a cheque may omit it.
            'due_date' => ['required_if:kind,traite', 'nullable', 'date'],
            'place_of_issue' => ['sometimes', 'nullable', 'string', 'max:80'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'counterparty_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'drawee_bank_id' => ['sometimes', 'nullable', 'integer', 'exists:banks,id'],
            'drawee_rib' => ['sometimes', 'nullable', 'string', 'max:30'],
            'reference_type' => ['sometimes', 'nullable', Rule::in(['sale', 'purchase', 'installment', 'manual', ''])],
            'reference_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string'],
            // false leaves it in draft instead of recognising it immediately.
            'auto_activate' => ['sometimes', 'boolean'],
        ]);

        foreach (['instrument_reference', 'place_of_issue', 'counterparty_name', 'drawee_rib', 'reference_type', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = '';
            }
        }

        // An incoming instrument comes from a customer, an outgoing one goes
        // to a supplier — mixing them would corrupt the control accounts.
        $incoming = $data['direction'] === PaymentInstrument::DIRECTION_IN;
        if ($incoming && ! empty($data['supplier_id'])) {
            return response()->json(['detail' => 'An incoming instrument belongs to a customer, not a supplier.'], 422);
        }
        if (! $incoming && ! empty($data['customer_id'])) {
            return response()->json(['detail' => 'An outgoing instrument belongs to a supplier, not a customer.'], 422);
        }
        if (empty($data['customer_id']) && empty($data['supplier_id']) && ($data['counterparty_name'] ?? '') === '') {
            return response()->json(['detail' => 'Name the counterparty, or link a customer/supplier.'], 422);
        }

        $warnings = LegalValidation::checkInstrumentDates($data['issue_date'], $data['due_date'] ?? null);
        if ($warnings) {
            // A due date before the issue date is a data error in any reading.
            return response()->json(['detail' => $warnings[0], 'errors' => ['due_date' => $warnings]], 422);
        }
        $warnings = LegalValidation::checkRib($data['drawee_rib'] ?? null);
        if ($warnings && LegalValidation::isEnforced()) {
            return response()->json(['detail' => $warnings[0], 'errors' => ['drawee_rib' => $warnings]], 422);
        }

        try {
            $instrument = InstrumentService::create($data, $request->user());
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json(
            $instrument->load(['customer', 'supplier'])->toApi(withEvents: true) + ['warnings' => $warnings],
            201
        );
    }

    /** Editable fields only — never the status, which moves via the actions. */
    public function update(Request $request, PaymentInstrument $instrument)
    {
        $data = $request->validate([
            'instrument_reference' => ['sometimes', 'nullable', 'string', 'max:60'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'place_of_issue' => ['sometimes', 'nullable', 'string', 'max:80'],
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'drawee_bank_id' => ['sometimes', 'nullable', 'integer', 'exists:banks,id'],
            'drawee_rib' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        foreach ($data as $key => $value) {
            if ($value === null && ! in_array($key, ['due_date', 'bank_account_id', 'drawee_bank_id'], true)) {
                $data[$key] = '';
            }
        }

        // The amount is intentionally immutable: it is already in the ledger.
        $instrument->update($data);

        return response()->json($instrument->refresh()->toApi());
    }

    // ---------- lifecycle actions ----------

    private function act(callable $action)
    {
        try {
            return response()->json($action()->toApi(withEvents: true));
        } catch (InvalidTransition|UnbalancedEntry $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }
    }

    public function receive(Request $request, PaymentInstrument $instrument)
    {
        return $this->act(fn () => InstrumentService::receive($instrument, $request->user()));
    }

    public function issue(Request $request, PaymentInstrument $instrument)
    {
        return $this->act(fn () => InstrumentService::issue($instrument, $request->user()));
    }

    public function deposit(Request $request, PaymentInstrument $instrument)
    {
        $data = $request->validate([
            'bank_account_id' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'date' => ['sometimes', 'nullable', 'date'],
        ]);

        return $this->act(fn () => InstrumentService::deposit(
            $instrument,
            $request->user(),
            $data['bank_account_id'] ?? null,
            $data['date'] ?? null,
        ));
    }

    public function markPending(Request $request, PaymentInstrument $instrument)
    {
        return $this->act(fn () => InstrumentService::markPendingClearance($instrument, $request->user()));
    }

    public function clear(Request $request, PaymentInstrument $instrument)
    {
        $data = $request->validate([
            'date' => ['sometimes', 'nullable', 'date'],
            'fees' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return $this->act(fn () => InstrumentService::clear(
            $instrument,
            $request->user(),
            $data['date'] ?? null,
            (float) ($data['fees'] ?? 0),
        ));
    }

    public function bounce(Request $request, PaymentInstrument $instrument)
    {
        $data = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fees' => ['sometimes', 'numeric', 'min:0'],
            'date' => ['sometimes', 'nullable', 'date'],
            'move_to_doubtful' => ['sometimes', 'boolean'],
        ]);

        return $this->act(fn () => InstrumentService::bounce(
            $instrument,
            $request->user(),
            $data['reason'] ?? '',
            (float) ($data['fees'] ?? 0),
            $data['date'] ?? null,
            (bool) ($data['move_to_doubtful'] ?? false),
        ));
    }

    public function settle(Request $request, PaymentInstrument $instrument)
    {
        $data = $request->validate(['notes' => ['sometimes', 'nullable', 'string', 'max:255']]);

        return $this->act(fn () => InstrumentService::settle(
            $instrument, $request->user(), $data['notes'] ?? ''
        ));
    }

    public function cancel(Request $request, PaymentInstrument $instrument)
    {
        $data = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:255']]);

        return $this->act(fn () => InstrumentService::cancel(
            $instrument, $request->user(), $data['reason'] ?? ''
        ));
    }

    // ---------- attachments ----------

    public function attach(Request $request, PaymentInstrument $instrument)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:png,jpg,jpeg,webp,pdf'],
        ]);

        $file = $request->file('file');
        $path = $file->store("instruments/{$instrument->id}", 'local');

        $attachment = InstrumentService::attach(
            $instrument,
            $path,
            $file->getClientOriginalName(),
            $file->getMimeType() ?? '',
            $file->getSize() ?? 0,
            $request->user(),
        );

        return response()->json($attachment->toApi(), 201);
    }

    public function downloadAttachment(Attachment $attachment)
    {
        if (! Storage::disk('local')->exists($attachment->path)) {
            return response()->json(['detail' => 'File not found.'], 404);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }
}
