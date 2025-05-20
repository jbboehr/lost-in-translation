<?php

namespace CodingSocks\LostInTranslation\Console\Commands;

use CodingSocks\LostInTranslation\LostInTranslation;
use CodingSocks\LostInTranslation\MissingTranslationFileVisitor;
use Countable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\SplFileInfo;

class FindMissingTranslationStrings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lost-in-translation:find
                            {locale : The locale to be checked}
                            {--sorted : Sort the values before printing}
                            {--no-progress : Do not show the progress bar}
                            {--location : Print the location of missing translations}
                            {--json : Print the results in JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find missing translation strings in your Laravel blade files';

    /** @var \Illuminate\Contracts\Translation\Translator The translator instance. */
    protected Translator $translator;

    /** @var \Illuminate\Filesystem\Filesystem The filesystem instance. */
    protected Filesystem $files;

    /**
     * @param \Illuminate\Contracts\Translation\Translator $translator
     * @param \Illuminate\Filesystem\Filesystem $files
     */
    public function __construct(Translator $translator, Filesystem $files)
    {
        parent::__construct();

        $this->translator = $translator;
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(LostInTranslation $lit)
    {
        $baseLocale = config('lost-in-translation.locale');
        $locale = $this->argument('locale');
        $show_location = $this->option('location');
        $json_output = $this->option('json');

        [$missing, $locations] = $this->findInArray($baseLocale, $locale);

        $files = $this->collectFiles();

        $visitor = new MissingTranslationFileVisitor($locale, $lit, $this->translator);

        $this->traverse($files, $visitor);

        $this->printErrors($visitor->getErrors(), $this->output->getErrorStyle());

        $missing = $missing->merge($visitor->getTranslations())->unique();
        $locations = array_merge_recursive($locations, $visitor->getLocations());

        if ($this->option('sorted')) {
            $missing = $missing->sort();
        }

        if ($json_output) {
            if ($show_location) {
                $outputFormatter = function (string $key, array $locations) {
                    $this->line(json_encode([
                        'key' => $key,
                        'locations' => $locations,
                    ], JSON_PRETTY_PRINT));
                };
            } else {
                $outputFormatter = function (string $key) {
                    $this->line(json_encode($key, JSON_PRETTY_PRINT));
                };
            }
        } else {
            if ($show_location) {
                $outputFormatter = function (string $key, array $locations) {
                    $this->line(OutputFormatter::escape($key));
                    foreach ($locations as $location) {
                        $this->line("\tin " . $location);
                    }
                };
            } else {
                $outputFormatter = function (string $key) {
                    $this->line(OutputFormatter::escape($key));
                };
            }
        }

        foreach ($missing as $key) {
            $outputFormatter($key, isset($locations[$key]) ? $locations[$key] : []);
        }

        return self::SUCCESS;
    }

    /**
     * Execute a given callback while advancing a progress bar.
     *
     * @param \Countable|array $totalSteps
     * @param callable $callback
     * @return void
     */
    protected function traverse(Countable|array $totalSteps, callable $callback)
    {
        if ($this->option('no-progress')) {
            foreach ($totalSteps as $value) {
                $callback($value);
            }

            return;
        }

        $bar = $this->output->createProgressBar(count($totalSteps));

        $bar->start();

        foreach ($totalSteps as $value) {
            $callback($value);

            $bar->advance();
        }

        $bar->finish();
        $bar->clear();
    }

    /**
     * @param array $errors
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function printErrors(array $errors, OutputInterface $output): void
    {
        if ($this->output->getVerbosity() >= $this->parseVerbosity(OutputInterface::VERBOSITY_VERBOSE)) {
            foreach ($errors as $error) {
                $output->writeln($error);
            }
        }
    }

    /**
     * @param string $baseLocale
     * @param mixed $locale
     * @return array
     */
    protected function findInArray(string $baseLocale, mixed $locale)
    {
        if ($baseLocale === $locale) {
            return [Collection::empty(), []];
        }

        $data = Collection::make([]);
        $locations = [];
        $translator = $this->translator;

        if ($this->files->exists(lang_path($baseLocale . '.json'))) {
            $data = $data->merge(
                Collection::make($this->translator->get('*'))
                    ->each(static function ($item, string $key) use ($baseLocale, &$locations) {
                        $locations[$key][] = 'lang/'. $baseLocale .'.json';
                    })
            );
        }

        if ($this->files->exists(lang_path($baseLocale))) {
            $data = $data->merge(
                Collection::make($this->files->files(lang_path($baseLocale)))
                    ->mapWithKeys(static function (SplFileInfo $file) use ($translator) {
                        $tmp = Arr::dot([
                            $file->getFilenameWithoutExtension() => $translator->get($file->getFilenameWithoutExtension())
                        ]);

                        return array_combine(array_keys($tmp), array_fill(0, count($tmp), $file));
                    })
                    ->each(static function (SplFileInfo $file, string $key) use ($baseLocale, &$locations) {
                        $locations[$key][] = 'lang/' . $baseLocale . '/' . $file->getRelativePathname();
                    })
            );
        }

        return [
            $data->keys()
                ->filter(static function ($key) use ($locale, $translator) {
                    return !$translator->hasForLocale($key, $locale);
                }),
            $locations,
        ];
    }
    /**
     * @return mixed
     */
    protected function collectFiles()
    {
        return Collection::make(config('lost-in-translation.paths'))
            ->map(function (string $path) {
                return $this->files->allFiles($path);
            })
            ->flatten()
            ->unique()
            ->filter(function (\SplFileInfo $file) {
                return Str::endsWith($file->getExtension(), 'php');
            });
    }
}
