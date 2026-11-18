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

# The passwords are read inside psql with \getenv (psql 15+), never put on a command line (`ps` shows arguments)
psql_major=$(psql --version | sed -n 's/^psql (PostgreSQL) \([0-9][0-9]*\).*/\1/p')
[ "${psql_major:-0}" -ge 15 ] || fail "psql ${psql_major:-?} juda eski: 15 yoki yangisi kerak (postgres:17-alpine obrazidagi psql)."

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

# 1. Roles, passwords (= rotation), connect and schema rights. Passwords travel as psql variables read from the
# environment (\getenv), quoted by psql (:'var'), never in the SQL text or on a command line.
for role in "$OWNER" "$APP"; do
    if [ "$(printf '%s\n' "SELECT count(*) FROM pg_roles WHERE rolname = :'r'" | q -v r="$role")" = "0" ]; then
        say "rol yaratiladi: $role"
    fi
done
q -v owner="$OWNER" -v app="$APP" -v db="$PGDATABASE" <<'SQL'
\getenv owner_pw DB_OWNER_PASSWORD
\getenv app_pw DB_PASSWORD
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
-- The app must not be able to SET ROLE to the owner: a membership granted by hand is taken back
SELECT format('REVOKE %I FROM %I', :'owner', :'app') FROM pg_auth_members m
 WHERE m.roleid = (SELECT oid FROM pg_roles WHERE rolname = :'owner') AND m.member = (SELECT oid FROM pg_roles WHERE rolname = :'app') \gexec
-- pg_ls_archive_statusdir() and other sessions' pg_stat_activity rows (Monitoring, PostgresStats)
GRANT pg_monitor TO :"app";
SQL
say "rollar va parollar: $OWNER (egasi), $APP (ilova, pg_monitor)"
if [ "$(printf '%s\n' "SELECT pg_has_role(:'app', :'owner', 'MEMBER')" | q -v app="$APP" -v owner="$OWNER")" = "t" ]; then
    printf "db-roles: OGOHLANTIRISH: %s boshqa rol orqali %s ning a'zosi (SET ROLE qila oladi): o'sha rol a'zoligini qo'lda olib tashlang. app:check-production ilovani ishga tushirmaydi.\n" "$APP" "$OWNER" >&2
fi

# 2. Ownership: every object of the schema that rizqim_owner does not own. ALTER TABLE on a partitioned parent does not
# reach its partitions: each partition (and each detached month) is its own row here. Sequences owned by a column and
# indexes follow their table; objects of an extension are left alone.
list=$(mktemp)
trap 'rm -f "$list"' EXIT
tab=$(printf '\t')
# One line per object: its name, a tab, the statement that moves it
q -F "$tab" -v owner="$OWNER" > "$list" <<'SQL'
WITH o AS (SELECT oid FROM pg_roles WHERE rolname = :'owner'),
ext AS (SELECT classid, objid FROM pg_depend WHERE deptype = 'e')
SELECT name, stmt FROM (
    SELECT 1 AS k, c.relispartition::int AS p, format('%I.%I', n.nspname, c.relname) AS name,
           format('ALTER %s %I.%I OWNER TO %I',
                  CASE c.relkind WHEN 'v' THEN 'VIEW' WHEN 'm' THEN 'MATERIALIZED VIEW' WHEN 'S' THEN 'SEQUENCE' WHEN 'f' THEN 'FOREIGN TABLE' ELSE 'TABLE' END,
                  n.nspname, c.relname, :'owner') AS stmt
      FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
     WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')
       AND c.relowner <> (SELECT oid FROM o)
       AND (c.relkind <> 'S' OR NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid = 'pg_class'::regclass AND d.objid = c.oid AND d.refclassid = 'pg_class'::regclass AND d.deptype IN ('a', 'i')))
       AND NOT EXISTS (SELECT 1 FROM ext WHERE ext.classid = 'pg_class'::regclass AND ext.objid = c.oid)
    UNION ALL
    SELECT 2, 0, p.oid::regprocedure::text,
           format('ALTER %s %s OWNER TO %I', CASE p.prokind WHEN 'p' THEN 'PROCEDURE' WHEN 'a' THEN 'AGGREGATE' ELSE 'FUNCTION' END, p.oid::regprocedure, :'owner')
      FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
     WHERE n.nspname = 'public' AND p.proowner <> (SELECT oid FROM o)
       AND NOT EXISTS (SELECT 1 FROM ext WHERE ext.classid = 'pg_proc'::regclass AND ext.objid = p.oid)
    UNION ALL
    SELECT 3, 0, format('%I.%I', n.nspname, t.typname), format('ALTER TYPE %I.%I OWNER TO %I', n.nspname, t.typname, :'owner')
      FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
     WHERE n.nspname = 'public' AND t.typtype IN ('e', 'd', 'r', 'm') AND t.typowner <> (SELECT oid FROM o)
       AND NOT EXISTS (SELECT 1 FROM ext WHERE ext.classid = 'pg_type'::regclass AND ext.objid = t.oid)
) s
ORDER BY k, p, name
SQL

