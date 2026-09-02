<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Services\FxService;
use PHPUnit\Framework\TestCase;

/** Realized FX gain/loss arithmetic, independent of the database. */
class FxServiceTest extends TestCase
{
    public function test_inbound_gain_when_the_currency_strengthens(): void
    {
        // 100 EUR booked at 3.0, banked at 3.2: received 20 base more than booked.
        $fx = FxService::realized(Payment::DIRECTION_IN, 100, 3.0, 3.2);
        $this->assertEqualsWithDelta(300, $fx['base_booked'], 0.001);
        $this->assertEqualsWithDelta(320, $fx['base_settled'], 0.001);
        $this->assertEqualsWithDelta(20, $fx['gain'], 0.001);
    }

    public function test_inbound_loss_when_the_currency_weakens(): void
    {
        $fx = FxService::realized(Payment::DIRECTION_IN, 100, 3.2, 3.0);
        $this->assertEqualsWithDelta(-20, $fx['gain'], 0.001);
    }

    public function test_outbound_sense_is_flipped(): void
    {
        // Paying a 100 EUR bill booked at 3.0 but now costing 3.2 base is a loss.
        $out = FxService::realized(Payment::DIRECTION_OUT, 100, 3.0, 3.2);
        $this->assertEqualsWithDelta(-20, $out['gain'], 0.001);

        // Booked at 3.2, settled cheaper at 3.0 is a gain.
        $out2 = FxService::realized(Payment::DIRECTION_OUT, 100, 3.2, 3.0);
        $this->assertEqualsWithDelta(20, $out2['gain'], 0.001);
    }

    public function test_no_movement_when_the_rate_is_unchanged(): void
    {
        $fx = FxService::realized(Payment::DIRECTION_IN, 250, 3.1, 3.1);
        $this->assertEqualsWithDelta(0, $fx['gain'], 0.0001);
    }
}
