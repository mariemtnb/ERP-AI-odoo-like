<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\ReconciliationService;
use App\Support\DrfPagination;
use App\Support\LegalValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Banks, company bank accounts, and statement lines. */
class BankController extends Controller
{
    // ---------- banks ----------

    public function banks(Request $request)
    {
        $query = Bank::withCount('accounts')->orderBy('name');
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('short_name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%"));
        }

        return response()->json([
            'results' => $query->get()->map(fn (Bank $b) => $b->toApi())->values()->all(),
        ]);
    }

    public function storeBank(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:banks,code'],
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:30'],
            'swift' => ['sometimes', 'nullable', 'string', 'max:15'],
            'country' => ['sometimes', 'string', 'size:2'],
        ]);
        $data['short_name'] ??= '';
        $data['swift'] ??= '';

        return response()->json(Bank::create($data)->toApi(), 201);
    }

    public function updateBank(Request $request, Bank $bank)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:30'],
            'swift' => ['sometimes', 'nullable', 'string', 'max:15'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $bank->update($data);

        return response()->json($bank->refresh()->toApi());
    }

    // ---------- bank accounts ----------

    public function accounts(Request $request)
    {
        $query = BankAccount::with(['bank', 'glAccount'])->orderByDesc('is_default')->orderBy('label');
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        if ($bankId = $request->query('bank')) {
            $query->where('bank_id', $bankId);
        }

        return response()->json([
            'results' => $query->get()->map(fn (BankAccount $a) => $a->toApi())->values()->all(),
        ]);
    }

    public function showAccount(BankAccount $bankAccount)
    {
        // Full identifiers on the detail endpoint only.
        return response()->json($bankAccount->load(['bank', 'glAccount'])->toApi(full: true));
    }

    private function accountRules(bool $create): array
    {
        $required = $create ? 'required' : 'sometimes';

        return [
            'bank_id' => [$required, 'integer', 'exists:banks,id'],
            'label' => [$required, 'string', 'max:120'],
            'branch' => ['sometimes', 'nullable', 'string', 'max:120'],
            'rib' => ['sometimes', 'nullable', 'string', 'max:30'],
            'iban' => ['sometimes', 'nullable', 'string', 'max:40'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:40'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'gl_account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'opening_balance' => ['sometimes', 'numeric'],
            'opening_date' => ['sometimes', 'nullable', 'date'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array{0:array,1:string[]} sanitised data and advisory warnings */
    private function prepareAccount(array $data): array
    {
        foreach (['branch', 'rib', 'iban', 'account_number'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = '';
            }
        }

        $warnings = array_merge(
            LegalValidation::checkRib($data['rib'] ?? null),
            LegalValidation::checkIban($data['iban'] ?? null),
        );

        return [$data, $warnings];
    }

    public function storeAccount(Request $request)
    {
        [$data, $warnings] = $this->prepareAccount($request->validate($this->accountRules(true)));

        if ($warnings && LegalValidation::isEnforced()) {
            return response()->json(['detail' => $warnings[0], 'errors' => ['rib' => $warnings]], 422);
        }

        $account = DB::transaction(function () use ($data) {
            $account = BankAccount::create($data);
            $account->update(['current_balance' => $account->opening_balance]);
            if ($account->is_default) {
                BankAccount::where('id', '!=', $account->id)->update(['is_default' => false]);
            }

            return $account;
        });

        return response()->json(
            $account->load('bank')->toApi(full: true) + ['warnings' => $warnings],
            201
        );
    }

    public function updateAccount(Request $request, BankAccount $bankAccount)
    {
        [$data, $warnings] = $this->prepareAccount($request->validate($this->accountRules(false)));

        if ($warnings && LegalValidation::isEnforced()) {
            return response()->json(['detail' => $warnings[0], 'errors' => ['rib' => $warnings]], 422);
        }

        DB::transaction(function () use ($bankAccount, $data) {
            $bankAccount->update($data);
            if ($bankAccount->is_default) {
                BankAccount::where('id', '!=', $bankAccount->id)->update(['is_default' => false]);
            }
        });

        return response()->json(
            $bankAccount->refresh()->load('bank')->toApi(full: true) + ['warnings' => $warnings]
        );
    }

    // ---------- statement lines ----------

    public function transactions(Request $request)
    {
        $query = BankTransaction::with('bankAccount')
            ->orderByDesc('operation_date')
            ->orderByDesc('id');

        if ($accountId = $request->query('bank_account')) {
            $query->where('bank_account_id', $accountId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('operation_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('operation_date', '<=', $to);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('label', 'ilike', "%{$search}%")
                ->orWhere('reference', 'ilike', "%{$search}%"));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (BankTransaction $t) => $t->toApi())
        );
    }

    public function showTransaction(BankTransaction $bankTransaction)
    {
        return response()->json(
            $bankTransaction->load(['bankAccount', 'matches'])->toApi(withMatches: true)
        );
    }

    /** Key in a single line (no statement file to import). */
    public function storeTransaction(Request $request)
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'operation_date' => ['required', 'date'],
            'value_date' => ['sometimes', 'nullable', 'date'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'running_balance' => ['sometimes', 'nullable', 'numeric'],
        ]);
        $data['label'] ??= '';
        $data['reference'] ??= '';
        $data['created_by'] = $request->user()->id;
        $data['source'] = 'manual';

        return response()->json(BankTransaction::create($data)->toApi(), 201);
    }

    /**
     * Import a statement. Accepts a CSV/text upload or a pre-parsed `rows`
     * array (which is how the frontend sends XLSX after parsing it client
     * side, avoiding a spreadsheet dependency on the backend).
     */
    public function importTransactions(Request $request)
    {
        $request->validate([
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'file' => ['sometimes', 'file', 'max:5120'],
            'rows' => ['sometimes', 'array'],
            'rows.*.operation_date' => ['required_with:rows', 'date'],
            'rows.*.amount' => ['required_with:rows', 'numeric'],
        ]);

        $account = BankAccount::findOrFail($request->input('bank_account_id'));

        try {
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $extension = strtolower($file->getClientOriginalExtension());
                if (! in_array($extension, ['csv', 'txt'], true)) {
                    return response()->json([
                        'detail' => 'Upload a CSV file, or parse the spreadsheet client-side and send "rows".',
                    ], 422);
                }
                $rows = ReconciliationService::parseCsv($file->get());
                $source = 'csv';
            } elseif ($request->filled('rows')) {
                $rows = $request->input('rows');
                $source = 'xlsx';
            } else {
                return response()->json(['detail' => 'Send a "file" upload or a "rows" array.'], 422);
            }

            $result = ReconciliationService::import($account, $rows, $request->user(), $source);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json($result, 201);
    }

    /** Preview a CSV without storing anything — lets the user check the parse. */
    public function previewImport(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        try {
            $rows = ReconciliationService::parseCsv($request->file('file')->get());
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 422);
        }

        return response()->json([
            'count' => count($rows),
            'rows' => array_slice($rows, 0, 20),
        ]);
    }
}