moved=0
# One short transaction per object: ALTER ... OWNER takes ACCESS EXCLUSIVE on it for an instant, so a till's read or
# write queues behind it for at most lock_timeout (2 s). An object whose lock cannot be had (usually the nightly
# pg_dump, which holds a read lock on every table for its whole run) is tried again in later passes, 5 s apart. No
# object is tried once MOVE_DEADLINE seconds have passed since the start (checked before each one): 300 s outlasts a
# pilot-sized pg_dump [E] and still lets a deploy go on (the stack waits for this service), so the whole step ends
# within about 300 s + one lock_timeout. Whatever is left is named at the end. An object dropped meanwhile counts as done.
MOVE_DEADLINE=300
pending=$(mktemp)
retry=$(mktemp)
trap 'rm -f "$list" "$pending" "$retry"' EXIT
cp "$list" "$pending"
start=$(date +%s)
while :; do
    : > "$retry"
    while IFS="$tab" read -r name stmt; do
        [ -n "$stmt" ] || continue
        if [ $(($(date +%s) - start)) -ge "$MOVE_DEADLINE" ]; then
            printf '%s\t%s\n' "$name" "$stmt" >> "$retry"
            continue
        fi
        if err=$(q -c "SET lock_timeout = '2s'; $stmt" 2>&1); then
            say "ko'chirildi: ${stmt#ALTER }"
            moved=$((moved + 1))
        else
            case "$err" in
                *'does not exist'*) say "o'chirilgan, o'tkazib yuborildi: $name" ;;
                *) printf '%s\t%s\n' "$name" "$stmt" >> "$retry"; last_err=$err ;;
            esac
        fi
    done < "$pending"
    [ -s "$retry" ] || break
    if [ $(($(date +%s) - start)) -ge "$MOVE_DEADLINE" ]; then
        break
    fi
    say "$(wc -l < "$retry" | tr -d ' ') ta obyekt band (${last_err:-}), 5 s dan keyin qayta urinadi"
    sleep 5
    cp "$retry" "$pending"
done
failed=$(wc -l < "$retry" | tr -d ' ')

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
elif [ "$moved" -gt 0 ]; then
    say "egasi $OWNER ga o'tkazildi: $moved ta obyekt."
fi
if [ "$failed" -gt 0 ]; then
    # The stack still starts (the app's rights do not depend on who owns a table), but a migration that changes one of
    # these objects fails with "must be owner" and stops the backend from starting: run this again first
    names=$(cut -f1 "$retry" | paste -sd ',' - | sed 's/,/, /g')
    printf "db-roles: XATO: %s ta obyekt %s s ichida ko'chirilmadi (band: %s): %s. Ularni o'zgartiradigan migratsiya backend'ni to'xtatadi. Uzun tranzaksiya yo'qligini tekshirib qayta ishga tushiring: docker compose run --rm db-roles (docs/OPERATIONS.md, 22-bo'lim)\n" "$failed" "$MOVE_DEADLINE" "${last_err:-}" "$names" >&2
fi
say "tayyor: huquqlar va standart huquqlar qo'yildi."
