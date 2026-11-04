<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TournamentBase;

use App\Modules\TournamentBase\Support\CourtNames;
use App\Modules\TournamentBase\Support\PlayerName;
use App\Modules\TournamentBase\Support\WhatsApp;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SupportHelpersTest extends TestCase
{
    #[Test]
    public function whatsapp_normalizes_austrian_numbers(): void
    {
        $this->assertSame('436641234567', WhatsApp::normalize('0664 123 45 67'));
        $this->assertSame('436641234567', WhatsApp::normalize('+43 664/1234567'));
        $this->assertSame('436641234567', WhatsApp::normalize('0043 664 1234567'));
        $this->assertNull(WhatsApp::normalize('12345678'));
        $this->assertSame('123456789', WhatsApp::normalize('123456789'));
        $this->assertNull(WhatsApp::normalize(''));
        $this->assertNull(WhatsApp::normalize(null));
    }

    #[Test]
    public function whatsapp_url_encodes_text(): void
    {
        $this->assertSame('https://wa.me/436641234567?text=Hallo%20Max%21', WhatsApp::url('0664 1234567', 'Hallo Max!'));
        $this->assertSame('https://wa.me/436641234567', WhatsApp::url('06641234567'));
        $this->assertSame('', WhatsApp::url('abc'));
    }

    #[Test]
    public function court_labels(): void
    {
        config()->set('tournament-base.courts', [1 => 'Alpha', 2 => 'Beta', 3 => 'Gamma']);

        app()->setLocale('de');
        $this->assertSame('Platz 1 – Alpha', CourtNames::label(1));
        $this->assertSame('Platz 3 – Gamma', CourtNames::label('3'));
        $this->assertSame('Platz 4', CourtNames::label(4));
        $this->assertSame('Beta', CourtNames::short(2));
        $this->assertSame('', CourtNames::label(null));

        app()->setLocale('en');
        $this->assertSame('Court 1 – Alpha', CourtNames::label(1));
        $this->assertSame('Court 4', CourtNames::label(4));
    }

    #[Test]
    public function court_labels_without_configured_names(): void
    {
        config()->set('tournament-base.courts', []);
        app()->setLocale('de');

        $this->assertSame('Platz 1', CourtNames::label(1));
        $this->assertSame('1', CourtNames::short(1));
    }

    #[Test]
    public function first_name(): void
    {
        $this->assertSame('Anna', PlayerName::first('  Anna  Beispiel '));
        $this->assertSame('', PlayerName::first(''));
    }
}
