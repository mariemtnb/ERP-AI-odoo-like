<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\FixedAsset;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    private function asset(float $cost = 12000, float $salvage = 0, int $life = 12): FixedAsset
    {
        return FixedAsset::create([
            'name' => 'Van', 'acquisition_date' => '2026-01-01',
            'acquisition_cost' => $cost, 'salvage_value' => $salvage,
            'useful_life_months' => $life, 'created_by' => $this->manager->id,
        ]);
    }

    public function test_monthly_charge_is_straight_line(): void
    {
        $a = $this->asset(12000, 0, 12);
        $this->assertEquals(1000.0, AssetService::monthlyCharge($a)); // 12000/12

        $b = $this->asset(10000, 1000, 12);
        $this->assertEquals(750.0, AssetService::monthlyCharge($b)); // (10000-1000)/12
    }

    public function test_depreciating_increments_accumulated_and_lowers_book_value(): void
    {
        $a = $this->asset(12000, 0, 12);
        AssetService::depreciate($a, '2026-02-15', $this->manager);
        $a->refresh();

        $this->assertEquals('1000.00', $a->accumulated_depreciation);
        $this->assertEquals(11000.0, $a->bookValue());
        $this->assertDatabaseHas('depreciation_entries', ['fixed_asset_id' => $a->id, 'amount' => 1000]);
    }

    public function test_cannot_depreciate_same_month_twice(): void
    {
        $a = $this->asset();
        AssetService::depreciate($a, '2026-02-01', $this->manager);
        $this->expectException(InvalidTransition::class);
        AssetService::depreciate($a, '2026-02-20', $this->manager);
    }

    public function test_final_charge_is_trimmed_to_salvage_and_stops(): void
    {
        // cost 100, salvage 10, life 4 -> base 90 -> 22.50/month; last month trims
        $a = $this->asset(100, 10, 4);
        for ($m = 1; $m <= 4; $m++) {
            AssetService::depreciate($a, sprintf('2026-%02d-01', $m + 1), $this->manager);
        }
        $a->refresh();
        $this->assertEquals(90.0, (float) $a->accumulated_depreciation);
        $this->assertEquals(10.0, $a->bookValue()); // settles at salvage
        $this->assertTrue($a->isFullyDepreciated());

        $this->expectException(InvalidTransition::class);
        AssetService::depreciate($a, '2026-07-01', $this->manager);
    }

    public function test_schedule_covers_remaining_life(): void
    {
        $a = $this->asset(12000, 0, 12);
        $this->assertCount(12, AssetService::schedule($a));

        AssetService::depreciate($a, '2026-02-01', $this->manager);
        $this->assertCount(11, AssetService::schedule($a->refresh()));
    }

    public function test_dispose_stops_depreciation(): void
    {
        $a = $this->asset();
        AssetService::dispose($a, '2026-06-01');
        $this->assertEquals(FixedAsset::STATUS_DISPOSED, $a->refresh()->status);

        $this->expectException(InvalidTransition::class);
        AssetService::depreciate($a, '2026-07-01', $this->manager);
    }

    public function test_http_create_and_depreciate_with_rbac(): void
    {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/assets', [
                'name' => 'Laptop', 'acquisition_date' => '2026-01-01',
                'acquisition_cost' => 2400, 'useful_life_months' => 24,
            ])->assertCreated()->assertJsonPath('book_value', '2400.00');

        $asset = FixedAsset::latest('id')->first();
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/assets/{$asset->id}/depreciate", ['period' => '2026-02-01'])
            ->assertCreated()->assertJsonPath('accumulated_depreciation', '100.00');

        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')
            ->postJson("/api/v1/assets/{$asset->id}/depreciate", [])
            ->assertForbidden();
    }
}
