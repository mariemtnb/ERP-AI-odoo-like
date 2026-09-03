<?php

namespace App\Services;

use App\Models\Payslip;
use App\Models\PayrollRun;

/**
 * The CNSS social-security declaration: the figures a company files each
 * quarter. Aggregated straight from the payslips of the posted payroll runs in
 * the period — employee and employer contributions per employee, and the totals
 * that go on the declaration.
 */
class CnssDeclarationService
{
    public static function forPeriod(string $from, string $to): array
    {
        $payslips = Payslip::with('employee')
            ->whereHas('run', fn ($q) => $q
                ->whereIn('status', [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_PAID])
                ->whereBetween('period_month', [$from, $to]))
            ->get();

        $rows = [];
        foreach ($payslips as $slip) {
            $key = $slip->employee_id;
            $rows[$key] ??= [
                'employee_id' => $slip->employee_id,
                'employee_name' => $slip->employee?->fullName(),
                'employee_code' => $slip->employee?->code,
                'gross' => 0.0, 'employee_contribution' => 0.0, 'employer_contribution' => 0.0,
            ];
            $rows[$key]['gross'] += (float) $slip->gross_pay;
            $rows[$key]['employee_contribution'] += (float) $slip->cnss_employee;
            $rows[$key]['employer_contribution'] += (float) $slip->cnss_employer;
        }

        $rows = array_map(function ($r) {
            $r['gross'] = round($r['gross'], 3);
            $r['employee_contribution'] = round($r['employee_contribution'], 3);
            $r['employer_contribution'] = round($r['employer_contribution'], 3);
            $r['total_contribution'] = round($r['employee_contribution'] + $r['employer_contribution'], 3);

            return $r;
        }, array_values($rows));
        usort($rows, fn ($a, $b) => strcmp((string) $a['employee_name'], (string) $b['employee_name']));

        $sum = fn (string $k) => round(array_sum(array_column($rows, $k)), 3);
        $employee = $sum('employee_contribution');
        $employer = $sum('employer_contribution');

        return [
            'title' => 'CNSS declaration',
            'period_from' => $from,
            'period_to' => $to,
            'employee_count' => count($rows),
            'rows' => $rows,
            'total_gross' => $sum('gross'),
            'total_employee_contribution' => $employee,
            'total_employer_contribution' => $employer,
            'total_contribution' => round($employee + $employer, 3),
        ];
    }
}
