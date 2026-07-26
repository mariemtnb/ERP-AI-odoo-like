<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\CompanyProfile;
use App\Models\Journal;
use App\Support\AccountMap;
use App\Support\LegalValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Localization settings: company fiscal profile, accounting journals and the
 * semantic account mapping every posting service resolves through.
 */
class LocalizationController extends Controller
{
    // ---------- company profile ----------

    public function profile()
    {
        $profile = CompanyProfile::current();

        return response()->json($profile->toApi() + [
            'warnings' => $this->profileWarnings($profile),
        ]);
    }

    /** @return string[] advisory only — see LegalValidation */
    private function profileWarnings(CompanyProfile $profile): array
    {
        return LegalValidation::checkTaxId($profile->tax_id);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'trade_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'string', 'size:2'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email'],

            'tax_id' => ['sometimes', 'nullable', 'string', 'max:40'],
            'vat_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'category_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'establishment_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'trade_register' => ['sometimes', 'nullable', 'string', 'max:40'],
            'customs_code' => ['sometimes', 'nullable', 'string', 'max:40'],

            'fiscal_regime' => ['sometimes', Rule::in(CompanyProfile::REGIMES)],
            'vat_registered' => ['sometimes', 'boolean'],
            'default_vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'withholding_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'stamp_duty_amount' => ['sometimes', 'numeric', 'min:0'],
            'stamp_duty_enabled' => ['sometimes', 'boolean'],
            'fiscal_year_start_month' => ['sometimes', 'integer', 'min:1', 'max:12'],

            'currency' => ['sometimes', 'string', 'size:3'],
            'currency_decimals' => ['sometimes', 'integer', 'min:0', 'max:3'],
            'locale' => ['sometimes', Rule::in(CompanyProfile::LOCALES)],

            'invoice_number_format' => ['sometimes', 'string', 'max:60'],
            'default_payment_terms_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'late_payment_grace_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'enforce_legal_validation' => ['sometimes', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            if ($value === null && ! in_array($key, ['opening_date'], true)) {
                $data[$key] = '';
            }
        }

        $profile = CompanyProfile::current();
        $warnings = LegalValidation::checkTaxId($data['tax_id'] ?? $profile->tax_id);

        // Enforcement is opt-in: by default an odd-looking identifier is
        // reported, not rejected.
        if ($warnings && LegalValidation::isEnforced()) {
            return response()->json(['detail' => $warnings[0], 'errors' => ['tax_id' => $warnings]], 422);
        }

        $profile->update($data);

        return response()->json($profile->refresh()->toApi() + ['warnings' => $warnings]);
    }

    // ---------- journals ----------

    public function journals()
    {
        return response()->json([
            'results' => Journal::orderBy('code')->get()
                ->map(fn (Journal $j) => $j->toApi())->values()->all(),
        ]);
    }

    public function storeJournal(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:journals,code'],
            'name' => ['required', 'string', 'max:100'],
            'name_fr' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(Journal::TYPES)],
        ]);
        $data['name_fr'] ??= '';

        return response()->json(Journal::create($data)->toApi(), 201);
    }

    // ---------- account mapping ----------

    public function mappings()
    {
        return response()->json([
            'results' => AccountMapping::orderBy('key')->get()
                ->map(fn (AccountMapping $m) => $m->toApi())->values()->all(),
            'required_keys' => AccountMapping::KEYS,
        ]);
    }

    /**
     * Re-point one or more semantic keys. This is the supported way to adapt
     * the books to a company's own chart of accounts.
     */
    public function updateMappings(Request $request)
    {
        $data = $request->validate([
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.key' => ['required', 'string', 'max:40'],
            'mappings.*.account_code' => ['required', 'string', 'max:10'],
        ]);

        // Refuse codes that do not exist: a typo here would silently misfile
        // every future posting.
        $codes = collect($data['mappings'])->pluck('account_code')->unique();
        $known = Account::whereIn('code', $codes)->pluck('code');
        $unknown = $codes->diff($known);
        if ($unknown->isNotEmpty()) {
            return response()->json([
                'detail' => 'Unknown account code(s): ' . $unknown->implode(', '),
                'errors' => ['mappings' => $unknown->values()->all()],
            ], 422);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['mappings'] as $row) {
                AccountMapping::updateOrCreate(
                    ['key' => $row['key']],
                    ['account_code' => $row['account_code']]
                );
            }
        });
        AccountMap::flush();

        return response()->json([
            'results' => AccountMapping::orderBy('key')->get()
                ->map(fn (AccountMapping $m) => $m->toApi())->values()->all(),
        ]);
    }

    /**
     * Switch the whole mapping to a chart template.
     *
     * `tunisia` points the keys at the Tunisian accounts seeded by the
     * localization migration; `default` restores the generic chart. Accounts
     * themselves are never deleted, so this is reversible and history keeps
     * resolving.
     */
    public function applyChartTemplate(Request $request)
    {
        $data = $request->validate([
            'template' => ['required', Rule::in(['tunisia', 'default'])],
        ]);

        $tunisia = [
            'receivable' => '411',
            'payable' => '401',
            'cash' => '54',
            'bank' => '532',
            'cheques_receivable' => '414',
            'cheques_in_collection' => '5112',
            'cheques_payable' => '404',
            'notes_receivable' => '413',
            'notes_in_collection' => '5113',
            'notes_payable' => '403',
            'customer_advances' => '4191',
            'supplier_advances' => '4091',
            'doubtful_receivable' => '416',
            'vat_collected' => '4367',
            'vat_deductible' => '4366',
            'stamp_duty' => '4458',
            'bank_fees' => '627',
            'suspense' => '471',
        ];

        $default = [
            'receivable' => '1100',
            'payable' => '2000',
            'cash' => '1000',
            'bank' => '1000',
        ];

        $map = $data['template'] === 'tunisia' ? $tunisia : $default;

        DB::transaction(function () use ($map) {
            foreach ($map as $key => $code) {
                if (Account::where('code', $code)->exists()) {
                    AccountMapping::updateOrCreate(['key' => $key], ['account_code' => $code]);
                }
            }
        });
        AccountMap::flush();

        return response()->json([
            'detail' => "Applied the '{$data['template']}' chart template.",
            'results' => AccountMapping::orderBy('key')->get()
                ->map(fn (AccountMapping $m) => $m->toApi())->values()->all(),
        ]);
    }
}
