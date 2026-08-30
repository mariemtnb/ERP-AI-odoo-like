<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Currency;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['email' => 'a@t.t', 'password' => 'x', 'role' => 'admin']);
        // TND base is seeded by the migration; add two foreign currencies.
        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimals' => 2]);
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2]);
    }

    public function test_base_currency_rate_is_one_and_identity_convert(): void
    {
        $this->assertEquals('TND', CurrencyService::base()->code);
        $this->assertEquals(1.0, CurrencyService::rateFor('TND'));
        $this->assertEquals(100.0, CurrencyService::convert(100, 'TND', 'TND'));
    }

    public function test_convert_base_and_foreign(): void
    {
        CurrencyService::setRate('EUR', 3.4, '2026-08-01', $this->admin); // 1 EUR = 3.4 TND

        $this->assertEqualsWithDelta(340.0, CurrencyService::convert(100, 'EUR', 'TND'), 0.001);
        $this->assertEqualsWithDelta(100.0, CurrencyService::convert(340, 'TND', 'EUR'), 0.001);
    }

    public function test_convert_between_two_foreign_currencies_through_base(): void
    {
        CurrencyService::setRate('EUR', 3.4, '2026-08-01', $this->admin);
        CurrencyService::setRate('USD', 3.1, '2026-08-01', $this->admin);

        // 100 EUR -> USD = 100 * 3.4 / 3.1
        $this->assertEqualsWithDelta(100 * 3.4 / 3.1, CurrencyService::convert(100, 'EUR', 'USD'), 0.001);
    }

    public function test_latest_rate_on_or_before_date_is_used(): void
    {
        CurrencyService::setRate('EUR', 3.0, '2026-08-01', $this->admin);
        CurrencyService::setRate('EUR', 3.5, '2026-08-20', $this->admin);

        $this->assertEqualsWithDelta(3.0, CurrencyService::rateFor('EUR', '2026-08-10'), 0.0001);
        $this->assertEqualsWithDelta(3.5, CurrencyService::rateFor('EUR', '2026-08-25'), 0.0001);
    }

    public function test_setting_a_rate_on_base_currency_is_rejected(): void
    {
        $this->expectException(InvalidTransition::class);
        CurrencyService::setRate('TND', 2, null, $this->admin);
    }

    public function test_converting_without_a_rate_fails(): void
    {
        $this->expectException(InvalidTransition::class);
        CurrencyService::convert(100, 'EUR', 'TND'); // no rate set
    }

    public function test_http_admin_sets_rate_and_converts_but_employee_cannot_configure(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/currencies/EUR/rates', ['rate' => 3.4, 'as_of' => '2026-08-01'])
            ->assertCreated()
            ->assertJsonPath('rate', '3.40000000');

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/currencies/convert?amount=50&from=EUR&to=TND')
            ->assertOk()
            ->assertJsonPath('result', 170);

        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')
            ->postJson('/api/v1/currencies/EUR/rates', ['rate' => 9])
            ->assertForbidden();
        // but employees can still read/convert
        $this->actingAs($employee, 'api')->getJson('/api/v1/currencies')->assertOk();
    }
}
