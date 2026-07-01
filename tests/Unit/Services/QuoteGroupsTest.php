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

    protected function setUp(): void
    {
        parent::setUp();

        $this->overrideFile = __DIR__.'/../../../lang/'.self::TEST_LOCALE.'/booking/quotes.local.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->overrideFile)) {
            unlink($this->overrideFile);
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
        $this->writeOverrideFile($override);

        $this->assertSame($override, QuoteGroups::baseQuotes(self::TEST_LOCALE, ['Default quote']));
    }

    #[Test]
    public function falls_back_to_default_when_override_file_is_empty(): void
    {
        $this->writeOverrideFile([]);

        $default = ['Default quote'];
        $this->assertSame($default, QuoteGroups::baseQuotes(self::TEST_LOCALE, $default));
    }

    /** @param array<int, string> $quotes */
    private function writeOverrideFile(array $quotes): void
    {
        mkdir(dirname($this->overrideFile), recursive: true);
        file_put_contents(
            $this->overrideFile,
            '<?php return '.var_export($quotes, true).';'
        );
    }
}
