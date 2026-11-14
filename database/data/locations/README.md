# Australian locality / postcode reference data

- **Source:** Geoscape G-NAF (Geocoded National Address File), release **August 2026** (2026-08-17), via the
  <https://publicdata.au/d/gnaf/> parquet mirror of the official data.gov.au release.
- **Licence:** CC BY 4.0 plus the G-NAF "Open G-NAF Use Restriction". Attribution required:
  "Based on Geoscape G-NAF, © Geoscape Australia licensed by the Commonwealth of Australia under the Open Geo-coded
  National Address File (G-NAF) End User Licence Agreement." The restriction forbids using G-NAF to generate addresses or
  address lists *for sending mail* without verifying each address against another source. This table holds locality +
  postcode reference values only; the customer's street address is typed by the customer.
- **Not Australia Post data.** Australia Post's postcode file needs a paid commercial licence, which has not been
  obtained; nothing here is presented as Australia Post approved.
- `au-localities.csv` (one row per locality + postcode): state, locality_pid, locality_name, class_code, postcode, src.
  `src` = `address` (a current G-NAF address uses that postcode), `primary` (locality.primary_postcode) or `alias`.
  Postcodes are text (leading zeros kept, e.g. `0800`).
- `au-localities-without-postcode.csv`: current G-NAF localities with no postcode anywhere in the source (no addresses);
  they cannot be offered in the address form and are reported, not imported.
- `generate_gnaf_sql.py` downloads the G-NAF Aug 2026 `state`/`locality`/`address_detail` Parquet files, validates
  them (9 states, 15,534 localities, 15,544 suburb-postcode pairs) and writes `gnaf-import.sql`: idempotent INSERTs for
  `au_states`, `au_localities`, `au_locality_postcodes` and `au_location_imports`, as an alternative to the artisan
  importer. It never connects to a database. Run `python -m pip install duckdb` then `python -I generate_gnaf_sql.py`.
- `build_from_gnaf.py <out_dir>` regenerates both CSVs from a newer release (needs `pip install duckdb`; change the
  version in `B`). Then `php artisan locations:import-australia --dataset-version=... --release-date=...`.
