<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Members;

use App\Models\User;
use App\Modules\Members\Models\ClubRole;
use App\Modules\Members\Models\MailCategory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailConfigRestyleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['status' => 'admin']);
    }

    #[Test]
    public function category_delete_buttons_submit_the_hidden_confirm_forms(): void
    {
        $a = MailCategory::create(['name' => 'Alpha']);
        $b = MailCategory::create(['name' => 'Beta']);

        $html = (string) $this->actingAs($this->admin())->get(route('members.mail.config'))->assertOk()->getContent();

        foreach ([$a, $b] as $category) {
            $id = 'delete-mail-category-'.$category->id;
            $this->assertMatchesRegularExpression('/<button[^>]*form="'.$id.'"/', $html, "button for {$id}");
            $this->assertMatchesRegularExpression('/<form id="'.$id.'"[^>]*class="hidden"/', $html, "hidden {$id}");
            $this->assertMatchesRegularExpression('/<form id="'.$id.'"[^>]*onsubmit="return confirm\(/', $html, "confirm {$id}");
            $this->assertStringContainsString('aria-label="'.$category->name.'"', $html);
        }
        $this->assertStringNotContainsString('display:none', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('ui-card', $html);
        $this->assertStringNotContainsString('ui-btn', $html);
    }

    #[Test]
    public function sender_fields_are_labelled_and_required(): void
    {
        $html = (string) $this->actingAs($this->admin())->get(route('members.mail.config'))->assertOk()->getContent();

        foreach (['mail-from-name' => 'from_name', 'mail-from-address' => 'from_address'] as $id => $name) {
            $this->assertStringContainsString('<label class="ds-field__label" for="'.$id.'">', $html);
            $this->assertMatchesRegularExpression('/<input[^>]*id="'.$id.'"[^>]*\srequired/', $html);
            $this->assertMatchesRegularExpression('/<input[^>]*name="'.$name.'"/', $html);
        }
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        return ['create' => ['create'], 'edit' => ['edit']];
    }

    #[Test]
    #[DataProvider('pages')]
    public function function_form_fields_are_labelled_and_keep_their_names(string $which): void
    {
        MailCategory::create(['name' => 'Vorstand']);
        $url = $which === 'create'
            ? route('members.functions.create')
            : route('members.functions.edit', ClubRole::create(['name' => 'Obmann']));

        $html = (string) $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('<label class="ds-field__label" for="fn-name">', $html);
        $this->assertStringContainsString('<label class="ds-field__label" for="fn-tasks">', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="fn-name"[^>]*name="name"|<input[^>]*name="name"[^>]*id="fn-name"/', $html);
        $this->assertMatchesRegularExpression('/<label[^>]*>\s*<input[^>]*id="fn-is-board"[^>]*name="is_board"/', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="fn-tasks"[^>]*name="tasks"|<textarea[^>]*name="tasks"[^>]*id="fn-tasks"/', $html);
        $this->assertStringContainsString('name="mail_category_ids[]"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('ui-card', $html);
        $this->assertStringNotContainsString('ui-btn', $html);
    }
}
