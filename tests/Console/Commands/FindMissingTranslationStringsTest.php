<?php

namespace CodingSocks\LostInTranslation\Tests\Console\Commands;

use CodingSocks\LostInTranslation\LostInTranslationServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

class FindMissingTranslationStringsTest extends TestCase
{
    public function testBasicCommand(): void
    {
        $exit_code = $this
            ->withoutMockingConsoleOutput()
            ->artisan('lost-in-translation:find ja --no-progress --sorted');

        $output = Artisan::output();
        $lines = array_filter(preg_split('/[\r\n]+/', $output));

        $this->assertSame([
            "foobar",
            "global_key",
            "messages.namespaced_key"
        ], $lines);
        $this->assertSame(0, $exit_code);
    }

    public function testMissingLocale(): void
    {
        $exit_code = $this
            ->withoutMockingConsoleOutput()
            ->artisan('lost-in-translation:find zh --no-progress --sorted');

        $output = Artisan::output();
        $lines = array_filter(preg_split('/[\r\n]+/', $output));

        $this->assertSame([
            "foobar",
            "global_key",
            "key_in_both",
            "messages.namespaced_key"
        ], $lines);
        $this->assertSame(0, $exit_code);
    }

    public function testWithLocation(): void
    {
        $exit_code = $this
            ->withoutMockingConsoleOutput()
            ->artisan('lost-in-translation:find ja --no-progress --sorted --location');

        $output = Artisan::output();
        $lines = array_filter(preg_split('/[\r\n]+/', $output));

        $this->assertSame([
            "foobar",
            "\tin sample.blade.php",
            "global_key",
            "\tin lang/en.json",
            "messages.namespaced_key",
            "\tin lang/en/messages.php",
        ], $lines);
        $this->assertSame(0, $exit_code);
    }

    public function testWithJson(): void
    {
        $exit_code = $this
            ->withoutMockingConsoleOutput()
            ->artisan('lost-in-translation:find ja --no-progress --sorted --json');

        $output = Artisan::output();
        $lines = array_filter(preg_split('/[\r\n]+/', $output));

        $this->assertSame([
            '"foobar"',
            '"global_key"',
            '"messages.namespaced_key"'
        ], $lines);
        $this->assertSame(0, $exit_code);
    }

    public function testWithJsonAndLocations(): void
    {
        $exit_code = $this
            ->withoutMockingConsoleOutput()
            ->artisan('lost-in-translation:find ja --no-progress --sorted --json --location');

        $output = Artisan::output();
        $data = self::naiveParseJsonLines($output);

        $this->assertCount(3, $data);
        $this->assertSame([
            [
                'key' => 'foobar',
                'locations' => ['sample.blade.php'],
            ],
            [
                "key" => "global_key",
                "locations" => ['lang/en.json'],
            ],
            [
                "key" => "messages.namespaced_key",
                "locations" => ['lang/en/messages.php'],
            ],
        ], $data);
        $this->assertSame(0, $exit_code);
    }

    public function getPackageProviders($app)
    {
        return [
            LostInTranslationServiceProvider::class,
        ];
    }

    public function defineEnvironment($app)
    {
        $appPath = __DIR__ . '/../../application';
        $app->useLangPath($appPath . '/lang');

        tap($app['config'], static function (Repository $config) use ($appPath) {
            $config->set('lost-in-translation.paths', [
                $appPath . '/resources/views'
            ]);
        });
    }

    /** @return list<mixed> */
    static private function naiveParseJsonLines(string $output): array
    {
        $lines = array_filter(preg_split('/[\r\n]+/', $output));
        $buf = '';
        $data = [];
        $started = false;
        foreach ($lines as $line) {
            if (!$started) {
                if (trim($line) === '{') {
                    $started = true;
                    $buf = $line;
                }
            } else {
                if (trim($line) === "}") {
                    $data[] = json_decode($buf . $line, JSON_THROW_ON_ERROR);
                    $started = false;
                } else {
                    $buf .= $line;
                }
            }
        }

        return $data;
    }
}
