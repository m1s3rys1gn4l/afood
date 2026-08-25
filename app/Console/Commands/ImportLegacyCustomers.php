<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:import-legacy {--force : Run even if the users table already has rows}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time import of the legacy aFood (StackFood) zones and customers into this database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $usersFile = database_path('data/legacy_customers/users.sql');
        $zonesFile = database_path('data/legacy_customers/zones.sql');

        if (!file_exists($usersFile) || !file_exists($zonesFile)) {
            $this->error('Legacy data files not found under database/data/legacy_customers/. Did the git pull include them?');
            return self::FAILURE;
        }

        $existingUsers = DB::table('users')->count();
        if ($existingUsers > 0 && !$this->option('force')) {
            $this->error("The users table already has {$existingUsers} row(s). Refusing to run — pass --force if you're sure you want to proceed (e.g. re-running after a partial failure).");
            return self::FAILURE;
        }

        $this->info('Importing legacy zones...');
        $zonesInserted = $this->runInsertFile($zonesFile);
        $this->info("Inserted {$zonesInserted} zone(s).");

        $this->info('Importing legacy customers (this may take a moment)...');
        $usersInserted = $this->runInsertFile($usersFile);
        $this->info("Inserted {$usersInserted} user(s).");

        // Keep auto-increment ahead of the imported IDs so new signups/zones don't collide.
        $maxZoneId = DB::table('zones')->max('id');
        $maxUserId = DB::table('users')->max('id');
        DB::statement('ALTER TABLE zones AUTO_INCREMENT = ' . ((int) $maxZoneId + 1));
        DB::statement('ALTER TABLE users AUTO_INCREMENT = ' . ((int) $maxUserId + 1));

        $this->info('Legacy customer import complete.');
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
