<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\PaymentInstrument;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InstallmentService;
use App\Services\InstrumentService;
use App\Services\PaymentService;
use App\Services\ReconciliationService;
use Illuminate\Database\Seeder;

/**
 * Demo data for the Tunisia localization layer: a company profile, a bank
 * account, a cheque that clears, a cheque that bounces, a traite in
 * collection, an installment plan mid-way through, and a statement to
 * reconcile.
 *
 * Idempotent — bails out if instruments already exist.
 */
class TunisiaDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (PaymentInstrument::exists()) {
            return;
        }

        $admin = User::where('email', 'admin@erp.local')->first();
        if (! $admin) {
            return;   // DemoSeeder has not run yet
        }

        // --- company fiscal profile -----------------------------------
        CompanyProfile::current()->update([
            'legal_name' => 'Société Demo ERP SARL',
            'trade_name' => 'Demo ERP',
            'address' => '12 Rue de la République',
            'city' => 'Tunis',
            'postal_code' => '1001',
            'country' => 'TN',
            'phone' => '+216 71 100 100',
            'email' => 'contact@demo-erp.tn',
            'tax_id' => '1234567',
            'vat_code' => 'A',
            'category_code' => 'M',
            'establishment_code' => '000',
            'trade_register' => 'B01234562019',
            'fiscal_regime' => 'reel',
            'vat_registered' => true,
            'default_vat_rate' => 19,
            'stamp_duty_enabled' => true,
            'stamp_duty_amount' => 1.000,
            'currency' => 'TND',
            'currency_decimals' => 3,
            'locale' => 'fr',
            'invoice_number_format' => 'FAC-{YYYY}-{SEQ:4}',
            'default_payment_terms_days' => 30,
            'late_payment_grace_days' => 5,
        ]);

        // --- bank account ---------------------------------------------
        $biat = Bank::where('code', 'BIAT')->first() ?? Bank::first();
        $account = BankAccount::create([
            'bank_id' => $biat->id,
            'label' => 'Compte courant BIAT',
            'branch' => 'Agence Lac 2',
            // Demo RIB — 20 digits, structurally valid, not a real account.
            'rib' => '08100012345678901234',
            'currency' => 'TND',
            'opening_balance' => 25000.000,
            'opening_date' => now()->subMonths(3)->toDateString(),
            'current_balance' => 25000.000,
            'is_default' => true,
        ]);

        $customer = Customer::where('name', 'Ahmed Ben Ali')->first() ?? Customer::first();
        $other = Customer::where('name', 'Fatma Trabelsi')->first() ?? $customer;
        $supplier = Supplier::first();
        $sale = Sale::orderBy('id')->first();

        // --- cheque received and cleared ------------------------------
        $cleared = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'instrument_reference' => '4512889',
            'amount' => 1200.000,
            'issue_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(25)->toDateString(),
            'place_of_issue' => 'Tunis',
            'customer_id' => $customer?->id,
            'bank_account_id' => $account->id,
            'drawee_bank_id' => Bank::where('code', 'STB')->value('id'),
            'reference_type' => $sale ? 'sale' : 'manual',
            'reference_id' => $sale?->id,
            'notes' => 'Règlement facture — chèque encaissé.',
        ], $admin);
        InstrumentService::deposit($cleared, $admin, $account->id, now()->subDays(24)->toDateString());
        InstrumentService::clear($cleared, $admin, now()->subDays(20)->toDateString(), fees: 2.500);

        // --- cheque that bounced (chèque sans provision) ---------------
        $bounced = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_CHEQUE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'instrument_reference' => '7781203',
            'amount' => 850.000,
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'place_of_issue' => 'Sfax',
            'customer_id' => $other?->id,
            'bank_account_id' => $account->id,
            'drawee_bank_id' => Bank::where('code', 'BNA')->value('id'),
            'notes' => 'Chèque retourné par la banque.',
        ], $admin);
        InstrumentService::deposit($bounced, $admin, $account->id, now()->subDays(9)->toDateString());
        InstrumentService::bounce(
            $bounced,
            $admin,
            reason: 'Provision insuffisante',
            fees: 15.000,
            date: now()->subDays(5)->toDateString(),
            moveToDoubtful: true,
        );

        // --- traite (kembya) still in collection ----------------------
        $traite = InstrumentService::create([
            'kind' => PaymentInstrument::KIND_TRAITE,
            'direction' => PaymentInstrument::DIRECTION_IN,
            'instrument_reference' => 'EFF-2026-0091',
            'amount' => 3400.000,
            'issue_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->addDays(45)->toDateString(),
            'place_of_issue' => 'Sousse',
            'customer_id' => $customer?->id,
            'bank_account_id' => $account->id,
            'notes' => 'Effet à recevoir — échéance à 60 jours.',
        ], $admin);
        InstrumentService::deposit($traite, $admin, $account->id, now()->subDays(3)->toDateString());

        // --- cheque issued to a supplier ------------------------------
        if ($supplier) {
            InstrumentService::create([
                'kind' => PaymentInstrument::KIND_CHEQUE,
                'direction' => PaymentInstrument::DIRECTION_OUT,
                'instrument_reference' => '9900341',
                'amount' => 640.000,
                'issue_date' => now()->subDays(6)->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'supplier_id' => $supplier->id,
                'bank_account_id' => $account->id,
                'notes' => 'Règlement fournisseur.',
            ], $admin);
        }

        // --- installment plan, partly paid ("khlas bel taqsit") -------
        if ($sale) {
            $plan = InstallmentService::createPlan(
                referenceType: 'sale',
                referenceId: $sale->id,
                totalAmount: 2400.000,
                count: 6,
                user: $admin,
                frequency: 'monthly',
                startDate: now()->subMonths(2)->toDateString(),
                downPayment: 400.000,
                notes: 'Vente à crédit — 400 DT d\'avance puis 6 mensualités.',
            );

            // Down payment in cash, first monthly instalment by transfer,
            // second left unpaid so the demo shows an overdue échéance.
            $schedule = $plan->installments;
            PaymentService::settleInstallment(
                installment: $schedule[0],
                amount: (float) $schedule[0]->amount,
                method: 'cash',
                user: $admin,
                date: now()->subMonths(2)->toDateString(),
                reference: 'Avance',
            );
            PaymentService::settleInstallment(
                installment: $schedule[1],
                amount: (float) $schedule[1]->amount,
                method: 'bank_transfer',
                user: $admin,
                bankAccountId: $account->id,
                date: now()->subMonth()->toDateString(),
                reference: 'VIR-0091',
            );
            InstallmentService::markOverdue();
        }

        // --- a small statement to reconcile ---------------------------
        ReconciliationService::import($account, [
            [
                'operation_date' => now()->subDays(20)->toDateString(),
                'value_date' => now()->subDays(20)->toDateString(),
                'label' => 'REMISE CHEQUE 4512889',
                'reference' => '4512889',
                'amount' => 1197.500,     // 1200 less the 2.500 collection fee
            ],
            [
                'operation_date' => now()->subDays(5)->toDateString(),
                'value_date' => now()->subDays(5)->toDateString(),
                'label' => 'RETOUR CHEQUE IMPAYE 7781203',
                'reference' => '7781203',
                'amount' => -850.000,
            ],
            [
                'operation_date' => now()->subMonth()->toDateString(),
                'value_date' => now()->subMonth()->toDateString(),
                'label' => 'VIREMENT RECU CLIENT',
                'reference' => 'VIR-0091',
                'amount' => 333.333,
            ],
            [
                'operation_date' => now()->subDays(2)->toDateString(),
                'value_date' => now()->subDays(2)->toDateString(),
                'label' => 'FRAIS DE TENUE DE COMPTE',
                'reference' => '',
                'amount' => -12.000,
            ],
        ], $admin, 'csv');
    }
}
