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
            '/\n\nhttps:\/\/undangan\.example\.com\/invitation\/abc123token\n\n/',
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

        $this->assertSame("\u{200E}", mb_substr($message, 0, 1));
        $this->assertStringContainsString($arabic, $message);
        $this->assertStringContainsString('Halo Budi', $message);
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
        $this->assertStringStartsWith('بسم الله', $message);
    }
}
