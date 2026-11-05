<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\MailCategory;
use App\Modules\Members\Models\MailTemplate;
use App\Modules\Members\Models\Member;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Markup and CSS contracts behind the phone/desktop layout fixes (overflow, squeezed fields, covered titles). */
class MembersLayoutFixesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    private function builtCss(): string
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        return (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
    }

    #[Test]
    public function the_mail_page_grid_has_a_shrinkable_single_column_below_xl(): void
    {
        MailTemplate::create(['name' => 'Beitragserinnerung', 'subject' => 'Mitgliedsbeitrag', 'body' => 'Text']);

        $html = (string) $this->actingAs($this->admin())->get('/members/mail')->assertOk()->getContent();

        $this->assertStringContainsString('class="mbr-mail-grid"', $html);
        $this->assertStringNotContainsString('font-bold break-words', $html);
        $this->assertMatchesRegularExpression('/class="mbr-template [^"]*"/', $html);
        $this->assertStringContainsString('class="mbr-template__actions"', $html);
        $this->assertMatchesRegularExpression('/\.mbr-mail-grid\{[^}]*grid-template-columns:minmax\(0,1fr\)/', $this->builtCss());
    }

    #[Test]
    public function mail_category_rows_wrap_instead_of_squeezing_the_name_field(): void
    {
        MailCategory::create(['name' => 'Newsletter']);

        $html = (string) $this->actingAs($this->admin())->get('/members/mail/config')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="mbr-category-row"'));
        $this->assertMatchesRegularExpression('/\.mbr-category-row\{[^}]*flex-wrap:wrap/', $this->builtCss());
    }

    #[Test]
    public function the_dues_card_header_stacks_on_phones(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($this->admin())->get("/members/{$member->id}/edit")
            ->assertOk()
            ->assertSee('mbr-dues-card', false);
        $this->assertMatchesRegularExpression('/\.mbr-dues-card>\.ds-card__header>\.ds-card__actions\{[^}]*grid-(column:1|area:auto\/1)/', $this->builtCss());
    }
}
