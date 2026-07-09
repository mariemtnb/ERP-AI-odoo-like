<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;

class InsufficientStock extends Exception
{
    public function __construct(public readonly Product $product, float $requested)
    {
        parent::__construct(sprintf(
            'Insufficient stock for %s: requested %s, available %s',
            $product->sku,
            rtrim(rtrim(number_format($requested, 3, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format((float) $product->quantity_in_stock, 3, '.', ''), '0'), '.'),
        ));
    }
}
