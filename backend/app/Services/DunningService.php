<?php

namespace App\Services;

use App\Mail\DunningMail;
use App\Models\CompanyProfile;
use App\Models\DunningLevel;
use App\Models\DunningLog;
use App\Models\OnlinePayment;
use App\Models\Payment;
use App\Models\RecordMessage;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Accounts-receivable dunning: escalating reminders on overdue invoices.
 *
 * Reuses what already ships — the customer email (a reminder Mailable), the
 * portal link, and the record chatter, plus the payment facts to know what is
 * still outstanding. A run picks, for each overdue invoice, the highest reminder
 * level it has reached but not yet been sent, so it escalates without ever
 * re-sending the same level.
 */
class DunningService
{
    /** What is still owed on a sale: its total less inbound and online payments. */
    public static function outstanding(Sale $sale): float
    {
        $paid = (float) Payment::where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->where('direction', Payment::DIRECTION_IN)
            ->sum('amount');
        $paid += (float) OnlinePayment::where('sale_id', $sale->id)
            ->where('status', OnlinePayment::PAID)
            ->sum('amount');

        return round((float) $sale->total_amount - $paid, 3);
    }

    /** Due date: the invoice date plus the company's payment terms and grace. */
    public static function dueDate(Sale $sale, CompanyProfile $profile): ?Carbon
    {
        $base = $sale->invoice?->issued_at ?? $sale->sale_date;
        if (! $base) {
            return null;
        }
        $days = (int) $profile->default_payment_terms_days + (int) $profile->late_payment_grace_days;

        return Carbon::parse($base)->addDays($days);
    }

    /**
     * Overdue invoices with a reminder due but not yet sent.
     *
     * @return array<int,array{sale:Sale,level:DunningLevel,days_overdue:int,outstanding:float}>
     */
    public static function candidates(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : Carbon::today();
        $profile = CompanyProfile::current();
        $levels = DunningLevel::where('is_active', true)->orderByDesc('days_overdue')->get();
        if ($levels->isEmpty()) {
            return [];
        }

        $out = [];
        $sales = Sale::where('status', Sale::STATUS_CONFIRMED)
            ->has('invoice')->with(['invoice', 'customer'])->get();

        foreach ($sales as $sale) {
            $outstanding = self::outstanding($sale);
            if ($outstanding <= 0) {
                continue;
            }
            $due = self::dueDate($sale, $profile);
            if (! $due || ! $due->lt($asOf)) {
                continue;
            }
            $daysOverdue = (int) $due->diffInDays($asOf);

            $sent = DunningLog::where('sale_id', $sale->id)->pluck('level')->all();
            $level = $levels->first(fn ($l) => $l->days_overdue <= $daysOverdue && ! in_array($l->level, $sent, true));
            if (! $level) {
                continue;
            }

            $out[] = ['sale' => $sale, 'level' => $level, 'days_overdue' => $daysOverdue, 'outstanding' => $outstanding];
        }

        return $out;
    }

    /**
     * Send every due reminder: email the customer (best-effort), log it to the
     * sale's chatter, and record the send so it never repeats.
     *
     * @return array{sent:int, emailed:int, logs:array<int,array<string,mixed>>}
     */
    public static function run(User $user, ?string $asOf = null): array
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $emailed = 0;
        $logs = [];

        foreach (self::candidates($asOf) as $c) {
            /** @var Sale $sale */
            $sale = $c['sale'];
            $level = $c['level'];
            $to = $sale->customer?->email ?? '';

            $didEmail = false;
            if ($to !== '') {
                $portalUrl = "{$frontend}/portal/sales/{$sale->ensureToken()}";
                try {
                    Mail::to($to)->send(new DunningMail($sale, $level, $c['outstanding'], $c['days_overdue'], $portalUrl));
                    $didEmail = true;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            if ($didEmail) {
                $emailed++;
            }

            RecordMessage::create([
                'subject_type' => 'sales',
                'subject_id' => $sale->id,
                'user_id' => $user->id,
                'body' => sprintf(
                    'Dunning level %d (%s) sent - %d days overdue, %s TND outstanding.',
                    $level->level, $level->name, $c['days_overdue'], number_format($c['outstanding'], 3)
                ),
            ]);

            $log = DunningLog::firstOrCreate(
                ['sale_id' => $sale->id, 'level' => $level->level],
                [
                    'days_overdue' => $c['days_overdue'],
                    'outstanding' => $c['outstanding'],
                    'emailed_to' => $to,
                    'emailed' => $didEmail,
                    'sent_at' => now(),
                ]
            );
            $logs[] = $log->toApi();
        }

        return ['sent' => count($logs), 'emailed' => $emailed, 'logs' => $logs];
    }
}
