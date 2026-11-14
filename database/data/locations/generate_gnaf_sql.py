"""
Generate database/data/locations/gnaf-import.sql from Geoscape G-NAF (August 2026 release, 2026-08-17).

Downloads three tables (state, locality, address_detail) from the publicdata.au Parquet mirror of the official
data.gov.au release, builds the Australian state / locality / postcode reference data with DuckDB, validates it and
writes MySQL INSERT statements for the existing tables au_states, au_localities, au_locality_postcodes and
au_location_imports (schema: sql/proposed_changes.sql section 9).

This script never connects to a database and never touches customer addresses. It only writes one .sql file, and only
after every check has passed (the file is written to a temp name and renamed at the end, so a failed run leaves no
partial SQL behind).

Rules (they match `php artisan locations:import-australia` and au-localities.csv):
  * localities: current G-NAF localities (date_retired IS NULL) that have at least one postcode;
  * postcodes: postcodes of current addresses in that locality, plus the locality's primary_postcode;
  * names are display-cased like App\\Models\\Locations::titleCase(); source locality_pid is kept as source_pid;
  * state ids 1..9 = state codes in alphabetical order (same ids the artisan importer uses).

Usage:  python -I generate_gnaf_sql.py [--cache-dir DIR] [--out FILE]
Needs:  pip install duckdb

Attribution: Based on Geoscape G-NAF, (c) Geoscape Australia licensed by the Commonwealth of Australia under the
Open Geo-coded National Address File (G-NAF) End User Licence Agreement.
"""

import argparse
import hashlib
import os
import re
import sys
import tempfile
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

try:
    import duckdb
except ImportError:
    sys.exit('DuckDB is not installed. Run: python -m pip install duckdb')

RELEASE_DATE = '2026-08-17'
DATASET_VERSION = 'G-NAF August 2026'
BASE_URL = f'https://publicdata.au/d/gnaf/v/{RELEASE_DATE}/tables/'
TABLES = ['state', 'locality', 'address_detail']
SOURCE = 'Geoscape G-NAF (CC BY 4.0, Open G-NAF EULA) via publicdata.au'

EXPECTED = {'states': 9, 'localities': 15534, 'pairs': 15544}

# Same codes, names and ids as app/Console/Commands/ImportAustraliaLocations.php (id = alphabetical position).
STATES = {'ACT': 'Australian Capital Territory', 'NSW': 'New South Wales', 'NT': 'Northern Territory',
          'OT': 'Other Territories', 'QLD': 'Queensland', 'SA': 'South Australia', 'TAS': 'Tasmania',
          'VIC': 'Victoria', 'WA': 'Western Australia'}
STATE_IDS = {code: i + 1 for i, code in enumerate(STATES)}

ATTRIBUTION = ('Based on Geoscape G-NAF, (c) Geoscape Australia licensed by the Commonwealth of Australia under the '
               'Open Geo-coded National Address File (G-NAF) End User Licence Agreement.')
BATCH = 1000
UA = 'location-reference-generator/1.0 (python-urllib)'


def fail(msg):
    print(f'ERROR: {msg}', file=sys.stderr)
    print('No SQL was generated.', file=sys.stderr)
    sys.exit(1)


# ── Download ─────────────────────────────────────────────────────────────────────────────────────────────────────────

def remote_size(url):
    req = urllib.request.Request(url, method='HEAD', headers={'User-Agent': UA})
    with urllib.request.urlopen(req, timeout=60) as r:
        n = r.headers.get('Content-Length')
        return int(n) if n else None


def download(url, dest):
    """Download url to dest (reusing a cached copy of the right size). Stops the script on any failure."""
    try:
        size = remote_size(url)
        if dest.exists() and size is not None and dest.stat().st_size == size:
            print(f'  cached   {dest.name} ({size:,} bytes)')
            return
        part = dest.with_suffix(dest.suffix + '.part')
        req = urllib.request.Request(url, headers={'User-Agent': UA})
        got = 0
        with urllib.request.urlopen(req, timeout=120) as r, open(part, 'wb') as f:
            while chunk := r.read(1 << 20):
                f.write(chunk)
                got += len(chunk)
                if size:
                    print(f'\r  download {dest.name} {got * 100 // size:3d}%', end='', flush=True)
        print()
        if size is not None and got != size:
            part.unlink(missing_ok=True)
            fail(f'Download failed (incomplete: {got:,} of {size:,} bytes): {url}')
        os.replace(part, dest)
    except (urllib.error.URLError, OSError, ValueError) as e:
        fail(f'Download failed: {url}\n       {e}')


