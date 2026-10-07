<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameConfigurationDoor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfiguratorEntryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function job(string $number = 'EM-1'): BusinessJob
    {
        return BusinessJob::firstOrCreate(['job_number' => $number], ['job_name' => "Job {$number}", 'status' => 'active']);
    }

    private function config(string|array $tags, string $status = 'draft', ?BusinessJob $job = null, array $extra = []): DoorFrameConfiguration
    {
        $job ??= $this->job();
        $config = DoorFrameConfiguration::create($extra + [
            'business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => $status,
            'quantity' => count((array) $tags),
        ]);
        foreach ((array) $tags as $tag) {
            DoorFrameConfigurationDoor::create(['configuration_id' => $config->id, 'door_tag' => $tag]);
        }

        return $config;
    }

    private function tagsOf(int $id): array
    {
        return DoorFrameConfigurationDoor::where('configuration_id', $id)->pluck('door_tag')->all();
    }

    // ── one door tag at setup ────────────────────────────────────────────

    public function test_a_new_entry_takes_exactly_one_door_tag(): void
    {
        $this->actingAsRole('admin');
        $job = $this->job();
        $payload = fn (array $tags) => ['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'door_tags' => $tags];

        $this->postJson('/api/v1/door-frame-configurations', $payload(['D1', 'D2']))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'exactly one door tag'));
        $this->postJson('/api/v1/door-frame-configurations', $payload(['D1, D2']))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'single door tag'));
        $this->postJson('/api/v1/door-frame-configurations', $payload([]))->assertStatus(422);

        $id = $this->postJson('/api/v1/door-frame-configurations', $payload(['D1']))->assertCreated()->json('configuration.id');
        $this->assertSame(['D1'], $this->tagsOf($id));
        $this->assertSame(1, DoorFrameConfiguration::find($id)->quantity);

        // The reason a tag is rejected reaches the user (the page shows only `message`).
        $this->postJson('/api/v1/door-frame-configurations', $payload(['D1']))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'D1') && str_contains($m, 'already belong'));
    }

    public function test_each_duplicate_copy_takes_one_door_tag(): void
    {
        $this->actingAsRole('admin');
        $source = $this->config('D1');

        $this->postJson("/api/v1/door-frame-configurations/{$source->id}/duplicate", ['duplicates' => [['door_tags' => ['D2', 'D3']]]])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'exactly one door tag'));
        $this->postJson("/api/v1/door-frame-configurations/{$source->id}/duplicate", ['duplicates' => [['door_tags' => ['D2, D3']]]])
            ->assertStatus(422);
    }

    // ── edit details ─────────────────────────────────────────────────────

    public function test_notes_tag_and_job_can_be_edited_on_a_draft(): void
    {
        $this->actingAsRole('manager');
        $config = $this->config('D1');
        $other = $this->job('EM-2');

        $this->putJson("/api/v1/door-frame-configurations/{$config->id}", ['notes' => 'Check glazing', 'door_tag' => 'D1A'])->assertOk();
        $this->assertSame(['D1A'], $this->tagsOf($config->id));
        $this->assertSame('Check glazing', $config->fresh()->notes);

        $this->putJson("/api/v1/door-frame-configurations/{$config->id}", ['business_job_id' => $other->id])->assertOk();
        $this->assertSame($other->id, $config->fresh()->business_job_id);

        // Unchanged values are a no-op, not a conflict with itself.
        $this->putJson("/api/v1/door-frame-configurations/{$config->id}", ['door_tag' => 'D1A', 'business_job_id' => $other->id])->assertOk();
    }

    public function test_edit_rejects_duplicate_commas_and_clashes_with_clear_messages(): void
    {
        $this->actingAsRole('admin');
        $a = $this->config('D1');
        $this->config('D2');
        $otherJob = $this->job('EM-2');
        $this->config('D1', 'draft', $otherJob);

        $this->putJson("/api/v1/door-frame-configurations/{$a->id}", ['door_tag' => 'D2'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'D2') && str_contains($m, 'already belong'));
        $this->putJson("/api/v1/door-frame-configurations/{$a->id}", ['door_tag' => 'X1, X2'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'single door tag'));
        // D1 is already used on the other job, so it cannot move there.
        $this->putJson("/api/v1/door-frame-configurations/{$a->id}", ['business_job_id' => $otherJob->id])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'D1'));
        $this->assertSame(['D1'], $this->tagsOf($a->id));
    }

    public function test_edit_locks_follow_the_configurations_status(): void
    {
        $this->actingAsRole('admin');
        $otherJob = $this->job('EM-2');

        $reserved = $this->config('R1', 'reserved');
        $this->putJson("/api/v1/door-frame-configurations/{$reserved->id}", ['door_tag' => 'R1A', 'notes' => 'ok'])->assertOk();
        $this->putJson("/api/v1/door-frame-configurations/{$reserved->id}", ['business_job_id' => $otherJob->id])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'draft'));

        $onHold = $this->config('H1', 'on_hold');
        $this->putJson("/api/v1/door-frame-configurations/{$onHold->id}", ['notes' => 'ok'])->assertOk();
        $this->putJson("/api/v1/door-frame-configurations/{$onHold->id}", ['door_tag' => 'H2'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'draft or reserved'));

        $released = $this->config('L1', 'released');
        $this->putJson("/api/v1/door-frame-configurations/{$released->id}", ['notes' => 'nope'])->assertStatus(422);

        $linked = $this->config('K1', 'draft', null, ['duplicate_group_id' => 'grp-1']);
        $this->config('K2', 'draft', null, ['duplicate_group_id' => 'grp-1']);
        $this->putJson("/api/v1/door-frame-configurations/{$linked->id}", ['business_job_id' => $otherJob->id])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'linked'));
    }

    public function test_legacy_entries_with_several_tags_cannot_be_renamed_but_notes_still_save(): void
    {
        $this->actingAsRole('admin');
        $legacy = $this->config(['L1', 'L2']);

        $this->putJson("/api/v1/door-frame-configurations/{$legacy->id}", ['door_tag' => 'L9'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'several door tags'));
        $this->putJson("/api/v1/door-frame-configurations/{$legacy->id}", ['notes' => 'old one'])->assertOk();
        $this->assertSame(['L1', 'L2'], $this->tagsOf($legacy->id));
    }

    // ── delete ───────────────────────────────────────────────────────────

    public function test_only_admins_hold_the_delete_permission(): void
    {
        $permission = Permission::where('name', 'configurator.delete')->first();
        $this->assertNotNull($permission, 'the migration creates configurator.delete');
        $this->assertTrue(Role::where('name', 'admin')->first()->permissions->contains('name', 'configurator.delete'));
        foreach (['manager', 'office_staff', 'fabricator', 'viewer'] as $role) {
            $granted = Role::where('name', $role)->first()?->permissions->contains('name', 'configurator.delete');
            $this->assertFalse((bool) $granted, "{$role} must not be able to delete configurations");
        }

        $config = $this->config('D1');
        $this->actingAsRole('manager');
        $this->deleteJson("/api/v1/door-frame-configurations/{$config->id}")->assertForbidden();
        $this->assertNotNull(DoorFrameConfiguration::find($config->id));
    }

    public function test_admin_can_delete_a_draft_and_its_tag_becomes_reusable(): void
    {
        $this->actingAsRole('admin');
        $config = $this->config('D1');

        $this->deleteJson("/api/v1/door-frame-configurations/{$config->id}")->assertOk()->assertJsonPath('message', 'Configuration deleted');

        $this->assertNull(DoorFrameConfiguration::find($config->id));
        $this->assertNotNull(DoorFrameConfiguration::withTrashed()->find($config->id), 'soft delete keeps the record recoverable');
        $this->getJson("/api/v1/door-frame-configurations/{$config->id}")->assertNotFound();
        $this->assertCount(0, $this->getJson('/api/v1/door-frame-configurations?business_job_id='.$config->business_job_id)->json('configurations'));

        $this->postJson('/api/v1/door-frame-configurations', [
            'business_job_id' => $config->business_job_id, 'job_scope' => 'door_and_frame', 'door_tags' => ['D1'],
        ])->assertCreated();
    }

    public function test_a_reserved_configuration_can_be_deleted(): void
    {
        $this->actingAsRole('admin');
        $config = $this->config('D1', 'reserved');

        $this->deleteJson("/api/v1/door-frame-configurations/{$config->id}")->assertOk();
        $this->assertNull(DoorFrameConfiguration::find($config->id));
    }

    public function test_released_and_in_production_configurations_must_be_unreleased_first(): void
    {
        $this->actingAsRole('admin');
        foreach (['released', 'in_progress', 'completed', 'on_hold'] as $status) {
            $config = $this->config("T-{$status}", $status);
            $this->deleteJson("/api/v1/door-frame-configurations/{$config->id}")
                ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Un-release'));
            $this->assertNotNull(DoorFrameConfiguration::find($config->id), "{$status} must survive");
        }
    }

    public function test_deleting_down_to_one_linked_copy_unlinks_it(): void
    {
        $this->actingAsRole('admin');
        $a = $this->config('D1', 'draft', null, ['duplicate_group_id' => 'grp-9']);
        $b = $this->config('D2', 'draft', null, ['duplicate_group_id' => 'grp-9']);
        $c = $this->config('D3', 'draft', null, ['duplicate_group_id' => 'grp-9']);

        $this->deleteJson("/api/v1/door-frame-configurations/{$c->id}")->assertOk();
        $this->assertSame('grp-9', $a->fresh()->duplicate_group_id, 'two copies are still a group');

        $this->deleteJson("/api/v1/door-frame-configurations/{$b->id}")->assertOk();
        $this->assertNull($a->fresh()->duplicate_group_id, 'a group of one is no group');
    }

    public function test_the_entry_builder_page_offers_edit_and_admin_delete_controls(): void
    {
        $html = $this->get('/config')->assertOk()->getContent();

        $this->assertStringContainsString('id="fb-edit-modal"', $html);
        $this->assertStringContainsString('onclick="fbOpenEditModal()" data-permission="configurator.edit"', $html);
        $this->assertStringContainsString('onclick="fbDeleteConfig()" data-permission="configurator.delete"', $html);
        $this->assertStringNotContainsString('placeholder="D1, D2"', $html, 'the setup form no longer invites several tags');
    }
}
