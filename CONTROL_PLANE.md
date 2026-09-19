# Superadmin control plane

The superadmin control plane manages tenant registration, domain records, tenant database credentials, connection checks, and tenant status. Subscription plans and payment records are managed by the external CRM and are not used to grant or deny tenant access.

## Login

Superadmins sign in with a username and password. Existing admins receive a username based on the part of their email address before `@` when the control migration runs. During transition, the login field also accepts the existing email address.

Create or update an administrator interactively:

```bash
php artisan control:admin-create --name="Administrator" --username=admin --email=admin@example.com
```

The command asks for and confirms a password. Authenticator and recovery codes are no longer generated or accepted.

## Deploying this change

Upload the changed application files and the new control migration, then run:

```bash
php artisan control:migrate --confirm-control
```

This adds and backfills the superadmin `username` field. It does not alter tenant databases, and it does not delete existing plan, subscription, or payment records. Those tables remain available as historical data but have no control-plane routes or screens and no effect on tenant access.

Tenant activation requires a configured tenant database and a verified primary domain. Active tenants are allowed without a local subscription record.

## CRM linkage foundation

Each control-plane tenant can be linked to one CRM application instance through the nullable, unique `tenants.crm_application_instance_id` column. Existing tenants remain unlinked until the CRM integration explicitly associates them.

The tenant also records the last applied allowlisted data template in `data_template_code` and `data_template_version`. Tenant schema state continues to use `schema_version`.

CRM-triggered work reuses `provisioning_runs`. Set `source` to `crm` and set `external_reference` to the CRM operation idempotency key. Both fields are indexed for status reconciliation. Never store database credentials, API secrets, or raw SQL in the run result.

Apply the linkage migration to the control database with:

```bash
php artisan control:migrate --confirm-control
```
