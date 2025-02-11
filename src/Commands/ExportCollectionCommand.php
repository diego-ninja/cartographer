<?php

namespace Ninja\Cartographer\Commands;

use Exception;
use Ninja\Cartographer\Authentication\Strategy\AuthStrategyFactory;
use Ninja\Cartographer\Enums\Format;
use Ninja\Cartographer\Exporters\InsomniaExporter;
use Ninja\Cartographer\Exporters\PostmanExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ninja\Cartographer\Processors\AuthenticationProcessor;
use ReflectionException;

class ExportCollectionCommand extends Command
{
    protected $signature = 'cartographer:export
                            {--format= : The format for the collection [postman|insomnia|bruno](default: postman)}
                            {--bearer= : The bearer token to use on your endpoints}
                            {--basic= : The basic auth to use on your endpoints}';

    protected $description = 'Automatically generate a request collection for your API routes';

    public function __construct(
        private readonly AuthenticationProcessor $authProcessor
    ) {
        parent::__construct();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function handle(): void
    {
        $format = Format::tryFrom($this->option('format')) ?? Format::Postman;

        $filename = str_replace(
            ['{timestamp}', '{app}', '{format}'],
            [date('Y_m_d_His'), Str::snake(config('app.name')), $format->value],
            config('cartographer.filename'),
        );

        // Set authentication based on command options
        if (filled($this->option('bearer'))) {
            $strategy = AuthStrategyFactory::create('bearer', $this->option('bearer'));
            $this->authProcessor->setStrategy($strategy);
        } elseif (filled($this->option('basic'))) {
            $strategy = AuthStrategyFactory::create('basic', $this->option('basic'));
            $this->authProcessor->setStrategy($strategy);
        }

        $exporter = match ($format) {
            Format::Insomnia => app(InsomniaExporter::class),
            Format::Postman => app(PostmanExporter::class),
            Format::Bruno => throw new Exception('To be implemented'),
        };

        $exporter->to($filename)->export();

        Storage::disk(config('cartographer.disk'))
            ->put(sprintf('%s/%s', $format->value, $filename), $exporter->getOutput());

        $this->info('Collection Exported: ' . storage_path(sprintf('app/%s/%s', $format->value, $filename)));
    }
}
