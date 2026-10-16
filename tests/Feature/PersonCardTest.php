<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_register_a_card(): void
    {
        $this->createElevator();

        $this->postJson('/api/people', [
            'name' => 'João Silva',
            'card_code' => 'JOA-4001',
            'destination_floor' => 2,
            'weight_kg' => 75,
        ])->assertUnauthorized();
    }

    public function test_admin_can_register_and_block_a_card(): void
    {
        $this->createElevator();
        $admin = User::factory()->create();

        $created = $this->actingAs($admin)->postJson('/api/people', [
            'name' => 'João Silva',
            'card_code' => 'joa-4001',
            'destination_floor' => 2,
            'weight_kg' => 75,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.card_code', 'JOA-4001')
            ->assertJsonPath('data.card_status', 'active');

        $id = $created->json('data.id');

        $this->getJson('/api/cards/JOA-4001')
            ->assertOk()
            ->assertJsonPath('data.name', 'João Silva')
            ->assertJsonPath('data.destination_floor', 2);

        $this->actingAs($admin)->patchJson("/api/people/{$id}/status", [
            'card_status' => 'blocked',
        ])->assertOk()
            ->assertJsonPath('data.card_status', 'blocked');
    }

    public function test_admin_panel_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }
}
