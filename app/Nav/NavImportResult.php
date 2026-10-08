<?php

namespace App\Nav;

use DateTimeImmutable;

final readonly class NavImportResult
{
    public function __construct(
        public DateTimeImmutable $fileDate,
        public int $created,
        public int $updated,
        public int $active,
        public int $inactive,
        public int $skippedEtfs,
        public int $historyAppended,
    ) {}
}
