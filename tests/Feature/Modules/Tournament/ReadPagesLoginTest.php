<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tournament;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Tournament\Concerns\BuildsChampionship;
use Tests\TestCase;

/**
 * Every championship read page needs a login, as in ZF2 (LoginRedirectTrait): guests go to the login with
 * the module message and come back afterwards; a real session login reaches a non-overview page.
 */
class ReadPagesLoginTest extends TestCase
{
    use BuildsChampionship;
    use LazilyRefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return [
            'groups' => ['/tournament/groups'],
            'groups/B' => ['/tournament/groups/B'],
            'schedule' => ['/tournament/schedule'],
            'schedule/offen' => ['/tournament/schedule/offen'],
            'bracket' => ['/tournament/bracket'],
            'bracket/KO' => ['/tournament/bracket/KO'],
            'players' => ['/tournament/players'],
            'mine' => ['/tournament/mine'],
        ];
    }

    #[Test]
    #[DataProvider('pages')]
    public function guests_are_sent_to_the_login(string $path): void
    {
        $this->championship();

        $this->get($path)
            ->assertRedirect(route('login', ['redirect_to' => $path]))
            ->assertSessionHas('error', 'Für den Turnierbereich musst du angemeldet sein.');
    }

    #[Test]
    public function a_real_login_reaches_the_schedule(): void
    {
        $this->championship();
        $this->member(['email' => 'spieler02@example.test', 'pw' => bcrypt('geheim-123')]);

        $this->post('/login', ['email' => 'spieler02@example.test', 'password' => 'geheim-123']);

        $this->get('/tournament/schedule')->assertOk()->assertSee('Spielplan');
    }
}