def sha256(path):
    h = hashlib.sha256()
    with open(path, 'rb') as f:
        while chunk := f.read(1 << 20):
            h.update(chunk)
    return h.hexdigest()


# ── Name casing: port of App\Models\Locations::titleCase() (PHP mb_convert_case MB_CASE_TITLE + fix-ups) ────────────

_CASE_IGNORABLE = set("'.:’·")


def title_case(name):
    s = name.strip().lower()
    out, prev_cased = [], False
    for ch in s:
        if ch.isalpha():
            out.append(ch.upper() if not prev_cased else ch)
            prev_cased = True
        else:
            out.append(ch)
            if ch not in _CASE_IGNORABLE:
                prev_cased = False
    t = ''.join(out)
    t = re.sub(r"(?<=['\-])([^\W\d_])", lambda m: m.group(1).upper(), t)
    t = re.sub(r'\bMc([^\W\d_])', lambda m: 'Mc' + m.group(1).upper(), t)
    return re.sub(r'\b(Ii|Iii|Iv)\b', lambda m: m.group(1).upper(), t)


# ── Build + validate ─────────────────────────────────────────────────────────────────────────────────────────────────

def build(cache):
    con = duckdb.connect()  # in-memory only
    for t in TABLES:
        p = (cache / f'{t}.parquet').as_posix().replace("'", "''")
        try:
            con.execute(f"CREATE VIEW {t} AS SELECT * FROM read_parquet('{p}')")
            con.execute(f'SELECT COUNT(*) FROM {t}').fetchone()
        except duckdb.Error as e:
            fail(f'{t}.parquet is not a readable Parquet file ({e}). Delete it from {cache} and re-run.')

    # Postcodes must be text, or leading zeros (0800) would already be lost.
    for table, col in [('locality', 'primary_postcode'), ('address_detail', 'postcode')]:
        typ = con.execute(f"SELECT column_type FROM (DESCRIBE {table}) WHERE column_name = '{col}'").fetchone()
        if not typ or typ[0] != 'VARCHAR':
            fail(f'{table}.{col} is {typ[0] if typ else "missing"}, expected VARCHAR (leading zeros would be lost).')

    states = con.execute('SELECT state_pid, state_abbreviation FROM state WHERE date_retired IS NULL').fetchall()
    codes = sorted(c for _, c in states)
    if codes != sorted(STATES):
        fail(f'Unexpected G-NAF states {codes}; expected {sorted(STATES)}.')

    con.execute("""
        CREATE TABLE pairs AS
        SELECT DISTINCT s.state_abbreviation AS state, l.locality_pid, l.locality_name, l.locality_class_code, x.postcode
        FROM locality l
        JOIN state s ON s.state_pid = l.state_pid AND s.date_retired IS NULL
        JOIN (
            SELECT locality_pid, trim(postcode) AS postcode FROM address_detail
             WHERE date_retired IS NULL AND postcode IS NOT NULL
            UNION
            SELECT locality_pid, trim(primary_postcode) FROM locality WHERE primary_postcode IS NOT NULL
        ) x ON x.locality_pid = l.locality_pid
        WHERE l.date_retired IS NULL
    """)
    rows = con.execute('SELECT * FROM pairs ORDER BY state, locality_name, locality_pid, postcode').fetchall()
    no_postcode = con.execute("""
        SELECT COUNT(*) FROM locality l WHERE l.date_retired IS NULL
           AND l.locality_pid NOT IN (SELECT locality_pid FROM pairs)""").fetchone()[0]

    localities, pairs, invalid = {}, [], 0
    for state, pid, name, cls, pc in rows:
        pid, name = (pid or '').strip(), (name or '').strip()
        if state not in STATES or not pid or len(pid) > 40 or not name or not re.fullmatch(r'\d{4}', pc or ''):
            invalid += 1
            print(f'  invalid row: {state!r} {pid!r} {name!r} {pc!r}', file=sys.stderr)
            continue
        loc = {'state': state, 'name': title_case(name)[:120], 'class': (cls or '').strip()[:1] or None}
        if pid in localities and localities[pid] != loc:
            fail(f'Locality {pid} has conflicting state/name/class in the source.')
        localities[pid] = loc
        pairs.append((pid, pc))

    if len(set(pairs)) != len(pairs):
        fail('Duplicate locality/postcode pairs after de-duplication (unexpected).')

    counts = {'states': len({l['state'] for l in localities.values()}), 'localities': len(localities),
              'pairs': len(pairs)}
    print(f"  states {counts['states']}, localities {counts['localities']:,}, suburb-postcode pairs "
          f"{counts['pairs']:,}, invalid {invalid}, localities without any postcode (excluded) {no_postcode:,}")
    bad = {k: (counts[k], v) for k, v in EXPECTED.items() if counts[k] != v}
    if invalid or bad:
        fail('Validation failed: ' + ', '.join(f'{k} = {got:,} (expected {exp:,})' for k, (got, exp) in bad.items())
             + (f'; {invalid} invalid row(s)' if invalid else ''))
    return localities, pairs, counts, no_postcode


