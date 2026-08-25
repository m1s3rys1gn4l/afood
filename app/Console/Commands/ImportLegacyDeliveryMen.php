<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyDeliveryMen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deliverymen:import-legacy {--force : Run even if legacy delivery men already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time import of the legacy aFood (StackFood) delivery men';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $file = database_path('data/legacy_delivery/delivery_men.sql');

        if (!file_exists($file)) {
            $this->error('Legacy data file not found at database/data/legacy_delivery/delivery_men.sql. Did the git pull include it?');
            return self::FAILURE;
        }

        // Depends on zones already being migrated — delivery_men.zone_id references
        // the same remapped zone ids.
        if (DB::table('zones')->count() <= 1) {
            $this->error('Zones table only has the original zone(s) — run `php artisan customers:import-legacy` first (it also imports the real zones).');
            return self::FAILURE;
        }

        $existing = DB::table('delivery_men')->count();
        if ($existing > 1 && !$this->option('force')) {
            // >1 because this project already has one real delivery man account.
            $this->error("The delivery_men table already has {$existing} row(s). Refusing to run — pass --force if you're sure you want to proceed.");
            return self::FAILURE;
        }

        $this->info('Importing legacy delivery men...');
        $inserted = $this->runInsertFile($file);
        $this->info("Inserted {$inserted} delivery man/men.");

        $maxId = DB::table('delivery_men')->max('id');
        DB::statement('ALTER TABLE delivery_men AUTO_INCREMENT = ' . ((int) $maxId + 1));

        $this->info('Legacy delivery man import complete.');
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
