<?php

namespace App\Services;

use App\Exceptions\InvalidTransition;
use App\Models\Sale;
use App\Models\Shipment;
use App\Models\User;

/**
 * Delivery orders. A shipment goes pending → shipped → delivered; shipping
 * captures a tracking number, and it can be cancelled any time before delivery.
 */
class ShippingService
{
    public static function create(
        ?int $saleId,
        ?int $customerId,
        string $carrier,
        string $address,
        User $user,
    ): Shipment {
        // A sale fills in the customer if none was given.
        if ($saleId !== null && $customerId === null) {
            $customerId = Sale::where('id', $saleId)->value('customer_id');
        }

        return Shipment::create([
            'number' => DocumentService::nextNumber('SHP', Shipment::class),
            'sale_id' => $saleId,
            'customer_id' => $customerId,
            'carrier' => $carrier,
            'address' => $address,
            'created_by' => $user->id,
        ]);
    }

    public static function ship(Shipment $shipment, ?string $tracking): Shipment
    {
        if ($shipment->status !== Shipment::STATUS_PENDING) {
            throw new InvalidTransition("Only pending shipments can be shipped (status: {$shipment->status}).");
        }
        $shipment->update([
            'status' => Shipment::STATUS_SHIPPED,
            'tracking_number' => $tracking,
            'shipped_at' => now(),
        ]);

        return $shipment;
    }

    public static function deliver(Shipment $shipment): Shipment
    {
        if ($shipment->status !== Shipment::STATUS_SHIPPED) {
            throw new InvalidTransition("Only shipped orders can be delivered (status: {$shipment->status}).");
        }
        $shipment->update(['status' => Shipment::STATUS_DELIVERED, 'delivered_at' => now()]);

        return $shipment;
    }

    public static function cancel(Shipment $shipment): Shipment
    {
        if (in_array($shipment->status, [Shipment::STATUS_DELIVERED, Shipment::STATUS_CANCELLED], true)) {
            throw new InvalidTransition("A {$shipment->status} shipment cannot be cancelled.");
        }
        $shipment->update(['status' => Shipment::STATUS_CANCELLED]);

        return $shipment;
    }
}
