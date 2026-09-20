<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class GuestExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_guests_xlsx_with_invitation_links(): void
    {
        config(['app.frontend_url' => 'https://wedding.example.com']);

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

        $guest = Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Budi Santoso',
            'phone_number' => '081234567890',
            'guest_type' => 'VIP',
            'guest_relation_id' => $relation->id,
            'is_attended' => true,
            'scanned_at' => now()->startOfMinute(),
        ]);

        Guest::query()->create([
            'event_id' => $event->id,
            'name' => 'Andi',
            'phone_number' => null,
            'guest_type' => 'Regular',
            'is_attended' => false,
        ])->delete();

        $response = $this->actingAs($admin)
            ->get('/api/events/'.$event->id.'/guests/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            '.xlsx',
            (string) $response->headers->get('content-disposition')
        );

        $tmp = tempnam(sys_get_temp_dir(), 'gx');
        file_put_contents($tmp, $response->streamedContent());

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($tmp);

        $this->assertNotFalse($sheet);
        $this->assertNotFalse($shared);
        $this->assertStringContainsString('name', $shared);
        $this->assertStringContainsString('invitation_url', $shared);
        $this->assertStringContainsString('Budi Santoso', $shared);
        $this->assertStringContainsString('Teman Kerja', $shared);
        $this->assertStringContainsString(
            'https://wedding.example.com/invitation/'.$guest->secret_token,
            $shared
        );
        $this->assertStringContainsString('Ya', $shared);
        $this->assertStringNotContainsString('Andi', $shared);
    }

    public function test_export_empty_event_returns_header_only_xlsx(): void
    {
        config(['app.frontend_url' => 'https://wedding.example.com']);
        $admin = User::factory()->admin()->create();
        $event = Event::query()->create([
            'name' => 'Empty',
            'slug' => 'e-'.uniqid(),
        ]);

        $response = $this->actingAs($admin)
            ->get('/api/events/'.$event->id.'/guests/export');

        $response->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'gx');
        file_put_contents($tmp, $response->streamedContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($tmp);

        $this->assertNotFalse($shared);
        $this->assertStringContainsString('name', $shared);
        $this->assertStringContainsString('is_attended', $shared);
    }

    public function test_guest_export_requires_auth(): void
    {
        $event = Event::query()->create([
            'name' => 'Wedding',
            'slug' => 'w-'.uniqid(),
        ]);

        $this->getJson('/api/events/'.$event->id.'/guests/export')
            ->assertUnauthorized();
    }
}
