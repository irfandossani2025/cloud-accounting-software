# Deploying to Plesk (no SSH)

Server limits this project is built around: **PHP 8.2**, **MariaDB 10.1**, **Node 17.9**, **no SSH**.

- **Node is never used on the server.** Assets are built on your Mac (`npm run build`) and `public/build/` is committed.
- **MariaDB 10.1** is older than Laravel 12's official minimum. `app/Database/LegacyMariaDbConnection.php` patches the one query that
  fails on 10.1 (column introspection), and all string indexes are 191 characters long. Do not use JSON columns, CTEs (`WITH ...`),
  window functions or `renameColumn()` in migrations.

## One-time setup

1. **PHP version**: Plesk → *Websites & Domains → PHP Settings* → **PHP 8.2**. Make sure the `pdo_mysql`, `mbstring`, `bcmath`,
   `intl`, `fileinfo` and `openssl` extensions are enabled.
2. **Database**: Plesk → *Databases → Add Database* (MariaDB). Note the name, user and password.
3. **Git**: Plesk → *Git → Add Repository*:
   - Remote repository: `https://github.com/irfandossani2025/cloud-accounting-software.git`. For a private repo, add
     Plesk's SSH deploy key to GitHub under *Settings → Deploy keys*.
   - Deployment mode: **Automatic**. Target directory: `httpdocs`.
4. **Document root**: Plesk → *Hosting Settings* → document root **`httpdocs/public`**.
   With **Laravel Toolkit**, steps 3–5 are one action: *Laravel → Install Application → Install from remote repository*.
   It clones the repo, runs Composer, sets the document root and generates `APP_KEY`.
5. **Composer dependencies** (pick whichever your Plesk offers):
   - **Laravel Toolkit** (preferred): add the application. It runs `composer install` and artisan commands from the web page.
   - **PHP Composer** extension: open `composer.json` and click *Install* (use the *no-dev* option).
   - **Neither**: run `composer install --no-dev --optimize-autoloader` on your Mac, then upload `vendor/` over FTP/File Manager.
6. **`.env`**: in File Manager, copy `.env.example` to `.env` and fill in:
   - `APP_URL` = your domain.
   - `APP_KEY`: generate it on your Mac with `php artisan key:generate --show` and paste the value.
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
   - `SETUP_TOKEN`: any long random string (optional; `/setup` locks itself once an administrator exists).
   - Keep `SESSION_DRIVER=file` and `CACHE_STORE=file` so the installer works before the tables exist.
   - If the database password contains `#`, `$` or spaces, wrap it in single quotes: `DB_PASSWORD='...'`.
     Simplest is a letters-and-numbers password for the database user.
7. **Permissions**: `storage/` and `bootstrap/cache/` must be writable by the site's system user (File Manager → *Change Permissions*: 775).
8. **Install**: open `https://your-domain/setup?token=<SETUP_TOKEN>`:
   1. click **Install database** (creates the tables);
   2. enter the company details and the administrator account.

   `/setup` locks itself permanently once an administrator exists.

## Every update

**Live setup (accounts.techittechnologies.com):** Laravel Toolkit → *Deployment* is set to **Automatic**, and GitHub has a
webhook to Plesk, so every push to `main` deploys by itself (maintenance mode, git pull, `composer install`).
Step 5 *Install package.json dependencies* must stay **off**: the server's Node 17.9 cannot run the build tools, and the
built assets are committed. The deployment script (step 6) needs SSH, so **migrations do not run automatically**: after a
push that adds a migration, run `migrate --force` in Laravel Toolkit → *Artisan*.

1. On your Mac: `npm run build` (if views/CSS/JS changed), `php artisan test`, then commit and push to `main`.
2. Plesk pulls automatically. If `composer.json` changed, run Composer again (step 5).
3. If there are new migrations, run `php artisan migrate --force`. Use Laravel Toolkit → *Artisan*, or put it in Plesk Git →
   *Additional deployment actions*:

   ```
   /opt/plesk/php/8.2/bin/php artisan migrate --force
   /opt/plesk/php/8.2/bin/php artisan optimize
   ```

   (The PHP path can differ. Plesk shows it under *PHP Settings*.)

## Backups

1. **Plesk:** *Backup Manager* → schedule daily backups that include databases.
2. **In the app:** *Gateway → Year-end, period lock & backup → Download backup (.sql)* (administrators only). It contains
   all company data as `INSERT` statements.

To restore an app backup: create an empty database, point `.env` at it, run the installer's *Install database* step (or
`php artisan migrate --force`) so the tables exist, **do not** complete the company setup, then import the `.sql` file
with Plesk → *Databases → phpMyAdmin → Import*.

## Year-end

No closing entries are needed. After finalising a year: download a backup, then *Close & lock year* on the year-end page.
Lock individual months the same way after filing a VAT return. Locked dates cannot be posted to, altered or cancelled.
