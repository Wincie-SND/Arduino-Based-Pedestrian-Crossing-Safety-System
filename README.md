# TOMECO CrossGuard

Pedestrian crossing monitoring and incident management for authorized TOMECO personnel.

## Run locally

Install the PHP dependencies, configure the `.env` file, and run the database migrations. Start the application with:

```sh
php artisan serve
```

Open `http://127.0.0.1:8000` in your browser. The interface is rendered by Laravel Blade and uses static files from `public/`; no Node.js or Vite process is needed.