# ── SQL ──────────────────────────────────────────────────────────────────────────────────────────────────────────────

def q(v):
    if v is None:
        return 'NULL'
    return "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"


def batched(prefix, values, f):
    for i in range(0, len(values), BATCH):
        f.write(prefix + '\n' + ',\n'.join(values[i:i + BATCH]) + ';\n')


def write_sql(out, localities, pairs, counts, no_postcode, hashes, urls):
    content_hash = hashlib.sha256(''.join(
        f"{localities[p]['state']}|{p}|{localities[p]['name']}|{localities[p]['class'] or ''}|{pc}\n"
        for p, pc in sorted(pairs)).encode('utf-8')).hexdigest()

    tmp = out.with_name(out.name + '.tmp')
    with open(tmp, 'w', encoding='utf-8', newline='\n') as f:
        f.write(f"""-- Australian state / locality / postcode reference data ({DATASET_VERSION}, released {RELEASE_DATE})
--
-- {ATTRIBUTION}
-- Licence: CC BY 4.0 plus the Open G-NAF Use Restriction (G-NAF must not be used to generate address lists for
-- sending mail without verifying each address against another source). Not Australia Post data.
--
-- Generated {datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')} UTC by database/data/locations/generate_gnaf_sql.py.
-- Do not edit by hand; re-run the script instead.
-- Sources (sha256):
""")
        for t in TABLES:
            f.write(f'--   {urls[t]}\n--     {hashes[t]}\n')
        f.write(f"""--
-- Contents: {counts['states']} states/territories, {counts['localities']:,} localities, {counts['pairs']:,} suburb-postcode pairs.
-- {no_postcode:,} current G-NAF localities have no postcode anywhere in the source and are not included.
--
-- Target tables must already exist (sql/proposed_changes.sql section 9). This file does no DDL on them, never
-- deletes rows and does not touch customer_addresses or any other table. Safe to run more than once:
--   au_states / au_localities: upsert (keyed by id/code and by the G-NAF locality_pid in source_pid);
--   au_locality_postcodes:     INSERT IGNORE on its (locality_id, postcode) primary key;
--   au_location_imports:       one audit row per run, with inserted/updated/skipped computed by the run itself.
-- Foreign keys are resolved by joining on au_states.code and au_localities.source_pid, never by assumed ids.
-- Session-only TEMPORARY tables (tmp_gnaf_*) are used for staging and a count guard, and are dropped at the end.
-- If any statement fails, the mysql client stops and the open transaction is rolled back.
-- Run e.g.: mysql --default-character-set=utf8mb4 -u USER -p DATABASE < gnaf-import.sql

SET NAMES utf8mb4;
SET @OLD_SQL_MODE = @@SESSION.sql_mode, SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

DROP TEMPORARY TABLE IF EXISTS tmp_gnaf_localities;
DROP TEMPORARY TABLE IF EXISTS tmp_gnaf_postcodes;
CREATE TEMPORARY TABLE tmp_gnaf_localities (
 source_pid VARCHAR(40) NOT NULL PRIMARY KEY,
 state_code VARCHAR(3) NOT NULL,
 name VARCHAR(120) NOT NULL,
 class_code CHAR(1) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TEMPORARY TABLE tmp_gnaf_postcodes (
 source_pid VARCHAR(40) NOT NULL,
 postcode CHAR(4) NOT NULL,
 PRIMARY KEY (source_pid, postcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

-- States (fixed ids, same as the artisan importer)
INSERT INTO au_states (id, code, name) VALUES
""")
        f.write(',\n'.join(f'({STATE_IDS[c]}, {q(c)}, {q(n)})' for c, n in STATES.items()))
        f.write('\nON DUPLICATE KEY UPDATE name = VALUES(name);\n\n-- Staging: localities\n')
        batched('INSERT INTO tmp_gnaf_localities (source_pid, state_code, name, class_code) VALUES',
                [f"({q(p)}, {q(l['state'])}, {q(l['name'])}, {q(l['class'])})"
                 for p, l in sorted(localities.items(), key=lambda kv: (kv[1]['state'], kv[1]['name'], kv[0]))], f)
        f.write('\n-- Staging: suburb-postcode pairs (postcodes are quoted text, leading zeros kept)\n')
        batched('INSERT INTO tmp_gnaf_postcodes (source_pid, postcode) VALUES',
                [f'({q(p)}, {q(pc)})' for p, pc in sorted(pairs)], f)
        f.write(f"""
-- Guard: staging must hold exactly the validated counts, otherwise everything below fails.
SET @staged_localities = (SELECT COUNT(*) FROM tmp_gnaf_localities);
SET @staged_pairs = (SELECT COUNT(*) FROM tmp_gnaf_postcodes);
SET @guard = IF(@staged_localities = {counts['localities']} AND @staged_pairs = {counts['pairs']}, 'ok', NULL);
CREATE TEMPORARY TABLE tmp_gnaf_guard (ok VARCHAR(2) NOT NULL);
INSERT INTO tmp_gnaf_guard VALUES (@guard);  -- NULL into NOT NULL: aborts on a count mismatch
DROP TEMPORARY TABLE tmp_gnaf_guard;

-- Change counts for the audit row (computed before the upsert)
SELECT COUNT(*), COALESCE(SUM(NOT (l.state_id <=> s.id AND l.name <=> t.name AND l.class_code <=> t.class_code)), 0)
  INTO @matched, @updated
  FROM tmp_gnaf_localities t
  JOIN au_states s ON s.code = t.state_code
  JOIN au_localities l ON l.source_pid = t.source_pid;

-- Localities: insert new, update changed (keyed by source_pid)
INSERT INTO au_localities (state_id, source_pid, name, class_code)
SELECT s.id, t.source_pid, t.name, t.class_code
  FROM tmp_gnaf_localities t
  JOIN au_states s ON s.code = t.state_code
ON DUPLICATE KEY UPDATE state_id = VALUES(state_id), name = VALUES(name), class_code = VALUES(class_code);

-- Suburb-postcode pairs: locality_id resolved through source_pid; existing pairs are skipped
INSERT IGNORE INTO au_locality_postcodes (locality_id, postcode)
SELECT l.id, p.postcode
  FROM tmp_gnaf_postcodes p
  JOIN au_localities l ON l.source_pid = p.source_pid;

-- Import audit row
INSERT INTO au_location_imports (source, dataset_version, release_date, file_sha256, states_count, localities_count,
                                 postcodes_count, inserted, updated, skipped, invalid)
VALUES ({q(SOURCE)}, {q(DATASET_VERSION)}, {q(RELEASE_DATE)}, {q(content_hash)}, {counts['states']}, {counts['localities']},
        {counts['pairs']}, {counts['localities']} - @matched, @updated, @matched - @updated, 0);

COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_gnaf_localities;
DROP TEMPORARY TABLE IF EXISTS tmp_gnaf_postcodes;
SET SESSION sql_mode = @OLD_SQL_MODE;

-- If the storefront caches locations, clear it afterwards: php artisan cache:clear
-- (file_sha256 above is the sha256 of the sorted "state|pid|name|class|postcode" rows, not of a source file.)
""")
    os.replace(tmp, out)


