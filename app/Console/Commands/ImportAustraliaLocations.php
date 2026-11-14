<?php

namespace App\Console\Commands;

use App\Models\Locations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the Australian locality/postcode reference data (database/data/locations/au-localities.csv, Geoscape G-NAF) into
 * au_states / au_localities / au_locality_postcodes. Idempotent: localities are keyed by their source id, postcodes are
 * synced per locality, and customer_addresses is never touched.
 */
class ImportAustraliaLocations extends Command
{
    protected $signature = 'locations:import-australia
        {file? : CSV to import (default database/data/locations/au-localities.csv)}
        {--dataset-version=2026-08-17 : Version label of the source release (G-NAF Aug 2026 = 2026-08-17)}
        {--release-date=2026-08-17 : Release date of the source (YYYY-MM-DD)}
        {--source=Geoscape G-NAF (CC BY 4.0, Open G-NAF EULA) : Where the file came from}
        {--dry-run : Validate and report without writing}
        {--force : Import even if the source looks incomplete (fewer than 8 states, under 1000 localities, or 10%+ fewer than the last import)}';

    protected $description = 'Import/update the Australian suburb, state and postcode reference data (repeatable and safe to re-run)';

    private const STATES = ['ACT' => 'Australian Capital Territory', 'NSW' => 'New South Wales', 'NT' => 'Northern Territory', 'OT' => 'Other Territories',
        'QLD' => 'Queensland', 'SA' => 'South Australia', 'TAS' => 'Tasmania', 'VIC' => 'Victoria', 'WA' => 'Western Australia'];

    private const COLUMNS = ['state', 'locality_pid', 'locality_name', 'class_code', 'postcode'];

