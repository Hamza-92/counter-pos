# Tenant database commands

Run these commands from the deployed project directory containing `artisan`, using your server's PHP executable. The app reads the superadmin database configuration and registered tenant credentials automatically. Do not change `.env` for each tenant.

## Migrate every existing tenant

```bash
php artisan tenant:migrate-all
```

The command snapshots all registered tenants, including suspended, archived, and provisioning tenants, then processes them sequentially. It prints the total, tenant name/UUID/status, pending migration names, backup ID, live Laravel migration output, progress counts, and a final per-tenant results table with timings.

- **MIGRATED:** a verified backup was created, then pending migrations were applied and the superadmin schema version updated.
- **SKIPPED:** every migration filename is already recorded in that tenant's `migrations` table. No backup or schema changes are made. The check is still recorded as an operation in the superadmin database.
- **FAILED:** connection, configuration, lock, backup, or migration failed. Processing continues with the next tenant. Inspect application logs under `storage/logs` and the superadmin `provisioning_runs` records using the tenant UUID. A missing database configuration or missing `migrations` table is a failure; this command does not provision new databases.

Exit code is `0` when all tenants migrated or were already up to date (also when no tenants exist), and `1` if any tenant failed or the registry could not be read. Tenants registered after the initial snapshot are handled on the next run.

The existing per-tenant operation lock, database identity checks, credential allowlist, migration credentials, and connection cleanup are reused. A backup failure prevents migration for that tenant. Control-plane schema migrations are excluded; only root files in `database/migrations` are considered. All pending migrations are applied, not just the latest file.

Rerunning skips recorded migrations and applies outstanding ones. Do not edit an already applied migration to deploy a new change; create a new migration file. Completion is determined by Laravel's migration history, not by checking every column for schema drift.

Migration execution can lock tables, and MySQL schema changes may partially persist if a migration fails. There is no automatic rollback or guarantee of zero downtime. Review failures before retrying; the backup ID is printed before migrations start. Run one deployment at a time and avoid overlapping bulk runs. Backups are retained under `storage/app/tenants/<UUID>/private/backups`, so allow sufficient disk space.

## Migrate one tenant

```bash
php artisan tenant:migrate --tenant=TENANT_UUID
```

Copy the UUID from the tenant details page in superadmin. This existing command always creates a backup before running pending migrations, even if none remain. It updates the schema version and logs the operation. It does not accept `--all` or require an extra `--force` option.

## Back up or check one tenant

```bash
php artisan tenant:backup --tenant=TENANT_UUID
php artisan tenant:health --tenant=TENANT_UUID
```

Backup prints a verified backup ID. Health checks the database and selected required tables/columns; it updates health/schema metadata but is not an exhaustive check of every migration.

## Migrate the superadmin database

```bash
php artisan control:migrate --confirm-control
```

Use only when deploying changes in `database/migrations/control`. This runs control-plane migrations once and does not migrate tenant databases. The online-store-price migration belongs to tenant databases and does not require this command.

## Deployment checklist

1. Upload the new migration files, application changes (including new PHP files), compiled assets, and `public/mix-manifest.json`. For this bulk command include `app/Console/Commands/TenantMigrateAll.php` and `app/Tenancy/TenantMigrationService.php`. The command is automatically discovered by the existing console kernel.
2. Coordinate a maintenance window if necessary so users do not use code requiring new columns before all tenant migrations finish.
3. Run `php artisan tenant:migrate-all` and review the final results. Do not treat a partially failed run as a successful deployment.
4. Investigate failed tenants, then rerun the bulk command or the single-tenant command. Already recorded migrations are not rerun.
5. Check the updated functionality before restoring normal access.

Do not use plain `php artisan migrate` to update tenant databases: it does not resolve tenants from the registry. Never use `migrate:fresh`, `migrate:reset`, or `migrate:refresh` for this deployment; they can remove existing data.
