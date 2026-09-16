<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\WhatsappDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_whatsapp_devices(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/whatsapp-devices', [
                'name' => 'WA 1',
                'provider_device_id' => 'dev-1',
                'phone_label' => '08111',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'WA 1')
            ->assertJsonPath('data.is_active', true);

        $id = WhatsappDevice::query()->value('id');

        $this->actingAs($admin)
            ->getJson('/api/whatsapp-devices')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->patchJson('/api/whatsapp-devices/'.$id, [
                'is_active' => false,
                'name' => 'WA 1 Off',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name', 'WA 1 Off');

        $this->actingAs($admin)
            ->deleteJson('/api/whatsapp-devices/'.$id)
            ->assertOk();

        $this->assertDatabaseMissing('whatsapp_devices', ['id' => $id]);
    }

    public function test_delete_rejects_when_device_in_use(): void
    {
        $admin = User::factory()->admin()->create();
        $device = WhatsappDevice::query()->create([
            'name' => 'In Use',
            'provider_device_id' => 'x',
            'is_active' => true,
        ]);
        Event::query()->create([
            'name' => 'E',
            'slug' => 'e-'.uniqid(),
            'whatsapp_device_id' => $device->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/whatsapp-devices/'.$device->id)
            ->assertStatus(422);
    }

    public function test_event_can_set_default_whatsapp_device(): void
    {
        $admin = User::factory()->admin()->create();
        $device = WhatsappDevice::query()->create([
            'name' => 'Main',
            'provider_device_id' => 'p1',
            'is_active' => true,
        ]);
        $event = Event::query()->create(['name' => 'E', 'slug' => 'e-'.uniqid()]);

        $this->actingAs($admin)
            ->patchJson('/api/events/'.$event->id, [
                'whatsapp_device_id' => $device->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_device_id', $device->id)
            ->assertJsonPath('data.whatsapp_device.name', 'Main');
    }

    public function test_panitia_cannot_manage_devices(): void
    {
        $panitia = User::factory()->panitia()->create();

        $this->actingAs($panitia)
            ->getJson('/api/whatsapp-devices')
            ->assertForbidden();
    }
}
