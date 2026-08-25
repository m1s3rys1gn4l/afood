<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyFoodStores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:import-legacy {--force : Run even if legacy stores already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time import of the legacy aFood (StackFood) vendors, stores, and store schedules into the food module';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $files = [
            'vendors' => database_path('data/legacy_food/vendors.sql'),
            'stores' => database_path('data/legacy_food/stores.sql'),
            'store_schedule' => database_path('data/legacy_food/store_schedule.sql'),
        ];

        foreach ($files as $label => $path) {
            if (!file_exists($path)) {
                $this->error("Legacy data file not found for {$label} at database/data/legacy_food/. Did the git pull include it?");
                return self::FAILURE;
            }
        }

        // Depends on zones already being migrated (customers:import-legacy) — stores
        // reference the same remapped zone ids.
        if (DB::table('zones')->count() <= 1) {
            $this->error('Zones table only has the original zone(s) — run `php artisan customers:import-legacy` first (it also imports the real zones).');
            return self::FAILURE;
        }

        $foodModule = DB::table('modules')->where('module_type', 'food')->first();
        if (!$foodModule) {
            $this->error('No module with module_type = "food" found — cannot import stores into it.');
            return self::FAILURE;
        }
        // database/data/legacy_food/stores.sql has module_id hardcoded to 2, matching
        // the live server's food module id at the time this was generated.
        if ((int) $foodModule->id !== 2) {
            $this->error("The food module's id is {$foodModule->id}, but the legacy data file assumes id 2. Regenerate database/data/legacy_food/stores.sql before running this.");
            return self::FAILURE;
        }

        $existingStores = DB::table('stores')->where('module_id', $foodModule->id)->count();
        if ($existingStores > 1 && !$this->option('force')) {
            // >1 because a fresh install already ships with one demo store for the module.
            $this->error("The food module already has {$existingStores} stores. Refusing to run — pass --force if you're sure you want to proceed.");
            return self::FAILURE;
        }

        $this->info('Importing legacy vendors...');
        $vendorsInserted = $this->runInsertFile($files['vendors']);
        $this->info("Inserted {$vendorsInserted} vendor(s).");

        $this->info('Importing legacy stores...');
        $storesInserted = $this->runInsertFile($files['stores']);
        $this->info("Inserted {$storesInserted} store(s).");

        $this->info('Importing legacy store schedules...');
        $scheduleInserted = $this->runInsertFile($files['store_schedule']);
        $this->info("Inserted {$scheduleInserted} store schedule row(s).");

        $maxVendorId = DB::table('vendors')->max('id');
        $maxStoreId = DB::table('stores')->max('id');
        DB::statement('ALTER TABLE vendors AUTO_INCREMENT = ' . ((int) $maxVendorId + 1));
        DB::statement('ALTER TABLE stores AUTO_INCREMENT = ' . ((int) $maxStoreId + 1));

        $this->info('Legacy store import complete.');
        return self::SUCCESS;
    }

    /**
     * Execute every INSERT statement in the given file inside one transaction.
     */
    private function runInsertFile(string $path): int
    {
        $count = 0;

        DB::transaction(function () use ($path, &$count) {
            $handle = fopen($path, 'r');
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue;
                }
                DB::unprepared($line);
                $count++;
            }
            fclose($handle);
        });

        return $count;
    }
}
