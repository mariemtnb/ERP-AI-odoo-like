<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransition;
use App\Models\PosOrder;
use App\Models\PosSession;
use App\Services\PosService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class PosController extends Controller
{
    private const WITH = ['lines.product', 'payments', 'customer', 'creator'];

    /** The caller's open till, or null. */
    public function session(Request $request)
    {
        $session = PosService::openSessionFor($request->user());

        return response()->json($session?->toApi());
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $session = PosService::openSession($request->user(), (float) $data['opening_float']);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($session->toApi(), 201);
    }

    public function close(Request $request, PosSession $session)
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            PosService::closeSession($session, (float) $data['counted_cash']);
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($session->fresh()->toApi());
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,cheque'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $session = PosService::openSessionFor($request->user());
        if (! $session) {
            return response()->json(['detail' => 'Open a till before ringing up a sale.'], 409);
        }

        try {
            $order = PosService::checkout(
                $session,
                $data['lines'],
                $data['payments'],
                $data['customer'] ?? null,
                $request->user(),
            );
        } catch (InvalidTransition|InsufficientStock $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }

        return response()->json($order->toApi(), 201);
    }

    public function index(Request $request)
    {
        $query = PosOrder::with(self::WITH)->orderByDesc('created_at')->orderByDesc('id');

        if ($session = $request->query('session')) {
            $query->where('session_id', $session);
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn (PosOrder $o) => $o->toApi())
        );
    }

    public function show(PosOrder $order)
    {
        return response()->json($order->load(self::WITH)->toApi());
    }
}
