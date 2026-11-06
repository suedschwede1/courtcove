<?php

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UiGalleryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function gallery_renders_in_testing(): void
    {
        $this->get(route('ui.gallery'))->assertOk()->assertSee('ds-icon', false);
    }

    #[Test]
    public function gallery_offers_the_visual_reference_hooks_and_an_accessible_dialog(): void
    {
        $this->get('/_ui')->assertOk()
            ->assertSee('data-testid="ui-gallery"', false)
            ->assertSee('data-testid="gallery-dialog-open"', false)
            ->assertSee('aria-labelledby="gallery-dialog-title"', false)
            ->assertSee('ds-btn--primary', false)
            ->assertSee('ds-field__error', false);
    }

    #[Test]
    public function gallery_renders_in_the_dusk_environment(): void
    {
        app()->detectEnvironment(fn () => 'dusk.local');

        $this->get('/_ui')->assertOk();
    }

    #[Test]
    public function gallery_is_hidden_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->get('/_ui')->assertNotFound();
    }
}
