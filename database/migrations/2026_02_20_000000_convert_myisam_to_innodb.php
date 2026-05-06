<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ConvertMyisamToInnodb extends Migration
{
    /**
     * Run the migrations.
     * Converts all tables in the current MySQL database with ENGINE=MyISAM to InnoDB.
     */
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $database = config('database.connections.mysql.database');
        if (! $database) return;

        $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND ENGINE = 'MyISAM'", [$database]);

        foreach ($tables as $t) {
            $table = $t->TABLE_NAME;
            try {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            } catch (\Exception $e) {
                // Don't stop migration on single-table failure; continue and log to laravel log
                logger()->error("Failed to convert table {$table} to InnoDB: {$e->getMessage()}");
            }
        }
    }

    /**
     * Reverse the migrations.
     * This migration is effectively irreversible safely; we leave down() empty.
     */
    public function down()
    {
        // Intentionally left empty. Reverting engines can be destructive; handle manually if needed.
    }
}
