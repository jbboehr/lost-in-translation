<?php

namespace CodingSocks\LostInTranslation\Tests;

use CodingSocks\LostInTranslation\Console\Commands\FindMissingTranslationStrings;
use CodingSocks\LostInTranslation\LostInTranslationServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Translation\FileLoader;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use function Orchestra\Testbench\artisan;

class FindMissingTranslationStringCommandTest extends \Orchestra\Testbench\TestCase
{
    public function testBasicCommand(): void
    {
        $this->artisan('lost-in-translation:find ja')
            ->expectsOutput('foobar');
    }

    public function testCommandWithLocationFlag(): void
    {
        $this->artisan('lost-in-translation:find ja --location')
            ->expectsOutputToContain('foobar')
            ->expectsOutputToContain('in sample.blade.php');
    }

    public function testCommandWithLocationAndJsonFlag(): void
    {
        $this->artisan('lost-in-translation:find ja --location --json')
            ->expectsOutput(<<<EOF
{
    "key": "foobar",
    "locations": [
        "sample.blade.php"
    ]
}
EOF
            );
    }

    public function getPackageProviders($app)
    {
        return [
            LostInTranslationServiceProvider::class,
        ];
    }

    public function defineEnvironment($app)
    {
        $app->useLangPath(__DIR__ . '/fixtures/lang');
        tap($app['config'], static function (Repository $config) {
            $config->set('lost-in-translation.paths', __DIR__ . '/fixtures');
        });
    }
}