def main():
    here = Path(__file__).resolve().parent
    ap = argparse.ArgumentParser(description='Generate gnaf-import.sql from Geoscape G-NAF (August 2026).')
    ap.add_argument('--cache-dir', type=Path, default=Path(tempfile.gettempdir()) / f'gnaf-{RELEASE_DATE}',
                    help='where the downloaded Parquet files are kept (default: %(default)s)')
    ap.add_argument('--out', type=Path, default=here / 'gnaf-import.sql', help='output SQL file (default: %(default)s)')
    a = ap.parse_args()
    a.cache_dir.mkdir(parents=True, exist_ok=True)

    print(f'1/3 Downloading G-NAF {RELEASE_DATE} Parquet files to {a.cache_dir}')
    urls = {t: f'{BASE_URL}{t}.parquet' for t in TABLES}
    for t in TABLES:
        download(urls[t], a.cache_dir / f'{t}.parquet')
    hashes = {t: sha256(a.cache_dir / f'{t}.parquet') for t in TABLES}

    print('2/3 Building and validating with DuckDB')
    localities, pairs, counts, no_postcode = build(a.cache_dir)

    print(f'3/3 Writing {a.out}')
    write_sql(a.out, localities, pairs, counts, no_postcode, hashes, urls)
    print(f'Done: {a.out} ({a.out.stat().st_size:,} bytes)')
    print(ATTRIBUTION)


if __name__ == '__main__':
    main()
