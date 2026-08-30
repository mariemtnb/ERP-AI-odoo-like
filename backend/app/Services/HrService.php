<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;

/**
 * HR workflows: attendance clock in/out, leave requests with an approval
 * workflow and an annual balance, and employee expense claims.
 */
class HrService
{
    /** Default annual-leave allowance in days, per employee, per year. */
    public const ANNUAL_ALLOWANCE = 30;

    // ---------- attendance ----------

    public static function clockIn(Employee $employee, ?string $date, ?string $time): AttendanceRecord
    {
        $date ??= now()->toDateString();
        $record = AttendanceRecord::firstOrNew(['employee_id' => $employee->id, 'work_date' => $date]);
        if ($record->check_in) {
            throw new InvalidTransition("Already clocked in for {$date}.");
        }
        $record->check_in = $time ?? now()->format('H:i:s');
        $record->created_at ??= now();
        $record->save();

        return $record;
    }

    public static function clockOut(Employee $employee, ?string $date, ?string $time): AttendanceRecord
    {
        $date ??= now()->toDateString();
        $record = AttendanceRecord::where('employee_id', $employee->id)->where('work_date', $date)->first();
        if (! $record || ! $record->check_in) {
            throw new InvalidTransition("No clock-in recorded for {$date}.");
        }
        if ($record->check_out) {
            throw new InvalidTransition("Already clocked out for {$date}.");
        }
        $out = $time ?? now()->format('H:i:s');
        $record->check_out = $out;
        $record->hours = round(
            Carbon::parse($record->check_in)->floatDiffInHours(Carbon::parse($out)),
            2
        );
        $record->save();

        return $record;
    }

    // ---------- leave ----------

    private static function inclusiveDays(string $start, string $end): float
    {
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        if ($e->lt($s)) {
            throw new InvalidTransition('End date is before start date.');
        }

        return $s->diffInDays($e) + 1;
    }

    public static function requestLeave(Employee $employee, string $type, string $start, string $end, string $reason): LeaveRequest
    {
        if (! in_array($type, LeaveRequest::TYPES, true)) {
            throw new InvalidTransition("Unknown leave type: {$type}.");
        }
        $days = self::inclusiveDays($start, $end);

        return LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'reason' => $reason,
        ]);
    }

    public static function decideLeave(LeaveRequest $request, bool $approve, User $decider): LeaveRequest
    {
        if ($request->status !== LeaveRequest::STATUS_PENDING) {
            throw new InvalidTransition("Only pending requests can be decided (status: {$request->status}).");
        }
        $request->update([
            'status' => $approve ? LeaveRequest::STATUS_APPROVED : LeaveRequest::STATUS_REJECTED,
            'decided_by' => $decider->id,
            'decided_at' => now(),
        ]);

        return $request;
    }

    /** Annual-leave balance for a year: allowance minus approved annual days. */
    public static function leaveBalance(Employee $employee, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        $used = (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('type', 'annual')
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereYear('start_date', $year)
            ->sum('days');

        return [
            'year' => $year,
            'allowance' => self::ANNUAL_ALLOWANCE,
            'used' => $used,
            'remaining' => round(self::ANNUAL_ALLOWANCE - $used, 1),
        ];
    }

    // ---------- expense claims ----------

    public static function submitClaim(Employee $employee, string $date, string $category, float $amount, string $description): ExpenseClaim
    {
        if ($amount <= 0) {
            throw new InvalidTransition('Claim amount must be positive.');
        }

        return ExpenseClaim::create([
            'employee_id' => $employee->id,
            'claim_date' => $date,
            'category' => $category,
            'amount' => round($amount, 2),
            'description' => $description,
        ]);
    }

    public static function decideClaim(ExpenseClaim $claim, bool $approve, User $decider): ExpenseClaim
    {
        if ($claim->status !== ExpenseClaim::STATUS_PENDING) {
            throw new InvalidTransition("Only pending claims can be decided (status: {$claim->status}).");
        }
        $claim->update([
            'status' => $approve ? ExpenseClaim::STATUS_APPROVED : ExpenseClaim::STATUS_REJECTED,
            'decided_by' => $decider->id,
            'decided_at' => now(),
        ]);

        return $claim;
    }

    public static function reimburseClaim(ExpenseClaim $claim): ExpenseClaim
    {
        if ($claim->status !== ExpenseClaim::STATUS_APPROVED) {
            throw new InvalidTransition("Only approved claims can be reimbursed (status: {$claim->status}).");
        }
        $claim->update(['status' => ExpenseClaim::STATUS_REIMBURSED]);

        return $claim;
    }
}
