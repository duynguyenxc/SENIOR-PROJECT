# Veg Buffet (Senior Project)

## Prereqs
- Docker Desktop installed
- Docker Desktop running (daemon must be on)

## Start
From `d:\Senior-Project`:

```bash
docker compose up -d --build
```

## URLs
- Website: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

## Database credentials (local dev)
- Host (from containers): `db`
- Host (from your PC tools): `127.0.0.1`
- Port: `3306`
- DB: `vegbuffet`
- App user: `app` / `apppass`
- Root user: `root` / `rootpass`

## Reset database (re-run init SQL)
Init SQL in `db/init` runs only on first boot of the volume. To reset:

```bash
docker compose down -v
docker compose up -d --build
```

## Internal account seed
- Super admin account: `admin@vegbuffet.com`
- Password: `password`

Staff accounts are managed inside the app by the super admin.

## Render deployment (free tier)
This repo is prepared for a free Render web service. Because free Render does not include private services or persistent disks, the free setup is intentionally web-only and expects an external MySQL or MariaDB database.

### Included Render files
- `render.yaml`: Blueprint that creates a single free Docker web service
- `web/Dockerfile`: Builds a production image that includes the app source
- `web/render-start.sh`: Ensures the uploads directory exists before Apache starts

### What you need before deploying
Prepare an external MySQL or MariaDB database first. You will need:
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Then import these SQL files into that database:
- `db/init/01_schema.sql`
- `db/init/02_seed.sql`

After importing, the default super admin account is:
- `admin@vegbuffet.com`
- `password`

### Deploy on Render
1. Push this repo to GitHub.
2. In Render, choose **New +** -> **Blueprint**.
3. Select the repo and let Render read `render.yaml`.
4. When Render asks for environment values, fill in:
   - `APP_URL`
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   - `DB_SSL_CA_PATH`
   - `DB_SSL_VERIFY_SERVER_CERT`
   - `STRIPE_SECRET_KEY`
   - `STRIPE_PUBLISHABLE_KEY`
5. Create the Blueprint and wait for the web service to deploy.
6. After the service is live, verify the real Render URL and update `APP_URL` if needed.
7. Redeploy once after fixing `APP_URL` so Stripe success and cancel redirects use the right domain.

### Important free-tier notes
- Free Render web services spin down after inactivity, so the first request after idle can be slow.
- Free Render does not provide persistent storage. Any new files written to `web/uploads` can disappear after a redeploy or restart.
- Because uploads are temporary on free tier, avoid relying on long-term image uploads in the admin panel unless you later move to paid Render or external object storage.
- The app must be connected to an external database because free Render does not support the private MariaDB service used by the paid setup.
- For TiDB Cloud Starter, keep TLS enabled by setting `DB_SSL_CA_PATH=/etc/ssl/certs/ca-certificates.crt` and `DB_SSL_VERIFY_SERVER_CERT=true`.
- If you change the Render domain or attach a custom domain later, update `APP_URL` in the Render dashboard.

