# MySQL App User Setup

Use a dedicated MySQL user for Laravel instead of passwordless `root`. Keep the password out of git and store it only in the local `.env` or your production secret manager.

Scope the account to `127.0.0.1` and `localhost` only. Do not use wildcard hosts such as `%` for the application user.

## Create User

Generate a strong password first:

```bash
openssl rand -base64 32
```

Connect as a MySQL administrator:

```bash
sudo mysql
```

Create the database and app user. Replace `REPLACE_WITH_STRONG_PASSWORD` before running:

```sql
CREATE DATABASE IF NOT EXISTS DigitalForensics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'dlds_app'@'127.0.0.1'
IDENTIFIED BY 'REPLACE_WITH_STRONG_PASSWORD';

CREATE USER IF NOT EXISTS 'dlds_app'@'localhost'
IDENTIFIED BY 'REPLACE_WITH_STRONG_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE
ON DigitalForensics.*
TO 'dlds_app'@'127.0.0.1';

GRANT SELECT, INSERT, UPDATE, DELETE
ON DigitalForensics.*
TO 'dlds_app'@'localhost';

FLUSH PRIVILEGES;
```

## Temporary Migration Grants

Laravel migrations need schema privileges. Grant them only during deploy or local setup:

```sql
GRANT CREATE, ALTER, INDEX, DROP, REFERENCES
ON DigitalForensics.*
TO 'dlds_app'@'127.0.0.1';

GRANT CREATE, ALTER, INDEX, DROP, REFERENCES
ON DigitalForensics.*
TO 'dlds_app'@'localhost';

FLUSH PRIVILEGES;
```

Run migrations:

```bash
php artisan config:clear
php artisan migrate --force
php artisan migrate:status
```

Revoke schema privileges after migrations if the app user should be runtime-only:

```sql
REVOKE CREATE, ALTER, INDEX, DROP, REFERENCES
ON DigitalForensics.*
FROM 'dlds_app'@'127.0.0.1';

REVOKE CREATE, ALTER, INDEX, DROP, REFERENCES
ON DigitalForensics.*
FROM 'dlds_app'@'localhost';

FLUSH PRIVILEGES;
```

## Update `.env`

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=DigitalForensics
DB_USERNAME=dlds_app
DB_PASSWORD=REPLACE_WITH_STRONG_PASSWORD
```

Then clear cached config and verify:

```bash
php artisan config:clear
php artisan migrate:status
php artisan test
```

## Rollback

If the app cannot connect, temporarily restore the previous local credentials in `.env`, clear config, and verify:

```bash
php artisan config:clear
php artisan migrate:status
```

To remove the app user:

```sql
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'dlds_app'@'127.0.0.1';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'dlds_app'@'localhost';
DROP USER IF EXISTS 'dlds_app'@'127.0.0.1';
DROP USER IF EXISTS 'dlds_app'@'localhost';
FLUSH PRIVILEGES;
```

Passwordless `root` should remain limited to isolated local development only and should not be used for production runtime.
