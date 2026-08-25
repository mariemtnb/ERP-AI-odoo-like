<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Semantic account key → chart-of-accounts code.
 *
 * This is the seam that keeps accounting rules configurable: services post to
 * `cheques_receivable`, never to a literal code, so re-pointing the mapping
 * (or switching to the Tunisian chart wholesale) is a settings change rather
 * than a code change.
 */
class AccountMapping extends Model
{
    /** Keys the posting services rely on. */
    public const KEYS = [
        'receivable', 'payable', 'cash', 'bank', 'inventory', 'revenue', 'cogs',
        'cheques_receivable', 'cheques_in_collection', 'cheques_payable',
        'notes_receivable', 'notes_in_collection', 'notes_payable',
        'customer_advances', 'supplier_advances', 'doubtful_receivable',
        'vat_collected', 'vat_deductible', 'stamp_duty', 'bank_fees', 'suspense',
        'salary_expense', 'salaries_payable', 'employee_advances', 'payroll_deductions',
    ];

    protected $fillable = ['key', 'account_code', 'label', 'description'];

    protected $attributes = ['label' => '', 'description' => ''];

    public function toApi(): array
    {
        $account = Account::where('code', $this->account_code)->first();

        return [
            'id' => $this->id,
            'key' => $this->key,
            'account_code' => $this->account_code,
            'account_name' => $account?->name,
            'account_exists' => $account !== null,
            'label' => $this->label,
            'description' => $this->description,
        ];
    }
}
