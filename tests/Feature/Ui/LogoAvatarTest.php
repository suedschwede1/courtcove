<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\NormalizesHtml;
use Tests\TestCase;

class LogoAvatarTest extends TestCase
{
    use NormalizesHtml;

    private string $logoFile = '';

    protected function tearDown(): void
    {
        if ($this->logoFile !== '' && file_exists($this->logoFile)) {
            unlink($this->logoFile);
        }
        parent::tearDown();
    }

    private function render(string $template): string
    {
        return $this->html((string) $this->blade($template));
    }

    #[Test]
    public function logo_falls_back_to_initials(): void
    {
        View::share('bookingLogoPath', '');

        $this->assertSame(
            '<span class="ds-logo ds-logo--44" role="img" aria-label="Example Club"><span class="ds-logo__initials" aria-hidden="true">EC</span></span>',
            $this->render('<x-ui.logo-mark name="Example Club" />'),
        );
    }

    #[Test]
    public function logo_uses_the_image_when_the_file_exists(): void
    {
        $this->logoFile = public_path('imgs-client/test-logo.png');
        if (! is_dir(dirname($this->logoFile))) {
            mkdir(dirname($this->logoFile), 0777, true);
        }
        file_put_contents($this->logoFile, 'x');
        View::share('bookingLogoPath', 'imgs-client/test-logo.png');

        $html = $this->render('<x-ui.logo-mark name="Example Club" />');

        $this->assertStringContainsString('<img src="'.asset('imgs-client/test-logo.png').'" alt="" class="ds-logo__img">', $html);
        $this->assertStringNotContainsString('ds-logo__initials', $html);
    }

    #[Test]
    public function logo_size_maps_to_a_class(): void
    {
        View::share('bookingLogoPath', '');

        $this->assertStringContainsString('ds-logo--36', $this->render('<x-ui.logo-mark name="Example Club" :size="36" />'));
    }

    #[Test]
    public function avatar_is_a_link_with_href(): void
    {
        $this->assertSame(
            '<a href="https://example.test/me" class="ds-avatar" aria-label="Mein Konto – Pat Example">PE</a>',
            $this->render('<x-ui.avatar name="Pat Example" href="https://example.test/me" label="Mein Konto – Pat Example" />'),
        );
    }

    #[Test]
    public function avatar_without_href_is_decorative(): void
    {
        $this->assertSame(
            '<span class="ds-avatar" aria-hidden="true">PE</span>',
            $this->render('<x-ui.avatar name="Pat Example" />'),
        );
    }
}
