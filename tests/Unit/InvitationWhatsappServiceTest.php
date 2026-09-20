<?php

namespace Tests\Unit;

use App\Models\Guest;
use App\Services\FlowkirimService;
use App\Services\InvitationWhatsappService;
use Tests\TestCase;

class InvitationWhatsappServiceTest extends TestCase
{
    public function test_normalize_phone_handles_common_formats(): void
    {
        $service = new InvitationWhatsappService(app(FlowkirimService::class));

        $this->assertSame('6281234567890', $service->normalizePhone('0812-3456-7890'));
        $this->assertSame('6281234567890', $service->normalizePhone('+62 812 3456 7890'));
        $this->assertSame('6281234567890', $service->normalizePhone('81234567890'));
        $this->assertNull($service->normalizePhone(null));
        $this->assertNull($service->normalizePhone(''));
        $this->assertNull($service->normalizePhone('123'));
    }

    public function test_render_message_puts_link_on_own_line_with_scheme(): void
    {
        config(['app.frontend_url' => 'undangan.example.com']);

        $guest = new Guest([
            'name' => 'Budi',
            'secret_token' => 'abc123token',
        ]);

        $service = new InvitationWhatsappService(app(FlowkirimService::class));
        $message = $service->renderMessage(
            'Halo {nama}, undangan: {link} terima kasih',
            $guest,
        );

        $this->assertStringContainsString('https://undangan.example.com/invitation/abc123token', $message);
        $this->assertMatchesRegularExpression(
            '/\n\n\x{200E}?https:\/\/undangan\.example\.com\/invitation\/abc123token\n\n/u',
            $message,
        );
    }

    public function test_render_message_prefixes_lrm_when_force_ltr_enabled(): void
    {
        config([
            'app.frontend_url' => 'https://undangan.example.com',
            'flowkirim.force_ltr' => true,
        ]);

        $guest = new Guest([
            'name' => 'Budi',
            'secret_token' => 'abc123token',
        ]);

        $service = new InvitationWhatsappService(app(FlowkirimService::class));
        $arabic = 'بسم الله الرحمن الرحيم';
        $message = $service->renderMessage(
            $arabic."\n\nHalo {nama}\n{link}",
            $guest,
        );

        $this->assertSame("\u{202A}", mb_substr($message, 0, 1));
        $this->assertSame("\u{202C}", mb_substr($message, -1));
        $this->assertStringContainsString("\u{200E}".$arabic, $message);
        $this->assertStringContainsString("\u{200E}Halo Budi", $message);
        $this->assertStringContainsString($arabic, $message);
        $this->assertStringContainsString('Halo Budi', $message);
    }

    public function test_apply_text_direction_is_idempotent(): void
    {
        config(['flowkirim.force_ltr' => true]);

        $service = new InvitationWhatsappService(app(FlowkirimService::class));
        $once = $service->applyTextDirection("بسم الله\nHalo");
        $twice = $service->applyTextDirection($once);

        $this->assertSame($once, $twice);
        $this->assertSame(1, substr_count($twice, "\u{202A}"));
        $this->assertSame(1, substr_count($twice, "\u{202C}"));
    }

    public function test_render_message_skips_lrm_when_force_ltr_disabled(): void
    {
        config([
            'app.frontend_url' => 'https://undangan.example.com',
            'flowkirim.force_ltr' => false,
        ]);

        $guest = new Guest([
            'name' => 'Budi',
            'secret_token' => 'abc123token',
        ]);

        $service = new InvitationWhatsappService(app(FlowkirimService::class));
        $message = $service->renderMessage(
            "بسم الله\nHalo {nama} {link}",
            $guest,
        );

        $this->assertFalse(str_starts_with($message, "\u{200E}"));
        $this->assertFalse(str_starts_with($message, "\u{202A}"));
        $this->assertStringStartsWith('بسم الله', $message);
    }
}
