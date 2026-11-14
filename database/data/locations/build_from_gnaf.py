import sys; sys.path.insert(0, r'C:/Users/MSI/AppData/Local/Temp/claude/098addccaca3d072/4d3f711d-29b4-4788-96fd-a250963bfefb/scratchpad/pylib')
import duckdb
B='https://publicdata.au/d/gnaf/v/2026-08-17/tables/'
d=sys.argv[1]
c=duckdb.connect()
c.execute("INSTALL httpfs; LOAD httpfs;")
for t in ['state','locality','locality_alias']:
    c.execute(f"create table {t} as select * from read_parquet('{B}{t}.parquet')")
c.execute(f"create table ap as select locality_pid, postcode, count(*) n from read_parquet('{B}address_detail.parquet') where date_retired is null and postcode is not null group by 1,2")
c.execute("""create table out as
select s.state_abbreviation state, l.locality_pid, l.locality_name, l.locality_class_code class_code, x.postcode, x.src from locality l join state s using(state_pid)
join (select locality_pid, postcode, 'address' src from ap
 union select locality_pid, primary_postcode, 'primary' from locality where primary_postcode is not null
 union select locality_pid, postcode, 'alias' from locality_alias where postcode is not null and date_retired is null) x using(locality_pid)
where l.date_retired is null""")
c.execute("create table nopc as select s.state_abbreviation state, l.locality_pid, l.locality_name, l.locality_class_code class_code from locality l join state s using(state_pid) where l.date_retired is null and l.locality_pid not in (select locality_pid from out)")
print(c.execute("select count(*), count(distinct locality_pid) from out").fetchall(), c.execute("select count(*) from nopc").fetchall())
c.execute(f"copy out to '{d}/out.csv' (header)")
c.execute(f"copy nopc to '{d}/nopc.csv' (header)")
c.execute(f"copy state to '{d}/state.csv' (header)")
