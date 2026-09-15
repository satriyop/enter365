# Production & Remote Ops

CLI tools to provision, deploy, and operate Enter365 on an Ubuntu server with Caddy and PostgreSQL.

## Configuration

Before running remote commands, configure your deployment target in `.env.deploy` (gitignored):

```bash
cp scripts/prod/templates/env.deploy.example .env.deploy
```

Fill in your server configuration:
```bash
AIDEV_IP=your-server-ip
AIDEV_HOST=root@aidev
DOMAIN=enter365.example.com
SSH_KEY_FILE=~/.ssh/id_ed25519
```

## Architecture

Same origin deployment:
- **Caddy** serves the compiled SPA frontend and proxies `/api`, `/up`, `/storage`, `/pos/kopitiam` to Laravel PHP-FPM pool.
- **PostgreSQL** runs on the server (or managed database).
- **Supervisor** manages Laravel queue workers and background tasks.

## Usage

```bash
./scripts/prod.sh help
```

### Initial Provisioning
1. Setup SSH configuration:
   ```bash
   ./scripts/prod.sh ssh-config
   ```
2. Provision system packages, PHP-FPM pool, and Postgres DB (additive):
   ```bash
   ./scripts/prod.sh provision
   ```
3. Initialize environment and generate application key:
   ```bash
   ./scripts/prod.sh env-init
   ```
4. Deploy code and run migrations:
   ```bash
   ./scripts/prod.sh deploy
   ```
5. Check deployment status:
   ```bash
   ./scripts/prod.sh health
   ```
