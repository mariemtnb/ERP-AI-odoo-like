<?php

namespace App\Services;

use App\Models\PayrollSetting;

/**
 * Tunisian statutory payroll calculation: CNSS (social security), IRPP (income
 * tax) and CSS (solidarity contribution), from a monthly gross salary.
 *
 * The method mirrors how a Tunisian payslip is actually built:
 *
 *   1. CNSS employee     = gross × cnss_employee_rate   (withheld)
 *      CNSS employer     = gross × cnss_employer_rate   (employer cost, shown
 *                                                        for reporting, not
 *                                                        withheld from pay)
 *   2. Professional-expense abatement = (gross − CNSS) × rate, annually capped
 *   3. Monthly taxable   = gross − CNSS − abatement
 *   4. Annualise (×12), subtract family relief, apply the progressive scale,
 *      then bring the tax back to a month (÷12). CSS is a flat rate on the same
 *      annual taxable base.
 *
 * Every rate, cap, relief and bracket comes from PayrollSetting, so updating
 * the figures when the finance law changes needs no code change.
 */
class TunisianPayrollTax
{
    /**
     * @return array{cnss_employee: float, cnss_employer: float, taxable_base: float, irpp: float, css: float}
     */
    public static function compute(float $grossMonthly, bool $headOfFamily = false, int $children = 0): array
    {
        $s = PayrollSetting::current();
        $gross = max(0.0, $grossMonthly);

        $cnssEmployee = $gross * $s->cnss_employee_rate;
        $cnssEmployer = $gross * $s->cnss_employer_rate;

        $afterCnss = $gross - $cnssEmployee;

        // Professional-expense abatement: a fraction of pay-after-CNSS, but the
        // annual total is capped — so cap it per month at cap / 12.
        $abatement = min($afterCnss * $s->expense_abatement_rate, $s->expense_abatement_cap / 12);
        $monthlyTaxable = max(0.0, $afterCnss - $abatement);

        // IRPP is an annual, progressive tax; withholding is a twelfth of it.
        $annualTaxable = $monthlyTaxable * 12;

        $childCount = max(0, min($children, (int) $s->max_children));
        $relief = ($headOfFamily ? $s->head_of_family_deduction : 0.0) + $childCount * $s->child_deduction;
        $annualNet = max(0.0, $annualTaxable - $relief);

        $irppAnnual = self::progressive($annualNet, $s->irpp_brackets ?? []);
        $irppMonthly = $irppAnnual / 12;

        $cssMonthly = ($annualNet * $s->css_rate) / 12;

        return [
            'cnss_employee' => round($cnssEmployee, 3),
            'cnss_employer' => round($cnssEmployer, 3),
            'taxable_base' => round($monthlyTaxable, 3),
            'irpp' => round($irppMonthly, 3),
            'css' => round($cssMonthly, 3),
        ];
    }

    /**
     * Progressive tax over marginal brackets. Each bracket is
     * { upto: number|null, rate: fraction }, ordered ascending; a null `upto`
     * is the open-ended top band.
     */
    private static function progressive(float $amount, array $brackets): float
    {
        $tax = 0.0;
        $floor = 0.0;

        foreach ($brackets as $b) {
            $ceiling = $b['upto'] ?? INF;
            if ($amount <= $floor) {
                break;
            }
            $slice = min($amount, $ceiling) - $floor;
            if ($slice > 0) {
                $tax += $slice * (float) $b['rate'];
            }
            $floor = $ceiling;
        }

        return $tax;
    }
}
