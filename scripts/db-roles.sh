#!/bin/sh
# RIZQIM: baza rollari (M4, docs/scale/M4_DESIGN.md 5-bo'lim; docs/OPERATIONS.md, «Baza rollari»).
#   rizqim_owner (DB_OWNER_USERNAME): sxemadagi hamma obyektlarning egasi; migratsiyalar va db:partitions:* shu rol bilan.
#   rizqim_app   (DB_USERNAME): faqat ma'lumot o'qish-yozish (SELECT/INSERT/UPDATE/DELETE) + pg_monitor; php-fpm
#                (PgBouncer orqali), queue, scheduler, reverb shu rol bilan ishlaydi.
# Bootstrap superuser (POSTGRES_USER) o'zgarmaydi: postgres konteyneri, backup, tiklash mashqi va shu skript.
#
# `db-roles` compose xizmati (bir martalik) har `docker compose up` da ishga tushiradi; qo'lda:
#   docker compose run --rm db-roles
# Ulanish: PGHOST/PGPORT/PGUSER/PGPASSWORD (bootstrap superuser), baza DB_DATABASE (yoki PGDATABASE).
# Har ishga tushishda (qayta ishga tushirish xavfsiz):
#   - yo'q rollarni yaratadi, parollarni env'dan qo'yadi (parolni almashtirish = env'ni o'zgartirib qayta ishga tushirish);
#   - huquqlar va egasi keyin yaratadigan obyektlar uchun standart huquqlar (ALTER DEFAULT PRIVILEGES);
#   - rizqim_owner'niki bo'lmagan har obyekt (jadval, har bir oylik bo'lak alohida, ko'rinish, ketma-ketlik, funksiya)
#     egasini rizqim_owner ga o'tkazadi: har biri alohida, lock_timeout 2s va 3 urinish (kassa yozuvi 2 s dan ortiq
#     kutmaydi). Nimani ko'chirganini chiqaradi; ikkinchi ishga tushishda "ko'chiriladigan obyekt yo'q".
# DB_OWNER_USERNAME bo'sh = rollar ajratilmagan (M4 dan oldingidek bitta rol): hech narsa qilmaydi, 0 bilan chiqadi.
set -eu

say() { printf 'db-roles: %s\n' "$*"; }
fail() { printf 'db-roles: XATO: %s\n' "$*" >&2; exit 1; }

OWNER=${DB_OWNER_USERNAME:-}
APP=${DB_USERNAME:-}
if [ -z "$OWNER" ]; then
    say "DB_OWNER_USERNAME bo'sh: rollar ajratilmagan (bitta rol, avvalgidek). Hech narsa qilinmadi."
    exit 0
fi
[ -n "$APP" ] || fail "DB_USERNAME bo'sh: ilova roli (rizqim_app) nomini bering."
[ -n "${DB_OWNER_PASSWORD:-}" ] || fail "DB_OWNER_PASSWORD bo'sh: jadvallar egasi roli uchun parol bering."
[ -n "${DB_PASSWORD:-}" ] || fail "DB_PASSWORD bo'sh: ilova roli uchun parol bering."
for name in "$OWNER" "$APP"; do
    case "$name" in
        *[!a-z0-9_]* | [0-9]*) fail "rol nomi '$name' faqat kichik lotin harf, raqam va '_' dan iborat bo'lsin (raqam bilan boshlanmasin)." ;;
    esac
done
[ "$OWNER" != "$APP" ] || fail "DB_USERNAME va DB_OWNER_USERNAME bir xil ($APP): ilova jadvallar egasi bo'lmasligi kerak."
PGDATABASE=${DB_DATABASE:-${PGDATABASE:-}}
[ -n "$PGDATABASE" ] || fail "DB_DATABASE bo'sh."
export PGDATABASE

q() { psql -X -v ON_ERROR_STOP=1 -Atq "$@"; }

me=$(q -c "SELECT current_user || '|' || rolsuper::text FROM pg_roles WHERE rolname = current_user") \
    || fail "bazaga ulanib bo'lmadi (PGHOST=${PGHOST:-soket} PGUSER=${PGUSER:-?} baza $PGDATABASE)."
bootstrap=${me%|*}
case "$me" in
    *'|true') ;;
    *) fail "$bootstrap superuser emas: rollar va egalikni faqat bootstrap superuser (POSTGRES_USER/POSTGRES_PASSWORD) o'zgartira oladi. Hech narsa o'zgartirilmadi." ;;
esac
if [ "$bootstrap" = "$OWNER" ] || [ "$bootstrap" = "$APP" ]; then
    fail "bootstrap superuser ($bootstrap) DB_USERNAME yoki DB_OWNER_USERNAME bilan bir xil: ularga boshqa nom bering (rizqim_owner, rizqim_app), POSTGRES_USER o'zgarmaydi."
fi

# 1. Roles, passwords (= rotation), connect and schema rights. Passwords travel as psql variables, never in the text.
for role in "$OWNER" "$APP"; do
    if [ "$(printf '%s\n' "SELECT count(*) FROM pg_roles WHERE rolname = :'r'" | q -v r="$role")" = "0" ]; then
        say "rol yaratiladi: $role"
    fi
