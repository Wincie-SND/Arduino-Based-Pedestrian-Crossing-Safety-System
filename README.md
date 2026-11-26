# TOMECO CrossGuard

Pedestrian crossing monitoring and incident management for authorized TOMECO personnel.

## Workspace roles

Admins and Enforcers can monitor crossings, view notifications, and review or export violation reports for their workspace.

- **Admin** creates Enforcer accounts and manages workspace access from **User Management**.

To create an Enforcer account, enter the person's name, email address, and initial password in **User Management**. The account is assigned to the current workspace. The Enforcer signs in and verifies their email address to access the workspace.

Run `php artisan migrate` when upgrading an existing installation. The migrations rename Owner to Admin, rename the former Admin role to Enforcer, and convert any remaining Member memberships and invitations to Enforcer.

## Run locally

Install the PHP dependencies, configure the `.env` file, and run the database migrations. Start the application with:

```sh
php artisan serve
```

Open `http://127.0.0.1:8000` in your browser. The interface is rendered by Laravel Blade and uses static files from `public/`; no Node.js or Vite process is needed.

## Use the crossing camera

1. Sign in, open the crossing dashboard, and click **Start camera**.
2. Allow camera access when prompted. Aim the default camera on the computer running the browser at the crossing. Open the dashboard at `http://localhost:8000`, `http://127.0.0.1:8000`, or your HTTPS deployment.
3. Keep the dashboard tab visible while monitoring. When CrossGuard receives a new Arduino incident, the camera module saves a still image to that incident under **Violation reports**. Click **Stop camera** when you finish monitoring.

The image shows what the camera sees when the incident reaches the website. A delayed Arduino report may arrive after the crossing scene has changed. Only members of the crossing team can open snapshots through the website.

## Use the Laragon database

The project is configured to use Laragon's local MySQL server at `127.0.0.1:3306`. Start MySQL from Laragon, then create a database named `tomeco_crossguard` with the `utf8mb4_unicode_ci` collation in HeidiSQL or phpMyAdmin. The default Laragon connection is `root` with a blank password; update the `DB_*` values in `.env` if your MySQL credentials differ.

From the project directory, create the tables and optional local demo data with:

```sh
php artisan migrate --seed
```

Then refresh the database list in HeidiSQL or phpMyAdmin to browse the `tomeco_crossguard` tables.
