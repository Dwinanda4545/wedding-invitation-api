<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_guest_relations(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
        ]);

        $created = $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guest-relations', [
                'label' => 'Keluarga Pria',
            ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Keluarga Pria');

        $id = $created->json('data.id');

        $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guest-relations', [
                'label' => 'Keluarga Pria',
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->putJson('/api/events/'.$event->id.'/guest-relations/'.$id, [
                'label' => 'Keluarga Mempelai Pria',
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Keluarga Mempelai Pria');

        $this->actingAs($admin)
            ->getJson('/api/events/'.$event->id.'/guest-relations')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_guest_can_be_assigned_relation_and_delete_nullifies(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
        ]);
        $relation = GuestRelation::query()->create([
            'event_id' => $event->id,
            'label' => 'Teman Kerja',
            'sort_order' => 0,
        ]);

        $guestRes = $this->actingAs($admin)
            ->postJson('/api/events/'.$event->id.'/guests', [
                'name' => 'Budi',
                'guest_type' => 'Regular',
                'guest_relation_id' => $relation->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.relation.label', 'Teman Kerja');

        $guestId = $guestRes->json('data.id');

        $this->actingAs($admin)
            ->deleteJson('/api/events/'.$event->id.'/guest-relations/'.$relation->id)
            ->assertOk();

        $this->assertDatabaseMissing('guest_relations', ['id' => $relation->id]);
        $this->assertDatabaseHas('guests', [
            'id' => $guestId,
            'guest_relation_id' => null,
        ]);
    }

    public function test_guestbook_includes_relation(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
        ]);
        $relation = GuestRelation::query()->create([
            'event_id' => $event->id,
            'label' => 'Sekolah',
            'sort_order' => 0,
        ]);
        Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Sari',
            'guest_type' => 'VIP',
            'guest_relation_id' => $relation->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/events/'.$event->id.'/guestbook')
            ->assertOk()
            ->assertJsonPath('data.0.relation.label', 'Sekolah');
    }

    public function test_relation_from_other_event_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $eventA = Event::query()->create(['name' => 'A', 'slug' => 'a-'.uniqid()]);
        $eventB = Event::query()->create(['name' => 'B', 'slug' => 'b-'.uniqid()]);
        $relation = GuestRelation::query()->create([
            'event_id' => $eventB->id,
            'label' => 'Lain',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/events/'.$eventA->id.'/guests', [
                'name' => 'Budi',
                'guest_type' => 'Regular',
                'guest_relation_id' => $relation->id,
            ])
            ->assertStatus(422);
    }
}
