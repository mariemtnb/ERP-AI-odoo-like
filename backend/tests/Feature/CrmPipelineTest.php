<?php

namespace Tests\Feature;

use App\Models\CrmStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The CRM opportunity pipeline: stages, moving leads and the weighted forecast. */
class CrmPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    private function stage(string $name): CrmStage
    {
        return CrmStage::where('name', $name)->firstOrFail();
    }

    private function lead(float $expected = 1000): Lead
    {
        return Lead::create(['name' => 'Acme deal', 'created_by' => $this->manager->id, 'expected_revenue' => $expected]);
    }

    public function test_moving_to_a_stage_syncs_status_and_probability(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/leads/{$lead->id}/stage",
            ['stage_id' => $this->stage('Qualified')->id])
            ->assertOk()
            ->assertJsonPath('status', 'qualified')
            ->assertJsonPath('probability', 30)
            ->assertJsonPath('weighted_value', '300.000'); // 1000 × 30%
    }

    public function test_winning_and_losing_a_lead(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/leads/{$lead->id}/stage",
            ['stage_id' => $this->stage('Won')->id])->assertOk()->assertJsonPath('status', 'won');

        $lead2 = $this->lead();
        // Lost stage needs a reason.
        $this->actingAs($this->manager, 'api')->postJson("/api/v1/leads/{$lead2->id}/stage",
            ['stage_id' => $this->stage('Lost')->id])->assertStatus(422);

        $this->actingAs($this->manager, 'api')->postJson("/api/v1/leads/{$lead2->id}/stage",
            ['stage_id' => $this->stage('Lost')->id, 'lost_reason' => 'Chose a competitor'])
            ->assertOk()->assertJsonPath('status', 'lost')->assertJsonPath('lost_reason', 'Chose a competitor');
    }

    public function test_a_lead_probability_override_wins_over_the_stage_default(): void
    {
        $lead = $this->lead(1000);
        $lead->update(['stage_id' => $this->stage('Qualified')->id, 'probability' => 50]);

        $this->assertEqualsWithDelta(500, $lead->refresh()->weightedValue(), 0.001);
    }

    public function test_the_pipeline_groups_open_leads_and_forecasts_weighted(): void
    {
        // Two open leads, plus one won and one lost that must be excluded.
        $this->lead(1000)->update(['stage_id' => $this->stage('Qualified')->id]);   // weighted 300
        $this->lead(2000)->update(['stage_id' => $this->stage('Proposition')->id]); // weighted 1200 (60%)
        $this->lead(5000)->update(['stage_id' => $this->stage('Won')->id]);
        $this->lead(9000)->update(['stage_id' => $this->stage('Lost')->id]);

        $res = $this->actingAs($this->manager, 'api')->getJson('/api/v1/crm/pipeline')->assertOk();

        $this->assertSame(2, $res->json('open_count'));
        $this->assertEqualsWithDelta(3000, $res->json('total_expected'), 0.01);   // 1000 + 2000
        $this->assertEqualsWithDelta(1500, $res->json('total_weighted'), 0.01);   // 300 + 1200
    }

    public function test_stages_are_listed_in_order(): void
    {
        $res = $this->actingAs($this->manager, 'api')->getJson('/api/v1/crm/stages')->assertOk();
        $names = array_column($res->json(), 'name');
        $this->assertSame(['New', 'Qualified', 'Proposition', 'Negotiation', 'Won', 'Lost'], $names);
    }

    public function test_a_lead_can_be_created_with_a_stage_and_value(): void
    {
        $res = $this->actingAs($this->manager, 'api')->postJson('/api/v1/leads', [
            'name' => 'Big deal', 'stage_id' => $this->stage('Proposition')->id, 'expected_revenue' => 4000,
        ])->assertStatus(201)->assertJsonPath('probability', 60)->assertJsonPath('weighted_value', '2400.000');
        $this->assertNotNull($res->json('id'));
    }
}
