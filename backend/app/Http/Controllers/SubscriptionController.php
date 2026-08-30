<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::with('customer')->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (Subscription $s) => $s->toApi()));
    }

    public function show(Subscription $subscription)
    {
        return response()->json($subscription->toApi() + [
            'invoices' => $subscription->invoices()->orderBy('period_start')->get()->map(fn ($i) => $i->toApi())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer' => ['required', 'integer', 'exists:customers,id'],
            'description' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'interval' => ['required', 'string', 'in:monthly,quarterly,yearly'],
            'start_date' => ['required', 'date'],
        ]);

        try {
            $sub = SubscriptionService::create(
                $data['customer'], $data['description'], (float) $data['amount'],
                $data['interval'], $data['start_date'], $request->user()
            );
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($sub->toApi(), 201);
    }

    public function setStatus(Request $request, Subscription $subscription, string $status)
    {
        try {
            SubscriptionService::setStatus($subscription, $status);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($subscription->fresh()->toApi());
    }

    public function runBilling(Request $request)
    {
        $data = $request->validate(['as_of' => ['nullable', 'date']]);
        $invoices = SubscriptionService::runBilling($data['as_of'] ?? null);

        return response()->json([
            'generated' => count($invoices),
            'total_amount' => number_format(array_sum(array_map(fn ($i) => (float) $i->amount, $invoices)), 2, '.', ''),
        ]);
    }
}
