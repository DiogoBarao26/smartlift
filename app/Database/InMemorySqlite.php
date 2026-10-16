<?php

namespace App\Database;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InMemorySqlite
{
    public function boot(Application $app): void
    {
        if ($app->runningUnitTests() || $app->runningInConsole()) {
            return;
        }

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            return;
        }

        $this->restore();

        $app->terminating(function () {
            $this->snapshot();
        });
    }

    private function restore(): void
    {
        $path = $this->snapshotPath();

        if (! is_file($path) || filesize($path) === 0) {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);

            return;
        }

        $pdo = DB::connection()->getPdo();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('ATTACH DATABASE '.$this->quote($path).' AS snapshot');

        $objects = DB::select("
            SELECT type, name, sql
            FROM snapshot.sqlite_master
            WHERE sql IS NOT NULL
              AND name NOT LIKE 'sqlite_%'
            ORDER BY CASE type WHEN 'table' THEN 0 WHEN 'index' THEN 1 ELSE 2 END, rowid
        ");

        foreach ($objects as $object) {
            if ($object->type === 'table') {
                $pdo->exec($object->sql);
                $identifier = $this->identifier($object->name);
                $pdo->exec("INSERT INTO main.{$identifier} SELECT * FROM snapshot.{$identifier}");
            }
        }

        $sequence = DB::select("SELECT name FROM snapshot.sqlite_master WHERE name = 'sqlite_sequence'");

        if ($sequence !== []) {
            $pdo->exec('DELETE FROM main.sqlite_sequence');
            $pdo->exec('INSERT INTO main.sqlite_sequence SELECT * FROM snapshot.sqlite_sequence');
        }

        foreach ($objects as $object) {
            if ($object->type === 'index') {
                $pdo->exec($object->sql);
            }
        }

        $pdo->exec('DETACH DATABASE snapshot');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    private function snapshot(): void
    {
        $path = $this->snapshotPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (is_file($path)) {
            unlink($path);
        }

        DB::connection()->getPdo()->exec('VACUUM INTO '.$this->quote($path));
    }

    private function snapshotPath(): string
    {
        return storage_path('framework/smartlift-memory.sqlite');
    }

    private function quote(string $path): string
    {
        return "'".str_replace("'", "''", str_replace('\\', '/', $path))."'";
    }

    private function identifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
