<?php

namespace Tests\Feature;

use App\Models\DevItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DevItemTest extends TestCase
{
    use RefreshDatabase;

    private function actAs(string $role): User
    {
        $u = User::factory()->create(['role' => $role, 'is_active' => true]);
        Sanctum::actingAs($u, ['*']);

        return $u;
    }

    public function test_non_admin_is_forbidden_everywhere(): void
    {
        $this->actAs('manager');
        $item = DevItem::create(['type' => 'bug', 'title' => 'x']);

        $this->getJson('/api/v1/dev-items')->assertForbidden();
        $this->postJson('/api/v1/dev-items', ['type' => 'bug', 'title' => 'y'])->assertForbidden();
        $this->putJson("/api/v1/dev-items/{$item->id}", ['status' => 'resolved'])->assertForbidden();
        $this->deleteJson("/api/v1/dev-items/{$item->id}")->assertForbidden();
        $this->get('/api/v1/dev-items/export')->assertForbidden();
    }

    public function test_admin_can_create_filter_update_and_export(): void
    {
        $admin = $this->actAs('admin');

        $this->postJson('/api/v1/dev-items', [
            'type' => 'bug', 'title' => 'Crash on save', 'description' => 'boom',
            'console_output' => 'TypeError: x', 'priority' => 'high', 'page_url' => '/jobs',
        ])->assertCreated()->assertJsonPath('created_by', $admin->id);
        $this->postJson('/api/v1/dev-items', ['type' => 'feature', 'title' => 'Dark logo'])->assertCreated();
        $this->postJson('/api/v1/dev-items', ['type' => 'nope', 'title' => 'bad'])->assertStatus(422);

        $this->getJson('/api/v1/dev-items?type=bug')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/dev-items?search=crash')->assertJsonCount(1, 'data');

        $bug = DevItem::where('type', 'bug')->first();
        $this->putJson("/api/v1/dev-items/{$bug->id}", ['status' => 'resolved', 'resolution_notes' => 'fixed'])
            ->assertOk();
        $this->assertNotNull($bug->fresh()->resolved_at);

        // export defaults to active items only (the resolved bug is excluded)
        $md = $this->get('/api/v1/dev-items/export')->assertOk()->getContent();
        $this->assertStringContainsString('Dark logo', $md);
        $this->assertStringNotContainsString('Crash on save', $md);
    }
}
