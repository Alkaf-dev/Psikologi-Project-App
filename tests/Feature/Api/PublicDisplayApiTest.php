<?php

namespace Tests\Feature\Api;

use Tests\Concerns\InteractsWithSheets;
use Tests\TestCase;

class PublicDisplayApiTest extends TestCase
{
    use InteractsWithSheets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSheets();
    }

    protected function tearDown(): void
    {
        $this->tearDownSheets();
        parent::tearDown();
    }

    public function test_display_endpoints_are_available_without_a_token(): void
    {
        $this->getJson('/api/v1/display/courses?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'prodi_ids']], 'meta', 'links']);

        $this->getJson('/api/v1/display/schedules?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'course_id', 'day', 'start_time']], 'meta']);

        $this->getJson('/api/v1/display/groups?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'course_name']], 'meta']);

        $this->getJson('/api/v1/display/tasks?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'title', 'course_name', 'type', 'status']], 'meta']);

        $this->getJson('/api/v1/display/materials?per_page=100')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_display_groups_include_members_when_requested(): void
    {
        $response = $this->getJson('/api/v1/display/groups?per_page=100&with_members=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'name', 'members' => [['id', 'user_id', 'name', 'npm', 'role']],
                ]],
            ]);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_display_resources_can_be_filtered_by_course(): void
    {
        $courses = $this->getJson('/api/v1/display/courses?per_page=100')->json('data');
        $this->assertNotEmpty($courses);

        $courseId = (string) $courses[0]['id'];

        $schedules = $this->getJson('/api/v1/display/schedules?per_page=100&course_id='.$courseId)->json('data');
        $tasks = $this->getJson('/api/v1/display/tasks?per_page=100&course_id='.$courseId)->json('data');
        $groups = $this->getJson('/api/v1/display/groups?per_page=100&course_id='.$courseId)->json('data');

        foreach ([$schedules, $tasks, $groups] as $items) {
            foreach ($items as $item) {
                $this->assertSame($courseId, (string) $item['course_id']);
            }
        }
    }
}
