<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single row of payroll configuration: CNSS / CSS rates, the professional
 * expense abatement, family relief and the progressive IRPP scale. Everything
 * the statutory calculation needs, so no rate is baked into code.
 */
class PayrollSetting extends Model
{
    protected $fillable = [
        'cnss_employee_rate', 'cnss_employer_rate', 'css_rate',
        'expense_abatement_rate', 'expense_abatement_cap',
        'head_of_family_deduction', 'child_deduction', 'max_children', 'irpp_brackets',
    ];

    protected function casts(): array
    {
        return [
            'cnss_employee_rate' => 'float', 'cnss_employer_rate' => 'float', 'css_rate' => 'float',
            'expense_abatement_rate' => 'float', 'expense_abatement_cap' => 'float',
            'head_of_family_deduction' => 'float', 'child_deduction' => 'float',
            'max_children' => 'integer', 'irpp_brackets' => 'array',
        ];
    }

    /** The one configuration row, created with sane defaults if absent. */
    public static function current(): self
    {
        return static::query()->firstOr(fn () => static::create([
            'irpp_brackets' => [
                ['upto' => 5000, 'rate' => 0.00], ['upto' => 10000, 'rate' => 0.15],
                ['upto' => 20000, 'rate' => 0.25], ['upto' => 30000, 'rate' => 0.30],
                ['upto' => 40000, 'rate' => 0.33], ['upto' => 50000, 'rate' => 0.36],
                ['upto' => 70000, 'rate' => 0.38], ['upto' => null, 'rate' => 0.40],
            ],
        ]));
    }

    public function toApi(): array
    {
        return [
            'cnss_employee_rate' => (float) $this->cnss_employee_rate,
            'cnss_employer_rate' => (float) $this->cnss_employer_rate,
            'css_rate' => (float) $this->css_rate,
            'expense_abatement_rate' => (float) $this->expense_abatement_rate,
            'expense_abatement_cap' => (float) $this->expense_abatement_cap,
            'head_of_family_deduction' => (float) $this->head_of_family_deduction,
            'child_deduction' => (float) $this->child_deduction,
            'max_children' => (int) $this->max_children,
            'irpp_brackets' => $this->irpp_brackets,
        ];
    }
}