    public function handle(): int
    {
        $file = $this->argument('file') ?: database_path('data/locations/au-localities.csv');
        if (! is_file($file) || ! is_readable($file)) {
            $this->error("Source file not found or unreadable: {$file}");

            return self::FAILURE;
        }

        [$localities, $invalid, $errors] = $this->read($file);
        if ($localities === null) {
            $this->error($errors[0]);

            return self::FAILURE;
        }

        $postcodes = array_sum(array_map(fn ($l) => count($l['postcodes']), $localities));
        $stateCount = count(array_unique(array_column($localities, 'state')));
        $this->line(sprintf('Read %d localities, %d locality/postcode pairs, %d states/territories; %d invalid row(s).', count($localities), $postcodes, $stateCount, $invalid));
        foreach (array_slice($errors, 0, 10) as $e) {
            $this->warn('  '.$e);
        }
        if (($stateCount < 8 || count($localities) < 1000) && ! $this->option('force')) {
            $this->error('The source looks incomplete (expected all 8 states/territories and thousands of localities). Nothing imported.');

            return self::FAILURE;
        }

        $previous = DB::table('au_location_imports')->orderByDesc('id')->value('localities_count');
        if ($previous && count($localities) < $previous * 0.9 && ! $this->option('force')) {
            $this->error("The source has far fewer localities ({$previous} before, ".count($localities).' now). Check the file, or pass --force.');

            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run: no changes written.');

            return self::SUCCESS;
        }

        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        DB::transaction(function () use ($localities, &$stats) {
            foreach (self::STATES as $code => $name) {
                DB::table('au_states')->upsert([['id' => array_search($code, array_keys(self::STATES), true) + 1, 'code' => $code, 'name' => $name]], ['code'], ['name']);
            }
            $stateIds = DB::table('au_states')->pluck('id', 'code')->all();
            $existing = DB::table('au_localities')->get(['id', 'source_pid', 'state_id', 'name', 'class_code'])->keyBy('source_pid');
            $havePostcodes = [];
            foreach (DB::table('au_locality_postcodes')->get(['locality_id', 'postcode']) as $r) {
                $havePostcodes[$r->locality_id][$r->postcode] = true;
            }

            $newPostcodes = [];
            foreach ($localities as $pid => $l) {
                $row = ['state_id' => $stateIds[$l['state']], 'name' => $l['name'], 'class_code' => $l['class']];
                $cur = $existing[$pid] ?? null;
                if (! $cur) {
                    $id = DB::table('au_localities')->insertGetId($row + ['source_pid' => $pid]);
                    $stats['inserted']++;
                } else {
                    $id = (int) $cur->id;
                    if ((int) $cur->state_id !== $row['state_id'] || $cur->name !== $row['name'] || $cur->class_code !== $row['class_code']) {
                        DB::table('au_localities')->where('id', $id)->update($row);
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                }
                $have = $havePostcodes[$id] ?? [];
                foreach ($l['postcodes'] as $pc) {
                    if (! isset($have[$pc])) {
                        $newPostcodes[] = ['locality_id' => $id, 'postcode' => $pc];
                    }
                }
                $stale = array_diff(array_keys($have), $l['postcodes']);
                if ($stale) {
                    DB::table('au_locality_postcodes')->where('locality_id', $id)->whereIn('postcode', array_map('strval', $stale))->delete();
                }
            }
            foreach (array_chunk($newPostcodes, 1000) as $chunk) {
                DB::table('au_locality_postcodes')->insertOrIgnore($chunk);
            }
            // Localities no longer in the source are removed (customer addresses keep their text copy).
            $gone = $existing->keys()->diff(array_keys($localities));
            foreach ($gone->chunk(500) as $chunk) {
                DB::table('au_localities')->whereIn('source_pid', $chunk->all())->delete();
            }
        });

        DB::table('au_location_imports')->insert([
            'source' => (string) $this->option('source'), 'dataset_version' => (string) $this->option('dataset-version'),
            'release_date' => $this->option('release-date') ?: null, 'file_sha256' => hash_file('sha256', $file),
            'states_count' => $stateCount, 'localities_count' => count($localities), 'postcodes_count' => $postcodes,
            'inserted' => $stats['inserted'], 'updated' => $stats['updated'], 'skipped' => $stats['skipped'], 'invalid' => $invalid,
        ]);
        Locations::flushCache();

        $this->info(sprintf('Imported %d new, updated %d, unchanged %d, invalid %d. Total: %d localities, %d suburb-postcode pairs.',
            $stats['inserted'], $stats['updated'], $stats['skipped'], $invalid, count($localities), $postcodes));

        return self::SUCCESS;
    }

    /** @return array{0: ?array<string,array>, 1: int, 2: list<string>} [localities keyed by source pid, invalid row count, messages] */
    private function read(string $file): array
    {
        $h = fopen($file, 'rb');
        $header = fgetcsv($h, 0, ',', '"', '');
        if ($header === false || array_diff(self::COLUMNS, $header)) {
            return [null, 0, ['Invalid source file: expected columns '.implode(', ', self::COLUMNS).'.']];
        }
        $idx = array_flip($header);
        $out = [];
        $invalid = 0;
        $errors = [];
        for ($line = 2; ($r = fgetcsv($h, 0, ',', '"', '')) !== false; $line++) {
            $state = $r[$idx['state']] ?? '';
            $pid = trim($r[$idx['locality_pid']] ?? '');
            $name = trim($r[$idx['locality_name']] ?? '');
            $pc = trim($r[$idx['postcode']] ?? '');
            $class = trim($r[$idx['class_code']] ?? '');
            if (! isset(self::STATES[$state]) || $pid === '' || $name === '' || ! preg_match('/^\d{4}$/', $pc)) {
                $invalid++;
                $errors[] = "line {$line}: invalid row (".implode(' | ', array_map('strval', $r)).')';

                continue;
            }
            $out[$pid] ??= ['state' => $state, 'name' => Locations::titleCase($name), 'class' => $class !== '' ? substr($class, 0, 1) : null, 'postcodes' => []];
            if (! in_array($pc, $out[$pid]['postcodes'], true)) {
                $out[$pid]['postcodes'][] = $pc;
            }
        }
        fclose($h);

        return [$out, $invalid, $errors];
    }
}
