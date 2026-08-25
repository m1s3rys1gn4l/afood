<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyFoodItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'items:import-legacy {--force : Run even if legacy items already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time import of the legacy aFood (StackFood) add-ons and food items into the food module';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $files = [
            'add_ons' => database_path('data/legacy_food/add_ons.sql'),
            'items' => database_path('data/legacy_food/items.sql'),
        ];

        foreach ($files as $label => $path) {
            if (!file_exists($path)) {
                $this->error("Legacy data file not found for {$label} at database/data/legacy_food/. Did the git pull include it?");
                return self::FAILURE;
            }
        }

        // Depends on stores already being migrated — items.store_id references the
        // same remapped store ids, and add_ons.store_id likewise.
        if (DB::table('stores')->count() <= 2) {
            $this->error('Stores table only has the original demo store(s) — run `php artisan stores:import-legacy` first.');
            return self::FAILURE;
        }

        $foodModule = DB::table('modules')->where('module_type', 'food')->first();
        if (!$foodModule) {
            $this->error('No module with module_type = "food" found — cannot import items into it.');
            return self::FAILURE;
        }
        // database/data/legacy_food/items.sql has module_id hardcoded to 2, matching
        // the live server's food module id at the time this was generated.
        if ((int) $foodModule->id !== 2) {
            $this->error("The food module's id is {$foodModule->id}, but the legacy data file assumes id 2. Regenerate database/data/legacy_food/items.sql before running this.");
            return self::FAILURE;
        }

        $existingItems = DB::table('items')->where('module_id', $foodModule->id)->count();
        if ($existingItems > 0 && !$this->option('force')) {
            $this->error("The food module already has {$existingItems} items. Refusing to run — pass --force if you're sure you want to proceed.");
            return self::FAILURE;
        }

        $this->info('Importing legacy add-ons...');
        $addOnsInserted = $this->runInsertFile($files['add_ons']);
        $this->info("Inserted {$addOnsInserted} add-on(s).");

        $this->info('Importing legacy food items (this may take a moment)...');
        $itemsInserted = $this->runInsertFile($files['items']);
        $this->info("Inserted {$itemsInserted} item(s).");

        $maxAddOnId = DB::table('add_ons')->max('id');
        $maxItemId = DB::table('items')->max('id');
        DB::statement('ALTER TABLE add_ons AUTO_INCREMENT = ' . ((int) $maxAddOnId + 1));
        DB::statement('ALTER TABLE items AUTO_INCREMENT = ' . ((int) $maxItemId + 1));

        $this->info('Legacy item import complete.');
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
