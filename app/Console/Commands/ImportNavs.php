<?php

namespace App\Console\Commands;

use App\Nav\AmfiNavFileException;
use App\Nav\AmfiNavFileParser;
use App\Nav\NavImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

#[Signature('nav:import {--file= : Import a local NAVAll.txt instead of downloading it from AMFI}')]
#[Description('Import the AMFI scheme list and latest NAVs')]
class ImportNavs extends Command
{
    public function handle(AmfiNavFileParser $parser, NavImporter $importer): int
    {
        try {
            $contents = $this->contents();
            $result = $importer->import($parser->parse($contents));
        } catch (ConnectionException|RequestException $e) {
            $this->error('Could not download the AMFI NAV file: '.$e->getMessage());

            return self::FAILURE;
        } catch (AmfiNavFileException $e) {
            $this->error('The AMFI NAV file could not be imported: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Imported AMFI NAVs dated '.$result->fileDate->format('d M Y').'.');
        $this->table(['Created', 'Updated', 'Active', 'Inactive', 'ETFs skipped', 'History rows added'], [[
            $result->created,
            $result->updated,
            $result->active,
            $result->inactive,
            $result->skippedEtfs,
            $result->historyAppended,
        ]]);

        return self::SUCCESS;
    }

    /**
     * @throws ConnectionException|RequestException|AmfiNavFileException
     */
    private function contents(): string
    {
        $file = $this->option('file');

        if (is_string($file) && $file !== '') {
            $contents = is_readable($file) ? file_get_contents($file) : false;

            if ($contents === false) {
                throw new AmfiNavFileException("Cannot read [{$file}].");
            }

            return $contents;
        }

        return Http::timeout(config()->integer('services.amfi.timeout'))
            ->retry(3, 2000)
            ->get(config()->string('services.amfi.nav_url'))
            ->throw()
            ->body();
    }
}
