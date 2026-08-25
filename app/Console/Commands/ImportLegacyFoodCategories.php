<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyFoodCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:import-legacy {--force : Run even if legacy categories already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time import of the legacy aFood (StackFood) categories and sub-categories into the food module';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $file = database_path('data/legacy_food/categories.sql');

        if (!file_exists($file)) {
            $this->error('Legacy data file not found at database/data/legacy_food/categories.sql. Did the git pull include it?');
            return self::FAILURE;
        }

        $foodModule = DB::table('modules')->where('module_type', 'food')->first();
        if (!$foodModule) {
            $this->error('No module with module_type = "food" found — cannot import categories into it.');
            return self::FAILURE;
        }
        // database/data/legacy_food/categories.sql has module_id hardcoded to 2, matching
        // the live server's food module id at the time this was generated. Guard against
        // silently mis-assigning categories if that ever changes.
        if ((int) $foodModule->id !== 2) {
            $this->error("The food module's id is {$foodModule->id}, but the legacy data file assumes id 2. Regenerate database/data/legacy_food/categories.sql before running this.");
            return self::FAILURE;
        }

        $existing = DB::table('categories')->where('module_id', $foodModule->id)->count();
        if ($existing > 1 && !$this->option('force')) {
            // >1 because a fresh install already ships with one demo category for the module.
            $this->error("The food module already has {$existing} categories. Refusing to run — pass --force if you're sure you want to proceed.");
            return self::FAILURE;
        }

        $this->info('Importing legacy categories & sub-categories...');
        $inserted = $this->runInsertFile($file);
        $this->info("Inserted {$inserted} categories.");

        $maxId = DB::table('categories')->max('id');
        DB::statement('ALTER TABLE categories AUTO_INCREMENT = ' . ((int) $maxId + 1));

        $this->info('Legacy category import complete.');
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
