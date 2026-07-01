<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\QuoteGroups;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteGroupsTest extends TestCase
{
    private const TEST_LOCALE = 'quotegroupstest';

    private string $overrideFile;

    private string $namedOverrideFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideFile = __DIR__.'/../../../lang/'.self::TEST_LOCALE.'/booking/quotes.local.php';
        $this->namedOverrideFile = __DIR__.'/../../../lang/'.self::TEST_LOCALE.'/booking/quotes_named.local.php';
    }

    protected function tearDown(): void
    {
        foreach ([$this->overrideFile, $this->namedOverrideFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (is_dir(dirname($this->overrideFile))) {
            rmdir(dirname($this->overrideFile));
            rmdir(dirname($this->overrideFile, 2));
        }

        parent::tearDown();
    }

    #[Test]
    public function returns_default_when_no_override_file_exists(): void
    {
        $default = ['Default quote one', 'Default quote two'];

        $this->assertSame($default, QuoteGroups::baseQuotes(self::TEST_LOCALE, $default));
    }

    #[Test]
    public function returns_override_contents_when_file_exists_and_non_empty(): void
    {
        $override = ['Override quote one', 'Override quote two'];
        $this->writeOverrideFile($this->overrideFile, $override);

        $this->assertSame($override, QuoteGroups::baseQuotes(self::TEST_LOCALE, ['Default quote']));
    }

    #[Test]
    public function falls_back_to_default_when_override_file_is_empty(): void
    {
        $this->writeOverrideFile($this->overrideFile, []);

        $default = ['Default quote'];
        $this->assertSame($default, QuoteGroups::baseQuotes(self::TEST_LOCALE, $default));
    }

    #[Test]
    public function named_quotes_returns_default_when_no_override_file_exists(): void
    {
        $default = ['Default named quote'];

        $this->assertSame($default, QuoteGroups::namedQuotes(self::TEST_LOCALE, $default));
    }

    #[Test]
    public function named_quotes_returns_override_contents_when_file_exists_and_non_empty(): void
    {
        $override = ['Override named quote'];
        $this->writeOverrideFile($this->namedOverrideFile, $override);

        $this->assertSame($override, QuoteGroups::namedQuotes(self::TEST_LOCALE, ['Default named quote']));
    }

    #[Test]
    public function named_quotes_falls_back_to_default_when_override_file_is_empty(): void
    {
        $this->writeOverrideFile($this->namedOverrideFile, []);

        $default = ['Default named quote'];
        $this->assertSame($default, QuoteGroups::namedQuotes(self::TEST_LOCALE, $default));
    }

    /** @param array<int, string> $quotes */
    private function writeOverrideFile(string $file, array $quotes): void
    {
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), recursive: true);
        }
        file_put_contents(
            $file,
            '<?php return '.var_export($quotes, true).';'
        );
    }
}
