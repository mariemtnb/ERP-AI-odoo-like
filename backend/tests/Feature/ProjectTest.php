<?php

namespace Tests\Feature;

use App\Exceptions\InvalidTransition;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::create(['email' => 'm@t.t', 'password' => 'x', 'role' => 'manager']);
    }

    private function project(float $budget = 40): Project
    {
        return Project::create(['name' => 'Website', 'budget_hours' => $budget, 'created_by' => $this->manager->id]);
    }

    public function test_logging_time_rolls_up_into_the_summary(): void
    {
        $p = $this->project(40);
        $task = ProjectTask::create(['project_id' => $p->id, 'name' => 'Design']);

        ProjectService::logTime($p, $task->id, $this->manager, '2026-08-20', 6, true, 'wireframes');
        ProjectService::logTime($p, $task->id, $this->manager, '2026-08-21', 4, false, 'revisions');
        ProjectService::logTime($p, null, $this->manager, '2026-08-22', 2, true, 'call');

        $s = ProjectService::summary($p);
        $this->assertEquals(12.0, $s['logged_hours']);
        $this->assertEquals(8.0, $s['billable_hours']);   // 6 + 2
        $this->assertEquals(4.0, $s['non_billable_hours']);
        $this->assertEquals(28.0, $s['remaining_hours']); // 40 - 12
        $this->assertFalse($s['over_budget']);
        $this->assertEquals(10.0, collect($s['by_task'])->firstWhere('task', $task->id)['logged_hours']);
    }

    public function test_over_budget_flag(): void
    {
        $p = $this->project(5);
        ProjectService::logTime($p, null, $this->manager, '2026-08-20', 8, true, '');
        $s = ProjectService::summary($p);
        $this->assertTrue($s['over_budget']);
        $this->assertEquals(-3.0, $s['remaining_hours']);
    }

    public function test_cannot_log_on_closed_project(): void
    {
        $p = $this->project();
        ProjectService::close($p);
        $this->expectException(InvalidTransition::class);
        ProjectService::logTime($p, null, $this->manager, '2026-08-20', 2, true, '');
    }

    public function test_task_must_belong_to_project(): void
    {
        $p1 = $this->project();
        $p2 = $this->project();
        $foreign = ProjectTask::create(['project_id' => $p2->id, 'name' => 'X']);

        $this->expectException(InvalidTransition::class);
        ProjectService::logTime($p1, $foreign->id, $this->manager, '2026-08-20', 1, true, '');
    }

    public function test_http_log_time_and_rbac(): void
    {
        $p = $this->project(20);

        // any authenticated user can log their own time
        $employee = User::create(['email' => 'e@t.t', 'password' => 'x', 'role' => 'employee']);
        $this->actingAs($employee, 'api')
            ->postJson("/api/v1/projects/{$p->id}/timesheets", ['work_date' => '2026-08-20', 'hours' => 3])
            ->assertCreated()->assertJsonPath('hours', '3.00');

        // but only managers create projects
        $this->actingAs($employee, 'api')
            ->postJson('/api/v1/projects', ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/projects/{$p->id}/summary")
            ->assertOk()->assertJsonPath('logged_hours', 3);
    }
}
