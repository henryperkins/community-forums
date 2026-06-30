# Production-Like Evidence Profile

This profile is the Phase 2/3 closeout target for evidence that should not rely
on PHP's built-in server.

## Stack

- `php:8.4-fpm-bookworm`
- `nginx:1.27-alpine`
- `mariadb:11`
- `grafana/k6:0.53.0`

The web app is exposed at `http://127.0.0.1:8021`. MariaDB is exposed on
`127.0.0.1:3321` so the browser harness can reset and seed the evidence DB before
each prodlike run.

## Run

```bash
docker compose -f tests/prodlike/compose.yml up -d --build
cd tests/browser
npm run evidence:prodlike
npm run evidence:dark:prodlike
npm run a11y:prodlike
cd ../..
docker compose -f tests/prodlike/compose.yml run --rm k6 run /scripts/phase3-load.js
```

The browser scripts reset `retroboards_prodlike`, run migrations, seed deterministic
fixtures, and then point Playwright at the running Nginx/PHP-FPM app.

For queue-worker smoke in the same runtime profile, exec the commands inside the
running app container instead of using the host PHP binary:

```bash
docker compose -f tests/prodlike/compose.yml exec -T app php bin/console worker:email 100
docker compose -f tests/prodlike/compose.yml exec -T app php bin/console worker:digest
docker compose -f tests/prodlike/compose.yml exec -T app php bin/console worker:drafts
docker compose -f tests/prodlike/compose.yml exec -T app php bin/console worker:attachments
docker compose -f tests/prodlike/compose.yml exec -T app php bin/console worker:attachment-scans 60
docker compose -f tests/prodlike/compose.yml exec -T app php bin/console worker:webhooks 100
docker compose -f tests/prodlike/compose.yml exec -T app php bin/console worker:extensions 100
```

If you also rerun `tests/backup/rehearse.sh` while this stack is up, set
`BACKUP_REHEARSAL_PORT` to a free port such as `8031` or stop the stack first,
because Nginx already binds `127.0.0.1:8021`.

The k6 script writes `docs/evidence/phase3-load/phase3-load-summary.json`.
