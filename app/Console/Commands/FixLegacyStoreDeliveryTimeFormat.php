<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixLegacyStoreDeliveryTimeFormat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:fix-delivery-time-format';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time data fix: normalize delivery_time on stores imported by stores:import-legacy from the old "MIN-MAX-unit" format (e.g. 20-30-min) to the format this app expects, "MIN-MAX unit" (e.g. 20-30 min). Safe to run repeatedly — only touches rows still in the old format.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $affected = DB::table('stores')
            ->whereRaw("delivery_time REGEXP '^[0-9]+-[0-9]+-(min|minute|hours)$'")
            ->count();

        if ($affected === 0) {
            $this->info('No stores need fixing — delivery_time is already in the expected format everywhere.');
            return self::SUCCESS;
        }

        $this->info("Fixing delivery_time on {$affected} store(s)...");

        DB::statement("
            UPDATE stores
            SET delivery_time = REGEXP_REPLACE(
                REGEXP_REPLACE(delivery_time, '^([0-9]+)-([0-9]+)-minute$', '\$1-\$2 min'),
                '^([0-9]+)-([0-9]+)-(min|hours)$', '\$1-\$2 \$3'
            )
            WHERE delivery_time REGEXP '^[0-9]+-[0-9]+-(min|minute|hours)$'
        ");

        $this->info('Done.');
        return self::SUCCESS;
    }
}
