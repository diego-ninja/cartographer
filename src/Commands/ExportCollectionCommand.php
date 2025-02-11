<?php

namespace Ninja\Cartographer\Commands;

use Ninja\Cartographer\Authentication\Basic;
use Ninja\Cartographer\Authentication\Bearer;
use Ninja\Cartographer\Enums\Format;
use Ninja\Cartographer\Exporters\InsomniaExporter;
use Ninja\Cartographer\Exporters\PostmanExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportCollectionCommand extends Command
{
    /** @var string */
    protected $signature = 'export:collection
                            {--format= : The format for the collection [postman|insomnia|bruno](default: postman)}
                            {--bearer= : The bearer token to use on your endpoints}
                            {--basic= : The basic auth to use on your endpoints}';

    /** @var string */
    protected $description = 'Automatically generate a request collection for your API routes';

    public function handle(): void
    {
        $format = Format::tryFrom($this->option('format')) ?? Format::Postman;

        $filename = str_replace(
            ['{timestamp}', '{app}', '{format}'],
            [date('Y_m_d_His'), Str::snake(config('app.name')), $format->value],
            config('cartographer.filename'),
        );

        config()->set('cartographer.authentication', [
            'method' => $this->option('bearer') ? 'bearer' : ($this->option('basic') ? 'basic' : null),
            'token' => $this->option('bearer') ?? $this->option('basic') ?? null,
        ]);

        $exporter = match ($format) {
            Format::Insomnia => app(InsomniaExporter::class),
            Format::Bruno => app(BrunoExporter::class),
            Format::Postman => app(PostmanExporter::class),
        };

        $exporter
            ->to($filename)
            ->setAuthentication(value(function () {
                if (filled($this->option('bearer'))) {
                    return new Bearer($this->option('bearer'));
                }

                if (filled($this->option('basic'))) {
                    return new Basic($this->option('basic'));
                }

                return null;
            }))
            ->export();

        Storage::disk(config('cartographer.disk'))
            ->put(sprintf('%s/%s', $format->value, $filename), $exporter->getOutput());

        $this->info('Collection Exported: ' . storage_path(sprintf('app/%s/%s', $format->value, $filename)));
    }
}
