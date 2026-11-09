<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use Tests\Browser\Support\AdminModeBrowser;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Browsers start loginAs() sessions in admin mode (privileges only count with it, see App\Support\AdminMode).
     *
     * @param  RemoteWebDriver  $driver
     */
    protected function newBrowser($driver): AdminModeBrowser
    {
        return new AdminModeBrowser($driver);
    }

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail() && ! filter_var(env('DUSK_SKIP_CHROMEDRIVER', false), FILTER_VALIDATE_BOOL)) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-dev-shm-usage',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--no-sandbox',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        $binary = $_ENV['DUSK_CHROME_BINARY'] ?? env('DUSK_CHROME_BINARY');

        if (! $binary && is_executable('/snap/bin/chromium')) {
            $binary = '/snap/bin/chromium';
        }

        if ($binary) {
            $options->setBinary($binary);
        }

        if ($profile = env('DUSK_CHROME_USER_DATA_DIR')) {
            $options->addArguments(['--user-data-dir='.$profile]);
        }

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options,
            ),
        );
    }
}