done
q -v owner="$OWNER" -v app="$APP" -v owner_pw="$DB_OWNER_PASSWORD" -v app_pw="$DB_PASSWORD" -v db="$PGDATABASE" <<'SQL'
SET client_min_messages = warning;
SELECT format('CREATE ROLE %I', :'owner') WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'owner') \gexec
SELECT format('CREATE ROLE %I', :'app') WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app') \gexec
ALTER ROLE :"owner" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD :'owner_pw';
ALTER ROLE :"app" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD :'app_pw';
GRANT CONNECT ON DATABASE :"db" TO :"owner", :"app";
-- Nobody but the owner creates objects in the schema (PostgreSQL before 15 gave PUBLIC CREATE on public)
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO :"owner";
GRANT USAGE ON SCHEMA public TO :"app";
REVOKE CREATE ON SCHEMA public FROM :"app";
-- pg_ls_archive_statusdir() and other sessions' pg_stat_activity rows (Monitoring, PostgresStats)
GRANT pg_monitor TO :"app";
SQL
say "rollar va parollar: $OWNER (egasi), $APP (ilova, pg_monitor)"

# 2. Ownership: every object of the schema that rizqim_owner does not own. ALTER TABLE on a partitioned parent does not
# reach its partitions: each partition (and each detached month) is its own row here. Sequences owned by a column and
# indexes follow their table; objects of an extension are left alone.
list=$(mktemp)
trap 'rm -f "$list"' EXIT
q -v owner="$OWNER" > "$list" <<'SQL'
WITH o AS (SELECT oid FROM pg_roles WHERE rolname = :'owner'),
ext AS (SELECT classid, objid FROM pg_depend WHERE deptype = 'e')
SELECT stmt FROM (
    SELECT 1 AS k, c.relispartition::int AS p, c.relname::text AS name,
           format('ALTER %s %I.%I OWNER TO %I',
                  CASE c.relkind WHEN 'v' THEN 'VIEW' WHEN 'm' THEN 'MATERIALIZED VIEW' WHEN 'S' THEN 'SEQUENCE' WHEN 'f' THEN 'FOREIGN TABLE' ELSE 'TABLE' END,
                  n.nspname, c.relname, :'owner') AS stmt
      FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
     WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')
       AND c.relowner <> (SELECT oid FROM o)
       AND (c.relkind <> 'S' OR NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass AND d.objid = c.oid AND d.refclassid = 'pg_class'::regclass AND d.deptype IN ('a', 'i')))
       AND NOT EXISTS (SELECT 1 FROM ext WHERE ext.classid = 'pg_class'::regclass AND ext.objid = c.oid)
    UNION ALL
    SELECT 2, 0, p.proname::text,
           format('ALTER %s %s OWNER TO %I', CASE p.prokind WHEN 'p' THEN 'PROCEDURE' WHEN 'a' THEN 'AGGREGATE' ELSE 'FUNCTION' END, p.oid::regprocedure, :'owner')
      FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
     WHERE n.nspname = 'public' AND p.proowner <> (SELECT oid FROM o)
       AND NOT EXISTS (SELECT 1 FROM ext WHERE ext.classid = 'pg_proc'::regclass AND ext.objid = p.oid)
    UNION ALL
    SELECT 3, 0, t.typname::text, format('ALTER TYPE %I.%I OWNER TO %I', n.nspname, t.typname, :'owner')
      FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
     WHERE n.nspname = 'public' AND t.typtype IN ('e', 'd', 'r', 'm') AND t.typowner <> (SELECT oid FROM o)
       AND NOT EXISTS (SELECT 1 FROM ext WHERE ext.classid = 'pg_type'::regclass AND ext.objid = t.oid)
) s
ORDER BY k, p, name
SQL

moved=0
failed=0
while IFS= read -r stmt; do
    [ -n "$stmt" ] || continue
    attempt=1
    while :; do
        # One short transaction per object: ALTER ... OWNER takes ACCESS EXCLUSIVE on it for an instant; a till's
        # write queues behind it for at most lock_timeout, then this object is tried again
        if err=$(q -c "SET lock_timeout = '2s'; $stmt" 2>&1); then
            say "ko'chirildi: ${stmt#ALTER }"
            moved=$((moved + 1))
            break
        fi
        if [ "$attempt" -ge 3 ]; then
            printf 'db-roles: OGOHLANTIRISH: ko'"'"'chirilmadi (%s): %s\n' "$err" "$stmt" >&2
            failed=$((failed + 1))
            break
        fi
        attempt=$((attempt + 1))
        sleep 2
    done
done < "$list"

# 3. Rights on what exists now, and on what the owner creates later (migrations, new monthly partitions)
q -v owner="$OWNER" -v app="$APP" <<'SQL'
SET client_min_messages = warning;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"app";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO :"app";
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO :"app";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO :"app";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public GRANT EXECUTE ON FUNCTIONS TO :"app";
SQL

if [ "$moved" -eq 0 ] && [ "$failed" -eq 0 ]; then
    say "ko'chiriladigan obyekt yo'q: hammasi $OWNER niki."
else
    say "egasi $OWNER ga o'tkazildi: $moved ta obyekt."
fi
if [ "$failed" -gt 0 ]; then
    # The application works meanwhile (its rights do not depend on who owns a table); only a migration or a partition
    # step on such an object would fail, loudly. Not a failure of the service: the backend must still start.
    printf "db-roles: OGOHLANTIRISH: %s ta obyekt band edi va ko'chirilmadi. Tinch paytda qayta ishga tushiring: docker compose run --rm db-roles\n" "$failed" >&2
fi
say "tayyor: huquqlar va standart huquqlar qo'yildi."
