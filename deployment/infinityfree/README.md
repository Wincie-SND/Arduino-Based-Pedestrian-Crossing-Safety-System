# InfinityFree deployment preparation

This project is Laravel 13 and requires PHP 8.3 or newer. InfinityFree free hosting is currently on PHP 8.4, but it does not provide SSH, Composer, Artisan, or cron jobs. The GitHub workflow builds Composer dependencies before uploading; for manual deployment, prepare them locally. Import the database through phpMyAdmin.

## Prepared files

- `CrossGuard-InfinityFree.zip`: production files and Composer dependencies, excluding development dependencies and local caches.
- `htdocs.htaccess`: the same root rewrite rule as the archive's `.htaccess`, for manual copy if needed.
- `.env.production.example`: copy to the project root on the host as `.env`, then replace every `YOUR_...` value. Never upload the local development `.env`.
- `database/schema/mysql-schema.sql`: schema and Laravel migration history only. It does not contain local users, passwords, incidents, or demo records.
- `create-owner-sql.ps1`: creates the first owner account's SQL locally. The password is entered securely and only its hash is written to the generated SQL file.

## Upload steps

1. Create a MySQL database in the InfinityFree control panel. Record the database host, database name, username, and password shown there; the host is not necessarily `localhost`.
2. Import `database/schema/mysql-schema.sql` into that new, empty database using phpMyAdmin.
3. Upload and extract `CrossGuard-InfinityFree.zip` directly into `htdocs`, so `app`, `bootstrap`, `public`, `vendor`, and `artisan` are inside `htdocs` rather than nested in another folder.
4. Upload `htdocs.htaccess` to `htdocs/.htaccess`. Keep Laravel's existing `public/.htaccess` unchanged.
5. Create `.env` from `.env.production.example` in the project root. Set the website URL, database values, and an application key generated locally with `php artisan key:generate --show`. Keep `APP_DEBUG=false`.
6. Make sure `storage` and `bootstrap/cache` are writable by PHP.
7. Configure a real SMTP provider before sending invitations or password reset emails. InfinityFree does not provide email accounts or PHP `mail()` delivery.
8. On your PC, run `powershell -ExecutionPolicy Bypass -File .\deployment\infinityfree\create-owner-sql.ps1`. Choose a unique owner password when prompted, then import the generated `deployment\infinityfree\initial-owner.sql` into the same new database through phpMyAdmin. This creates the initial CrossGuard administrator and crossing workspace. Do not import the local demo accounts; they use test credentials and are deliberately absent from the schema dump.
9. Open the HTTPS website and verify login, dashboard pages, invitations, and evidence links.

## Automatic deploys from GitHub

The workflow in `.github/workflows/tests.yml` runs the project's checks, then builds and uploads the production package to `tomeco.freedev.app/htdocs` on pushes to the `main` branch. You can also start it manually from GitHub Actions while `main` is selected. It uses FTPS on port 21. InfinityFree's FTP host and login are listed in the hosting account's **FTP Details**; use the exact values shown there. The common host is `ftpupload.net`.

Before pushing the workflow to GitHub, add these repository secrets:

1. Open the GitHub repository, then **Settings → Secrets and variables → Actions → New repository secret**.
2. Create `INFINITYFREE_FTP_SERVER` with the FTP host from InfinityFree's FTP Details.
3. Create `INFINITYFREE_FTP_USERNAME` with the FTP username from FTP Details.
4. Create `INFINITYFREE_FTP_PASSWORD` with the FTP password from FTP Details. Do not put this password, the database password, or `.env` contents in the repository.
5. Commit and push the workflow and deployment files to `main`. In GitHub, open **Actions** and check **CrossGuard checks and deployment** for the result. Deployment starts only after the checks pass.

The upload excludes `.env` and `storage`, so the live database settings and uploaded evidence remain on the server. It does not erase the whole website directory. The first successful run needs to reach the existing `/tomeco.freedev.app/htdocs` folder; if the FTP client shows a different folder path, update `server-dir` in the workflow to match it. Database schema changes are not applied by file uploads and still need to be imported through phpMyAdmin.

To deploy manually instead, use the numbered upload steps above. The generated ZIP and `initial-owner.sql` are local-only files and are excluded from Git.

## Hosting limits to account for

- There is no server-side Composer or Artisan command line. Future migrations require importing a fresh schema or migration SQL through phpMyAdmin.
- Scheduled tasks and queue workers cannot run. Queue work is configured as `sync`; expired invitation cleanup will not run automatically, though invitation expiry is still checked when an invite is used.
- The included root rewrite routes traffic through Laravel's `public` directory. The storage rewrite is included for evidence files because shared hosting does not support Laravel's usual storage symlink.
- Upload only the prepared release. Do not upload `node_modules`, the local `.env`, local logs, or local database data.
