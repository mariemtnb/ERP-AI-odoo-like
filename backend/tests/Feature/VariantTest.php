<?php

namespace Tests\Feature;

use App\Models\FeatureFlag;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Product attributes and variant generation. */
class VariantTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private array $size;
    private array $colour;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureFlag::flush();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
        $this->size = $this->attribute('Size', ['S', 'M']);
        $this->colour = $this->attribute('Colour', ['Red', 'Blue']);
    }

    /** Create an attribute with values through the API; return value ids by label. */
    private function attribute(string $name, array $values): array
    {
        $a = $this->actingAs($this->manager, 'api')->postJson('/api/v1/product-attributes', ['name' => $name])
            ->assertStatus(201)->json('id');
        $ids = [];
        foreach ($values as $v) {
            $ids[$v] = $this->actingAs($this->manager, 'api')
                ->postJson("/api/v1/product-attributes/{$a}/values", ['value' => $v])
                ->assertStatus(201)->json('id');
        }

        return $ids;
    }

    private function template(): Product
    {
        return Product::create(['sku' => 'TSHIRT', 'name' => 'T-Shirt', 'cost_price' => 5, 'sale_price' => 15, 'unit' => 'unit']);
    }

    public function test_generating_the_cartesian_product_of_two_attributes(): void
    {
        $template = $this->template();

        $res = $this->actingAs($this->manager, 'api')->postJson("/api/v1/products/{$template->id}/variants", [
            'value_groups' => [array_values($this->size), array_values($this->colour)],
        ])->assertStatus(201)->assertJsonPath('created', 4); // 2 sizes × 2 colours

        $this->assertSame(4, $template->variants()->count());
        $variant = $template->variants()->with('attributeValues')->first();
        $this->assertSame($template->id, $variant->template_id);
        $this->assertSame(2, $variant->attributeValues()->count()); // one size + one colour
        $this->assertStringContainsString('T-Shirt (', $variant->name);
    }

    public function test_regenerating_does_not_duplicate_existing_variants(): void
    {
        $template = $this->template();
        $groups = [array_values($this->size), array_values($this->colour)];

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/products/{$template->id}/variants",
            ['value_groups' => $groups])->assertJsonPath('created', 4);
        // Add a new size value and regenerate — only the new combinations appear.
        $l = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/product-attributes/'.ProductAttribute::where('name', 'Size')->first()->id.'/values', ['value' => 'L'])
            ->json('id');

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/products/{$template->id}/variants", [
            'value_groups' => [[$this->size['S'], $this->size['M'], $l], array_values($this->colour)],
        ])->assertStatus(201)->assertJsonPath('created', 2); // only L×Red and L×Blue are new

        $this->assertSame(6, $template->variants()->count());
    }

    public function test_a_single_attribute_generates_one_variant_per_value(): void
    {
        $template = $this->template();
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/products/{$template->id}/variants", [
            'value_groups' => [array_values($this->colour)],
        ])->assertStatus(201)->assertJsonPath('created', 2);
    }

    public function test_an_unknown_value_is_rejected(): void
    {
        $template = $this->template();
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/products/{$template->id}/variants", [
            'value_groups' => [[999999]],
        ])->assertStatus(422);
    }

    public function test_the_module_is_absent_when_the_flag_is_off(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'variants'], ['enabled' => false]);
        FeatureFlag::flush();
        $this->actingAs($this->manager, 'api')->getJson('/api/v1/product-attributes')->assertStatus(404);
    }

    public function test_an_ordinary_employee_cannot_generate(): void
    {
        $template = $this->template();
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')->postJson("/api/v1/products/{$template->id}/variants", [
            'value_groups' => [array_values($this->colour)],
        ])->assertStatus(403);
    }
}
