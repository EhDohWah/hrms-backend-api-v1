<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use L5Swagger\GeneratorFactory;

class GenerateSwaggerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'l5-swagger:generate {documentation?} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate docs (with warning suppression)';

    /**
     * Execute the console command.
     */
    public function handle(GeneratorFactory $generatorFactory): void
    {
        // Suppress E_USER_WARNING errors during swagger generation
        $oldErrorHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$oldErrorHandler) {
            if ($errno === E_USER_WARNING || $errno === E_USER_NOTICE) {
                // Silently ignore warnings and notices from swagger-php validation
                if (str_contains($errstr, 'PathItem') ||
                    str_contains($errstr, 'Components') ||
                    str_contains($errstr, 'Webhook') ||
                    str_contains($errstr, '@OA\\Info()')) {
                    return true;
                }
            }

            // Let other errors pass through to the previous handler
            if ($oldErrorHandler) {
                return call_user_func($oldErrorHandler, $errno, $errstr, $errfile, $errline);
            }

            return false;
        });

        try {
            $all = $this->option('all');

            if ($all) {
                /** @var array<string> $documentations */
                $documentations = array_keys(config('l5-swagger.documentations', []));

                foreach ($documentations as $documentation) {
                    $this->generateDocumentation($generatorFactory, $documentation);
                }

                return;
            }

            $documentation = $this->argument('documentation');

            if (! $documentation) {
                $documentation = config('l5-swagger.default');
            }

            $this->generateDocumentation($generatorFactory, $documentation);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Generates documentation using the specified generator factory.
     */
    private function generateDocumentation(GeneratorFactory $generatorFactory, string $documentation): void
    {
        $this->info('Regenerating docs '.$documentation);

        $generator = $generatorFactory->make($documentation);
        $generator->generateDocs();
    }
}
