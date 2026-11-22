# TOMECO CrossGuard

Pedestrian crossing monitoring and incident management for authorized TOMECO personnel.

## Run locally

Install the PHP dependencies, configure the `.env` file, and run the database migrations. Start the application with:

```sh
php artisan serve
```

Open `http://127.0.0.1:8000` in your browser. The interface is rendered by Laravel Blade and uses static files from `public/`; no Node.js or Vite process is needed.

## Use the Laragon database

The project is configured to use Laragon's local MySQL server at `127.0.0.1:3306`. Start MySQL from Laragon, then create a database named `tomeco_crossguard` with the `utf8mb4_unicode_ci` collation in HeidiSQL or phpMyAdmin. The default Laragon connection is `root` with a blank password; update the `DB_*` values in `.env` if your MySQL credentials differ.

From the project directory, create the tables and optional local demo data with:

```sh
php artisan migrate --seed
```

Then refresh the database list in HeidiSQL or phpMyAdmin to browse the `tomeco_crossguard` tables.
