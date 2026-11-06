<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use App\Modules\Tournament\Services\GeneratorService;
use App\Modules\Tournament\Services\ResolverService;
use App\Modules\Tournament\Support\ServiceMessages;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * The ZF2 service strings stay byte-identical in the services; the admin UI shows them translated.
 */
class ServiceMessagesTest extends TestCase
{
    #[Test]
    public function lot_required_in_a_group_is_translated(): void
    {
        $this->assertSame(
            'Losentscheidung in Gruppe D für Platz 2 erforderlich.',
            ServiceMessages::resolver('Losentscheidung in Gruppe D fuer Platz 2 erforderlich.'),
        );

        App::setLocale('en');
        $this->assertSame('A lot decision is required in group D for place 2.', ServiceMessages::resolver('Losentscheidung in Gruppe D fuer Platz 2 erforderlich.'));
    }

    #[Test]
    public function lot_required_for_the_seed_order_is_translated(): void
    {
        $this->assertSame(
            'Losentscheidung erforderlich; die vorläufige Setzplatzreihenfolge darf nicht verwendet werden.',
            ServiceMessages::resolver('Losentscheidung erforderlich; die vorlaeufige Setzplatzreihenfolge darf nicht verwendet werden.'),
        );
    }

    #[Test]
    public function skipped_group_reason_is_translated(): void
    {
        $this->assertSame('1 von 3 Plätzen besetzt, 3 Partie(n) noch offen', ServiceMessages::skippedReason('1 von 3 Plaetzen besetzt, 3 Partie(n) noch offen'));
        $this->assertSame('3 von 4 Plätzen besetzt, 3 Partie(n) noch offen', ServiceMessages::skippedReason('3 von 4 Plaetzen besetzt, 3 Partie(n) noch offen'));
    }

    #[Test]
    public function unknown_messages_become_a_generic_note_and_are_logged(): void
    {
        Log::spy();

        $this->assertSame('interner Fehler, Details stehen im Fehlerprotokoll', ServiceMessages::resolver('too many placement levels - broken standings?'));
        $this->assertSame('interner Fehler, Details stehen im Fehlerprotokoll', ServiceMessages::skippedReason('SQLSTATE[HY000]: something'));

        Log::shouldHaveReceived('warning')->with('Untranslated tournament service message', ['message' => 'too many placement levels - broken standings?'])->once();
        Log::shouldHaveReceived('warning')->with('Untranslated tournament service message', ['message' => 'SQLSTATE[HY000]: something'])->once();
    }

    #[Test]
    public function the_patterns_match_the_service_sources(): void
    {
        // If a service text changes, the mapper must change with it.
        $resolver = (string) file_get_contents((string) (new ReflectionClass(ResolverService::class))->getFileName());
        $generator = (string) file_get_contents((string) (new ReflectionClass(GeneratorService::class))->getFileName());

        $this->assertStringContainsString("'Losentscheidung in Gruppe %s fuer Platz %u erforderlich.'", $resolver);
        $this->assertStringContainsString("'Losentscheidung erforderlich; die vorlaeufige Setzplatzreihenfolge darf nicht verwendet werden.'", $resolver);
        $this->assertStringContainsString("'%u von %u Plaetzen besetzt, %u Partie(n) noch offen'", $generator);
    }
}
