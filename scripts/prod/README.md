# Production ops (aidev)

Laptop → `root@aidev` (`146.190.87.122`). Public URL: `https://enter365.pamungkas.org`.

Same origin: Caddy serves the Vue SPA and proxies `/api`, `/up`, `/storage`, `/pos/kopitiam` to Laravel. The till talks to `/api/v1` (Bearer Sanctum).

```bash
./scripts/prod.sh help
```

Aidev already runs Caddy + Sipamungkas + OpenClaw. `provision` is additive (new PHP pool, new Postgres DB, Caddy site import). It does not replace the existing Caddyfile.

SSH on this droplet bans bursty connections. The scripts reuse one ControlMaster. If port 22 refuses, wait a few minutes.

TLS sits behind Cloudflare. If Caddy cannot mint a cert, grey-cloud the A record for two minutes, reload Caddy, then orange-cloud again. Cloudflare SSL mode: **Full (strict)**.
