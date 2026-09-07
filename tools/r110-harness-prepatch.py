from pathlib import Path
p=Path('tests/file10-r21-r40-contracts.sh')
t=p.read_text()
old='need "unset(\\$r[\'id\'],\\$r[\'channel_id\'],\\$r[\'owner_id\'],\\$r[\'thumbnail_id\'])" "$P/includes/class-vwlb-repository.php" r22-strip-internal-ids'
new='need "unset(\\$r[\'id\'],\\$r[\'channel_id\'],\\$r[\'owner_id\'],\\$r[\'thumbnail_id\']" "$P/includes/class-vwlb-repository.php" r22-strip-internal-ids'
if t.count(old)!=1: raise SystemExit(f'R22 historical assertion anchor count={t.count(old)}')
p.write_text(t.replace(old,new,1))
