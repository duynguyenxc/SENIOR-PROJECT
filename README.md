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

## Render deployment
This repo is prepared for Render with Docker-based services and environment variables only.

### Included Render files
- `render.yaml`: Blueprint that creates:
  - `veg-buffet-db` as a MariaDB private service with a persistent disk
  - `veg-buffet-web` as the PHP/Apache web service with a persistent disk for uploaded images
- `db/Dockerfile`: Copies `db/init` into MariaDB so schema and seed run on the first database boot
- `web/Dockerfile`: Builds a production image that includes the app source
- `web/render-start.sh`: Ensures the uploads directory exists and is writable before Apache starts

### Deploy on Render
1. Push this repo to GitHub.
2. In Render, choose **New +** -> **Blueprint**.
3. Select the repo and let Render read `render.yaml`.
4. When Render asks for manual environment values, fill in:
   - `APP_URL`: your Render service URL, for example `https://veg-buffet-web.onrender.com`
   - `STRIPE_SECRET_KEY`
   - `STRIPE_PUBLISHABLE_KEY`
5. Create the Blueprint and wait for both services to finish deploying.
6. After the first deploy, open the web service URL and log in with:
   - `admin@vegbuffet.com`
   - `password`

### Important Render notes
- This setup uses persistent disks for MariaDB and uploaded images, so use a paid Render service type such as `Starter` or higher.
- The MariaDB init SQL runs only when the database disk is empty, just like local Docker volumes.
- Uploaded dish and takeout images are stored on the web service disk at `web/uploads`, so they persist across restarts and deploys.
- If you change the Render domain or attach a custom domain later, update `APP_URL` in the Render dashboard so Stripe success and cancel URLs stay correct.
- This project still uses the current lightweight Stripe success-page verification flow, so the app must be reachable at the same public URL configured in `APP_URL`.

