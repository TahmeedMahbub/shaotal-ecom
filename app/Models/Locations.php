<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single source of Australian states, suburbs/localities and postcodes (tables au_states, au_localities,
 * au_locality_postcodes; see sql/proposed_changes.sql §9). Every address form and address validator goes through here.
 * Customer-saved addresses stay in customer_addresses as text and are never touched by the reference data.
 */
class Locations
{
    /** States the store delivers to; the dataset also has "Other Territories" (OT), which is listed but not offered. */
    public const DELIVERABLE = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];

    public const PER_PAGE = 30;

    private const TTL = 3600;

    /** True once the reference tables exist and hold data. Cached (a missing table would otherwise cost a query per request). */
    public static function available(): bool
    {
        $cache = Cache::store('file');
        if (($hit = $cache->get('locations.available.v1')) !== null) {
            return $hit === true;
        }
        try {
            $ok = Schema::hasTable('au_localities') && DB::table('au_localities')->exists();
        } catch (\Throwable) {
            $ok = false;
        }
        // "No data yet" is only remembered briefly so a freshly applied import shows up within a minute.
        $cache->put('locations.available.v1', $ok, $ok ? self::TTL : 60);

        return $ok;
    }

    public static function flushCache(): void
    {
        Cache::store('file')->forget('locations.available.v1');
        Cache::store('file')->forget('locations.states.v1');
    }

    /** @return list<array{id:int,code:string,name:string,deliverable:bool}> */
    public static function states(): array
    {
        return Cache::store('file')->remember('locations.states.v1', self::TTL, fn () => DB::table('au_states')->orderBy('name')->get(['id', 'code', 'name'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'code' => $s->code, 'name' => $s->name, 'deliverable' => in_array($s->code, self::DELIVERABLE, true)])->all());
    }

    /**
     * One page of a state's suburbs, each with its postcodes (two queries for the page, no N+1).
     * Search matches the start of the name or of any word in it (case-insensitive), or a postcode prefix when numeric.
     *
     * @return array{data: list<array>, has_more: bool}
     */
    public static function suburbs(int $stateId, string $search, int $page, int $perPage = self::PER_PAGE): array
    {
        $perPage = max(1, min(100, $perPage));
        $q = DB::table('au_localities as l')->where('l.state_id', $stateId);
        $search = trim($search);
        if ($search !== '') {
            $like = self::like($search);
            if (ctype_digit($search)) {
                $q->whereExists(fn ($s) => $s->from('au_locality_postcodes as p')->whereColumn('p.locality_id', 'l.id')->where('p.postcode', 'like', $like.'%'));
            } else {
                $q->where(fn ($w) => $w->where('l.name', 'like', $like.'%')->orWhere('l.name', 'like', '% '.$like.'%')->orWhere('l.name', 'like', '%-'.$like.'%'));
                $q->orderByRaw('l.name LIKE ? DESC', [$like.'%']);
            }
        }
        $rows = $q->orderBy('l.name')->orderBy('l.id')->offset(max(0, $page - 1) * $perPage)->limit($perPage + 1)->get(['l.id', 'l.name', 'l.state_id']);
        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage);

        $codes = collect(self::states())->pluck('code', 'id');
        $pcs = DB::table('au_locality_postcodes')->whereIn('locality_id', $rows->pluck('id')->all() ?: [0])->orderBy('postcode')->get(['locality_id', 'postcode'])->groupBy('locality_id');

        return [
            'data' => $rows->map(fn ($r) => [
                'id' => (int) $r->id, 'name' => $r->name, 'state_id' => (int) $r->state_id, 'state' => $codes[$r->state_id] ?? '',
                'postcodes' => ($pcs[$r->id] ?? collect())->pluck('postcode')->map(fn ($p) => (string) $p)->all(),
            ])->all(),
            'has_more' => $hasMore,
        ];
    }

    /** @return list<string>|null postcodes of a suburb, or null when the suburb id does not exist */
    public static function postcodes(int $localityId): ?array
    {
        if (! DB::table('au_localities')->where('id', $localityId)->exists()) {
            return null;
        }

        return DB::table('au_locality_postcodes')->where('locality_id', $localityId)->orderBy('postcode')->pluck('postcode')->map(fn ($p) => (string) $p)->all();
    }

    public static function stateExists(int $stateId): bool
    {
        return collect(self::states())->contains('id', $stateId);
    }

    /** Does this exact state + suburb + postcode exist in the dataset? (Suburb compared case-insensitively by collation.) */
    public static function matches(string $state, string $suburb, string $postcode): bool
    {
        return DB::table('au_localities as l')->join('au_states as s', 's.id', '=', 'l.state_id')
            ->join('au_locality_postcodes as p', 'p.locality_id', '=', 'l.id')
            ->where('s.code', $state)->where('l.name', trim($suburb))->where('p.postcode', $postcode)->exists();
    }

    /** Customer-facing problem with an address's state/suburb/postcode, or null when it is a real combination (or no dataset yet). */
    public static function problem(string $state, string $suburb, string $postcode): ?string
    {
        if (! self::available() || self::matches($state, $suburb, $postcode)) {
            return null;
        }
        $inState = DB::table('au_localities as l')->join('au_states as s', 's.id', '=', 'l.state_id')->where('s.code', $state)->where('l.name', trim($suburb))->exists();
        if (! $inState) {
            return 'We could not find "'.trim($suburb)."\" in {$state}. Please choose your suburb from the list.";
        }

        return 'This postcode does not belong to the selected suburb. Please choose the correct postcode.';
    }

    private static function like(string $s): string
    {
        return addcslashes($s, '\\%_');
    }

    /** "MOUNT ISA" → "Mount Isa", "O'CONNOR" → "O'Connor", "MCDOWALL" → "McDowall". */
    public static function titleCase(string $name): string
    {
        $t = mb_convert_case(mb_strtolower(trim($name), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $t = preg_replace_callback("/(?<=['\\-])\\p{Ll}/u", fn ($m) => mb_strtoupper($m[0], 'UTF-8'), $t);
        $t = preg_replace_callback('/\bMc(\p{Ll})/u', fn ($m) => 'Mc'.mb_strtoupper($m[1], 'UTF-8'), $t);

        return preg_replace_callback('/\b(Ii|Iii|Iv)\b/u', fn ($m) => strtoupper($m[1]), $t);
    }
}
