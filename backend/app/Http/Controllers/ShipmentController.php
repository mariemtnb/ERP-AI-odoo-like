<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransition;
use App\Models\Shipment;
use App\Services\ShippingService;
use App\Support\DrfPagination;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Shipment::with(['sale', 'customer'])->orderByDesc('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(DrfPagination::paginate($query, $request, fn (Shipment $s) => $s->toApi()));
    }

    public function show(Shipment $shipment)
    {
        return response()->json($shipment->load(['sale', 'customer'])->toApi());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sale' => ['nullable', 'integer', 'exists:sales,id'],
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
            'carrier' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $shipment = ShippingService::create(
            $data['sale'] ?? null, $data['customer'] ?? null,
            $data['carrier'], $data['address'] ?? '', $request->user()
        );

        return response()->json($shipment->load(['sale', 'customer'])->toApi(), 201);
    }

    public function ship(Request $request, Shipment $shipment)
    {
        $data = $request->validate(['tracking_number' => ['nullable', 'string', 'max:80']]);

        return $this->guard(fn () => ShippingService::ship($shipment, $data['tracking_number'] ?? null)
            ->load(['sale', 'customer'])->toApi());
    }

    public function deliver(Shipment $shipment)
    {
        return $this->guard(fn () => ShippingService::deliver($shipment)->load(['sale', 'customer'])->toApi());
    }

    public function cancel(Shipment $shipment)
    {
        return $this->guard(fn () => ShippingService::cancel($shipment)->load(['sale', 'customer'])->toApi());
    }

    private function guard(callable $fn)
    {
        try {
            return response()->json($fn());
        } catch (InvalidTransition $e) {
            return response()->json(['detail' => $e->getMessage()], 409);
        }
    }
}
