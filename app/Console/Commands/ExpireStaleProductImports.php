<?php

namespace App\Console\Commands;

use App\Models\ProductImport;
use Illuminate\Console\Command;

class ExpireStaleProductImports extends Command
{
    protected $signature = 'imports:expire-stale';

    protected $description = 'Marca como error las importaciones de productos que quedaron pending o processing más allá del timeout';

    public function handle(): int
    {
        $count = ProductImport::expireStale();

        $this->info($count === 0
            ? 'No hay importaciones trabadas.'
            : "{$count} importación(es) marcada(s) como error.");

        return self::SUCCESS;
    }
}
