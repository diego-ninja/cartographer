<?php

namespace Ninja\Cartographer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ninja\Cartographer\Authentication\Strategy\AuthStrategyFactory;
use Ninja\Cartographer\Enums\Format;
use Ninja\Cartographer\Exceptions\CartographerException;
use Ninja\Cartographer\Exceptions\ConfigurationException;
use Ninja\Cartographer\Exceptions\ExportException;
use Ninja\Cartographer\Exporters\InsomniaExporter;
use Ninja\Cartographer\Exporters\PostmanExporter;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use Ninja\Cartographer\Processors\RouteProcessor;
use Throwable;

class ExportCollectionCommand extends Command
{
    protected $signature = 'cartographer:export
                            {--format= : The format for the collection [postman|insomnia|bruno](default: postman)}
                            {--bearer= : The bearer token to use on your endpoints}
                            {--basic= : The basic auth to use on your endpoints}
                            {--structured= : Group endpoints by path structure}';

    protected $description = 'Automatically generate a request collection for your API routes';

    public function __construct(
        private readonly AuthenticationProcessor $authProcessor,
    ) {
        parent::__construct();
    }

    /**
     * @throws ConfigurationException
     * @throws ExportException
     * @throws Throwable
     */
    public function handle(): void
    {
        try {
            // Validate and set format
            $format = $this->resolveFormat();

            // Configure authentication if provided
            $this->configureAuthentication();

            // Update structured configuration
            if ($this->option('structured')) {
                config()->set('cartographer.structured', $this->option('structured'));
            }

            // Generate filename
            $filename = $this->generateFilename($format);

            // Get appropriate exporter
            $exporter = $this->resolveExporter($format);

            // Export and store collection
            $exporter->to($filename)->export();

            $this->storeCollection($format, $filename, $exporter->getOutput());

            $this->info('Group Exported: ' . storage_path(sprintf('app/%s/%s', $format->value, $filename)));
        } catch (CartographerException $e) {
            $this->error($e->getMessage());
            return;
        } catch (Throwable $e) {
            $this->error('An unexpected error occurred: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @throws ConfigurationException
     */
    private function resolveFormat(): Format
    {
        $formatOption = $this->option('format');
        $format = Format::tryFrom($formatOption ?? Format::Postman->value);

        if ( ! $format) {
            throw ConfigurationException::invalidExportFormat($formatOption ?? 'null');
        }

        return $format;
    }

    private function configureAuthentication(): void
    {
        if (filled($this->option('bearer'))) {
            $strategy = AuthStrategyFactory::create('bearer', $this->option('bearer'));
            $this->authProcessor->setStrategy($strategy);
        } elseif (filled($this->option('basic'))) {
            $strategy = AuthStrategyFactory::create('basic', $this->option('basic'));
            $this->authProcessor->setStrategy($strategy);
        }
    }

    private function generateFilename(Format $format): string
    {
        return str_replace(
            ['{timestamp}', '{app}', '{format}'],
            [date('Y_m_d_His'), Str::snake(config('app.name')), $format->value],
            config('cartographer.filename'),
        );
    }

    /**
     * @throws ConfigurationException
     */
    private function resolveExporter(Format $format): PostmanExporter|InsomniaExporter
    {
        return match ($format) {
            Format::Insomnia => new InsomniaExporter(config(), app(RouteProcessor::class), $this->authProcessor),
            Format::Postman => new PostmanExporter(config(), app(RouteProcessor::class), $this->authProcessor),
            Format::Bruno => throw ConfigurationException::invalidExportFormat('bruno (not implemented)'),
        };
    }

    /**
     * @throws ExportException
     */
    private function storeCollection(Format $format, string $filename, string $content): void
    {
        $path = sprintf('%s/%s', $format->value, $filename);

        if ( ! Storage::disk(config('cartographer.disk'))->put($path, $content)) {
            throw ExportException::failedToWriteFile($path, 'Storage write failed');
        }
    }
}
