<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenApi\Generator;
use Psr\Log\AbstractLogger;

class GenerateSwaggerDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:generate-safe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Swagger documentation without treating warnings as errors';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating Swagger documentation...');

        // Suppress warnings temporarily
        $oldErrorHandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if ($errno === E_USER_WARNING || $errno === E_USER_NOTICE) {
                // Silently ignore warnings and notices
                return true;
            }

            return false;
        });

        try {
            // Create a custom logger that suppresses warnings
            $logger = new class extends AbstractLogger
            {
                public function log($level, $message, array $context = []): void
                {
                    // Suppress all logs
                }
            };

            // Get scan options from config
            $config = config('l5-swagger.documentations.default');
            $paths = $config['paths']['annotations'] ?? [base_path('app')];

            // Scan with custom logger
            $openapi = Generator::scan($paths, [
                'logger' => $logger,
                'version' => '3.1.0',
            ]);

            // Ensure directory exists
            $docsPath = storage_path('api-docs');
            if (! is_dir($docsPath)) {
                mkdir($docsPath, 0755, true);
            }

            // Save JSON
            file_put_contents($docsPath.'/api-docs.json', $openapi->toJson());
            $this->info('✓ Documentation saved to storage/api-docs/api-docs.json');

            // Save YAML if configured
            if (config('l5-swagger.defaults.generate_yaml_copy')) {
                file_put_contents($docsPath.'/api-docs.yaml', $openapi->toYaml());
                $this->info('✓ Documentation saved to storage/api-docs/api-docs.yaml');
            }

            $this->info('Swagger documentation generated successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error generating Swagger documentation: '.$e->getMessage());
            $this->error('File: '.$e->getFile());
            $this->error('Line: '.$e->getLine());

            return Command::FAILURE;
        } finally {
            restore_error_handler();
        }
    }
}
