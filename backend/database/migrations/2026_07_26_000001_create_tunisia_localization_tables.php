<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tunisia localization layer: company fiscal profile, configurable account
 * mapping, journals, banks & reconciliation, payment instruments (cheques and
 * effets de commerce / kembyelet) and installment plans ("khlas bel taqsit").
 *
 * Design note — nothing here hardcodes a legal rule. Every account used by the
 * posting services is resolved through `account_mappings` (semantic key →
 * account code), which ships with a default mapping onto the EXISTING chart so
 * current behaviour is unchanged, and can be re-pointed at the Tunisian chart
 * (seeded below) from the localization settings screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ---------------------------------------------------------------
        // Company fiscal profile — single row, edited in Settings.
        // ---------------------------------------------------------------
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name', 200)->default('');
            $table->string('trade_name', 200)->default('');
            $table->text('address')->default('');
            $table->string('city', 100)->default('');
            $table->string('postal_code', 20)->default('');
            $table->string('country', 2)->default('TN');
            $table->string('phone', 30)->default('');
            $table->string('email')->default('');

            // Tax identifiers. Kept as free text with an optional, toggleable
            // format check — identifier formats are an administrative matter
            // and must stay editable without a code change.
            $table->string('tax_id', 40)->default('');            // matricule fiscal
            $table->string('vat_code', 10)->default('');
            $table->string('category_code', 10)->default('');
            $table->string('establishment_code', 10)->default('');
            $table->string('trade_register', 40)->default('');    // RC
            $table->string('customs_code', 40)->default('');

            // Fiscal settings — all configurable, no legal value baked in.
            $table->string('fiscal_regime', 30)->default('reel'); // reel|forfaitaire|export|exempt
            $table->boolean('vat_registered')->default(true);
            $table->decimal('default_vat_rate', 5, 2)->default(19);
            $table->decimal('withholding_rate', 5, 2)->default(0);
            $table->decimal('stamp_duty_amount', 10, 3)->default(0); // timbre fiscal
            $table->boolean('stamp_duty_enabled')->default(false);
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);

            $table->string('currency', 3)->default('TND');
            $table->unsignedTinyInteger('currency_decimals')->default(3);
            $table->string('locale', 5)->default('fr');           // fr|ar|en

            // Documents & terms
            $table->string('invoice_number_format', 60)->default('INV-{YYYY}-{SEQ:4}');
            $table->unsignedSmallInteger('default_payment_terms_days')->default(30);
            $table->unsignedSmallInteger('late_payment_grace_days')->default(0);

            // Legal validation is advisory by default: warnings, never blocks.
            $table->boolean('enforce_legal_validation')->default(false);
            $table->timestamps();
        });

        DB::table('company_profiles')->insert([
            'legal_name' => 'Demo SARL',
            'country' => 'TN',
            'currency' => 'TND',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ---------------------------------------------------------------
        // Accounting journals (journaux) — categories required by Tunisian
        // bookkeeping practice. Entries reference one; existing entries stay
        // NULL, which the reports treat as "miscellaneous".
        // ---------------------------------------------------------------
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('name_fr', 100)->default('');
            // sales|purchase|cash|bank|cheque|commercial_paper|installment|advance|misc
            $table->string('type', 24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('type');
        });

        DB::table('journals')->insert(array_map(
            fn ($j) => $j + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            [
                ['code' => 'VT', 'name' => 'Sales', 'name_fr' => 'Journal des ventes', 'type' => 'sales'],
                ['code' => 'AC', 'name' => 'Purchases', 'name_fr' => 'Journal des achats', 'type' => 'purchase'],
                ['code' => 'CA', 'name' => 'Cash', 'name_fr' => 'Journal de caisse', 'type' => 'cash'],
                ['code' => 'BQ', 'name' => 'Bank', 'name_fr' => 'Journal de banque', 'type' => 'bank'],
                ['code' => 'CH', 'name' => 'Cheques', 'name_fr' => 'Journal des chèques', 'type' => 'cheque'],
                ['code' => 'EF', 'name' => 'Commercial paper', 'name_fr' => 'Journal des effets', 'type' => 'commercial_paper'],
                ['code' => 'EH', 'name' => 'Installments', 'name_fr' => 'Journal des échéances', 'type' => 'installment'],
                ['code' => 'AV', 'name' => 'Advances', 'name_fr' => 'Journal des avances', 'type' => 'advance'],
                ['code' => 'OD', 'name' => 'Miscellaneous', 'name_fr' => 'Opérations diverses', 'type' => 'misc'],
            ]
        ));

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('journal_id')->nullable()->after('entry_date')
                ->constrained('journals')->nullOnDelete();
        });

        // ---------------------------------------------------------------
        // Tunisian chart of accounts (plan comptable) — seeded ALONGSIDE the
        // existing chart, not replacing it. Codes follow common Tunisian
        // practice and are a starting point to be confirmed with the
        // company's accountant; all of them are editable in the UI.
        // ---------------------------------------------------------------
        DB::table('accounts')->insert(array_map(
            fn ($a) => $a + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            [
                ['code' => '401', 'name' => 'Fournisseurs', 'type' => 'liability'],
                ['code' => '403', 'name' => 'Fournisseurs - effets à payer', 'type' => 'liability'],
                ['code' => '404', 'name' => 'Fournisseurs - chèques à payer', 'type' => 'liability'],
                ['code' => '4091', 'name' => 'Fournisseurs - avances et acomptes versés', 'type' => 'asset'],
                ['code' => '411', 'name' => 'Clients', 'type' => 'asset'],
                ['code' => '413', 'name' => 'Clients - effets à recevoir', 'type' => 'asset'],
                ['code' => '414', 'name' => 'Clients - chèques à recevoir', 'type' => 'asset'],
                ['code' => '416', 'name' => 'Clients douteux ou litigieux', 'type' => 'asset'],
                ['code' => '4191', 'name' => 'Clients - avances et acomptes reçus', 'type' => 'liability'],
                ['code' => '4366', 'name' => 'TVA déductible', 'type' => 'asset'],
                ['code' => '4367', 'name' => 'TVA collectée', 'type' => 'liability'],
                ['code' => '4458', 'name' => 'Timbre fiscal', 'type' => 'liability'],
                ['code' => '471', 'name' => "Compte d'attente", 'type' => 'asset'],
                ['code' => '5112', 'name' => 'Chèques à encaisser', 'type' => 'asset'],
                ['code' => '5113', 'name' => "Effets à l'encaissement", 'type' => 'asset'],
                ['code' => '532', 'name' => 'Banques', 'type' => 'asset'],
                ['code' => '54', 'name' => 'Caisse', 'type' => 'asset'],
                ['code' => '627', 'name' => 'Services bancaires et assimilés', 'type' => 'expense'],
                ['code' => '654', 'name' => 'Pertes sur créances irrécouvrables', 'type' => 'expense'],
            ]
        ));

        // ---------------------------------------------------------------
        // Semantic account mapping. THE indirection that keeps accounting
        // rules configurable: services ask for `cheques_receivable`, never
        // for a literal code.
        // ---------------------------------------------------------------
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('account_code', 10);
            $table->string('label', 120)->default('');
            $table->string('description', 255)->default('');
            $table->timestamps();
        });

        // Defaults point at the EXISTING chart wherever an equivalent already
        // exists, so nothing that works today changes behaviour. Keys with no
        // pre-existing equivalent point at the Tunisian accounts above.
        DB::table('account_mappings')->insert(array_map(
            fn ($m) => $m + ['created_at' => $now, 'updated_at' => $now],
            [
                ['key' => 'receivable', 'account_code' => '1100', 'label' => 'Accounts receivable', 'description' => 'Clients'],
                ['key' => 'payable', 'account_code' => '2000', 'label' => 'Accounts payable', 'description' => 'Fournisseurs'],
                ['key' => 'cash', 'account_code' => '1000', 'label' => 'Cash', 'description' => 'Caisse'],
                ['key' => 'bank', 'account_code' => '1000', 'label' => 'Bank', 'description' => 'Banque — override per bank account'],
                ['key' => 'inventory', 'account_code' => '1200', 'label' => 'Inventory', 'description' => 'Stocks'],
                ['key' => 'revenue', 'account_code' => '4000', 'label' => 'Sales revenue', 'description' => 'Ventes'],
                ['key' => 'cogs', 'account_code' => '5000', 'label' => 'Cost of goods sold', 'description' => 'Coût des ventes'],

                ['key' => 'cheques_receivable', 'account_code' => '414', 'label' => 'Cheques on hand', 'description' => 'Chèques reçus non remis'],
                ['key' => 'cheques_in_collection', 'account_code' => '5112', 'label' => 'Cheques in collection', 'description' => "Chèques remis à l'encaissement"],
                ['key' => 'cheques_payable', 'account_code' => '404', 'label' => 'Cheques issued', 'description' => 'Chèques émis non débités'],
                ['key' => 'notes_receivable', 'account_code' => '413', 'label' => 'Notes receivable', 'description' => 'Effets à recevoir / kembyelet'],
                ['key' => 'notes_in_collection', 'account_code' => '5113', 'label' => 'Notes in collection', 'description' => "Effets remis à l'encaissement"],
                ['key' => 'notes_payable', 'account_code' => '403', 'label' => 'Notes payable', 'description' => 'Effets à payer'],
                ['key' => 'customer_advances', 'account_code' => '4191', 'label' => 'Customer advances', 'description' => 'Avances reçues des clients'],
                ['key' => 'supplier_advances', 'account_code' => '4091', 'label' => 'Supplier advances', 'description' => 'Avances versées aux fournisseurs'],
                ['key' => 'doubtful_receivable', 'account_code' => '416', 'label' => 'Doubtful receivables', 'description' => 'Clients douteux — impayés'],
                ['key' => 'vat_collected', 'account_code' => '4367', 'label' => 'VAT collected', 'description' => 'TVA collectée'],
                ['key' => 'vat_deductible', 'account_code' => '4366', 'label' => 'VAT deductible', 'description' => 'TVA déductible'],
                ['key' => 'stamp_duty', 'account_code' => '4458', 'label' => 'Stamp duty', 'description' => 'Timbre fiscal'],
                ['key' => 'bank_fees', 'account_code' => '627', 'label' => 'Bank fees', 'description' => 'Frais et commissions bancaires'],
                ['key' => 'suspense', 'account_code' => '471', 'label' => 'Suspense', 'description' => "Compte d'attente — écarts de rapprochement"],
            ]
        ));

        // ---------------------------------------------------------------
        // Banks. Seeded with Tunisian banks by name only — SWIFT/BIC and
        // branch data are left blank rather than guessed, and are editable.
        // ---------------------------------------------------------------
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // RIB bank code when known
            $table->string('name', 120);
            $table->string('short_name', 30)->default('');
            $table->string('swift', 15)->default('');
            $table->string('country', 2)->default('TN');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('banks')->insert(array_map(
            fn ($b) => $b + ['swift' => '', 'country' => 'TN', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            [
                ['code' => 'BIAT', 'name' => 'Banque Internationale Arabe de Tunisie', 'short_name' => 'BIAT'],
                ['code' => 'STB', 'name' => 'Société Tunisienne de Banque', 'short_name' => 'STB'],
                ['code' => 'BNA', 'name' => 'Banque Nationale Agricole', 'short_name' => 'BNA'],
                ['code' => 'ATB', 'name' => 'Arab Tunisian Bank', 'short_name' => 'ATB'],
                ['code' => 'ATTIJARI', 'name' => 'Attijari Bank', 'short_name' => 'Attijari'],
                ['code' => 'AMEN', 'name' => 'Amen Bank', 'short_name' => 'Amen'],
                ['code' => 'BH', 'name' => 'BH Bank', 'short_name' => 'BH'],
                ['code' => 'BT', 'name' => 'Banque de Tunisie', 'short_name' => 'BT'],
                ['code' => 'UIB', 'name' => 'Union Internationale de Banques', 'short_name' => 'UIB'],
                ['code' => 'UBCI', 'name' => 'Union Bancaire pour le Commerce et l\'Industrie', 'short_name' => 'UBCI'],
                ['code' => 'ZITOUNA', 'name' => 'Banque Zitouna', 'short_name' => 'Zitouna'],
                ['code' => 'BARAKA', 'name' => 'Al Baraka Bank Tunisia', 'short_name' => 'Al Baraka'],
                ['code' => 'BTE', 'name' => 'Banque de Tunisie et des Emirats', 'short_name' => 'BTE'],
                ['code' => 'BTS', 'name' => 'Banque Tunisienne de Solidarité', 'short_name' => 'BTS'],
                ['code' => 'QNB', 'name' => 'QNB Tunisia', 'short_name' => 'QNB'],
                ['code' => 'WIFAK', 'name' => 'Wifak International Bank', 'short_name' => 'Wifak'],
                ['code' => 'POSTE', 'name' => 'La Poste Tunisienne (CCP)', 'short_name' => 'CCP'],
            ]
        ));

        // ---------------------------------------------------------------
        // Bank accounts. RIB is stored as free text with an optional check —
        // we validate shape, never reject on an unverifiable rule.
        // ---------------------------------------------------------------
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->string('label', 120);
            $table->string('branch', 120)->default('');
            $table->string('rib', 30)->default('');            // 20 digits in TN practice
            $table->string('iban', 40)->default('');
            $table->string('account_number', 40)->default('');
            $table->string('currency', 3)->default('TND');
            // GL account this bank account posts to; falls back to mapping 'bank'.
            $table->foreignId('gl_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('opening_balance', 14, 3)->default(0);
            $table->date('opening_date')->nullable();
            $table->decimal('current_balance', 14, 3)->default(0);
            $table->date('last_reconciled_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('bank_id');
        });

        // ---------------------------------------------------------------
        // Imported / manual bank statement lines.
        // ---------------------------------------------------------------
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('operation_date');
            $table->date('value_date')->nullable();
            $table->string('label', 255)->default('');
            $table->string('reference', 80)->default('');
            // Signed: positive = credit (money in), negative = debit (money out).
            $table->decimal('amount', 14, 3);
            $table->decimal('running_balance', 14, 3)->nullable();
            // unmatched|partially_matched|matched|disputed|ignored
            $table->string('status', 20)->default('unmatched');
            $table->decimal('matched_amount', 14, 3)->default(0);
            $table->string('import_batch', 40)->default('');
            $table->string('source', 20)->default('manual');   // manual|csv|xlsx
            $table->text('notes')->default('');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['bank_account_id', 'operation_date']);
            $table->index('status');
            // Deliberately NOT unique: a statement can legitimately carry two
            // identical lines (two equal cash deposits on the same day with no
            // reference). Re-import safety is handled in ReconciliationService,
            // which dedupes against previous batches only.
            $table->index(['bank_account_id', 'operation_date', 'amount'], 'bank_tx_lookup');
        });

        // ---------------------------------------------------------------
        // Payment instruments: cheques AND effets de commerce (traites /
        // kembyelet). One table — the lifecycle is identical; `kind` selects
        // the vocabulary and the GL mapping keys used when posting.
        // ---------------------------------------------------------------
        Schema::create('payment_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40);                      // our internal ref
            $table->string('kind', 20);                        // cheque|traite
            $table->string('direction', 10);                   // incoming|outgoing
            $table->string('instrument_reference', 60)->default(''); // cheque/traite serial
            $table->decimal('amount', 14, 3);

            $table->date('issue_date');
            $table->date('due_date')->nullable();              // traites always have one
            $table->string('place_of_issue', 80)->default('');

            // Counterparty — exactly one of the two is set.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('counterparty_name', 200)->default('');

            // draft|issued|received|deposited|pending_clearance|cleared|
            // bounced|cancelled|settled
            $table->string('status', 24)->default('draft');

            // Bank account it will be deposited to / drawn on.
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            // Counterparty's bank (drawee) — informational.
            $table->foreignId('drawee_bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('drawee_rib', 30)->default('');

            // Business document that produced it.
            $table->string('reference_type', 20)->default('');  // sale|purchase|installment|manual
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->date('deposited_at')->nullable();
            $table->date('cleared_at')->nullable();
            $table->date('bounced_at')->nullable();
            $table->string('bounce_reason', 255)->default('');
            $table->decimal('bank_fees', 12, 3)->default(0);

            $table->text('notes')->default('');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['kind', 'number']);
            $table->index(['status', 'due_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('direction');
        });

        // Append-only lifecycle history, mirroring the stock ledger's model.
        Schema::create('instrument_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('payment_instruments')->cascadeOnDelete();
            $table->string('event', 30);                       // deposit|clear|bounce|…
            $table->string('from_status', 24)->default('');
            $table->string('to_status', 24);
            $table->decimal('amount', 14, 3)->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->default('');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('instrument_id');
        });

        // ---------------------------------------------------------------
        // Installment plans — "khlas bel taqsit".
        // ---------------------------------------------------------------
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();            // PLAN-YYYY-NNNN
            $table->string('reference_type', 20);              // sale|purchase
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->decimal('total_amount', 14, 3);
            $table->decimal('down_payment', 14, 3)->default(0);
            $table->unsignedSmallInteger('installment_count');
            $table->string('frequency', 12)->default('monthly'); // weekly|monthly|quarterly|custom
            $table->date('start_date');
            $table->decimal('paid_amount', 14, 3)->default(0);
            // active|completed|cancelled|defaulted
            $table->string('status', 16)->default('active');
            $table->text('notes')->default('');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
            $table->index('status');
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 14, 3);
            $table->decimal('paid_amount', 14, 3)->default(0);
            // pending|partially_paid|paid|overdue|cancelled
            $table->string('status', 16)->default('pending');
            $table->string('payment_method', 20)->default('');  // cash|transfer|cheque|traite|card
            $table->date('paid_at')->nullable();
            $table->boolean('is_down_payment')->default(false);
            $table->text('notes')->default('');
            $table->timestamps();
            $table->unique(['plan_id', 'sequence']);
            $table->index(['status', 'due_date']);
        });

        // ---------------------------------------------------------------
        // Payments — the single settlement fact. Every cash receipt, bank
        // transfer, cheque clearing and installment payment is one row,
        // carrying the journal entry it produced.
        // ---------------------------------------------------------------
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();            // PAY-YYYY-NNNN
            $table->string('direction', 10);                   // inbound|outbound
            // cash|bank_transfer|cheque|traite|card|bank_deposit|bank_withdrawal
            $table->string('method', 20);
            $table->decimal('amount', 14, 3);
            $table->date('payment_date');

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('instrument_id')->nullable()->constrained('payment_instruments')->nullOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('installments')->nullOnDelete();

            $table->string('reference_type', 20)->default(''); // sale|purchase|advance|manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_advance')->default(false);     // avance / acompte

            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('reference', 80)->default('');
            $table->text('notes')->default('');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
            $table->index('payment_date');
        });

        // ---------------------------------------------------------------
        // Reconciliation: a bank line may match several business objects and
        // one object may be split across lines — hence a join table.
        // ---------------------------------------------------------------
        Schema::create('reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            // payment|instrument|installment|sale|purchase|adjustment
            $table->string('matchable_type', 20);
            $table->unsignedBigInteger('matchable_id')->nullable();
            $table->decimal('amount', 14, 3);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('note')->default('');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['matchable_type', 'matchable_id']);
        });

        // ---------------------------------------------------------------
        // Attachments (scanned cheque, traite, bank statement…).
        // ---------------------------------------------------------------
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 30);                  // instrument|payment|bank_transaction
            $table->unsignedBigInteger('owner_id');
            $table->string('path', 255);
            $table->string('filename', 200);
            $table->string('mime', 100)->default('');
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('reconciliation_matches');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('installments');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('instrument_events');
        Schema::dropIfExists('payment_instruments');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('account_mappings');

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_id');
        });
        Schema::dropIfExists('journals');
        Schema::dropIfExists('company_profiles');

        DB::table('accounts')->whereIn('code', [
            '401', '403', '404', '4091', '411', '413', '414', '416', '4191',
            '4366', '4367', '4458', '471', '5112', '5113', '532', '54', '627', '654',
        ])->delete();
    }
};
