<?php

namespace Ashraful19\LaravelMailbridge\Commands;

use Ashraful19\LaravelMailbridge\Support\ProviderCatalog;
use Illuminate\Console\Command;

final class ListProvidersCommand extends Command
{
    protected $signature = 'mailbridge:list-providers';

    protected $description = 'List configured Mailbridge providers and tested SDK versions.';

    public function handle(): int
    {
        $rows = [];

        foreach (ProviderCatalog::all() as $name => $provider) {
            $rows[] = [
                $name,
                $provider['driver'] ?? $name,
                $provider['sdk'] ?? '-',
                $provider['version'] ?? '-',
                implode(', ', $provider['capabilities'] ?? []),
            ];
        }

        $this->table(['Provider', 'Driver', 'SDK', 'Tested version', 'Capabilities'], $rows);

        return self::SUCCESS;
    }
}
