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
    public function gallery_is_hidden_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->get('/_ui')->assertNotFound();
    }
}
