# Release Calendar

A web application for tracking and managing software releases across multiple environments.

## Features

- **Overview page**: Shows all releases with their current status, progress, planned production dates, and responsible person
- **Status tracking**: Visual indicators for releases that are behind schedule or have overlapping production dates
- **Responsible person**: Each release has an assigned owner (defaults to creator), filterable by person including "mine"
- **Per-environment tracking**: For each environment, track whether the application is installed and the planned/deployed dates
- **Add release form**: Form with all necessary fields including version, changes, and optional planned dates per environment
- **GitHub integration**: Configure GitHub releases URL per application to auto-populate version and changes from GitHub releases
- **Detail view**: Edit all release details including deployed dates per environment
- **OIDC authentication**: Secure login using OpenID Connect (authorization code flow with PKCE). All authenticated users have full CRUD access.
- **Public view**: Unauthenticated users can see a compact list of past and future releases
- **Dark/light mode**: Automatically follows OS settings with manual override

## Configuration

All configuration is in `config/config.php`:

- **Applications**: Managed applications with labels and optional GitHub releases URLs
- **Environments**: List of environments (test, acceptance, production, etc.) with production flag
- **OIDC settings**: Provider URL, client ID, client secret. Can also be set via environment variables:
  - `OIDC_URL`
  - `OIDC_CLIENT_ID`
  - `OIDC_CLIENT_SECRET`
- **GitHub token**: Optional token to raise GitHub API rate limit (or via `GITHUB_TOKEN` env var)
- **Development mode**: Set `DEV_BYPASS=1` to enable local login without OIDC provider (for development only)

## Database

The application uses MariaDB/MySQL. The schema is defined in `sql/schema.sql`.

To initialize the database:
```fish
# Run the schema
mysql -u <user> -p <database> < sql/schema.sql

# Optionally load example data
mysql -u <user> -p <database> < sql/seed.sql
```

Or use the provided init script:
```fish
php sql/init-db.php
```

## Running Locally

Using Docker Compose:

```fish
docker compose up --build
```

This will:
- Start the application on http://localhost:8080
- Start a MariaDB container
- Create the database schema on startup
- Load example data (can be disabled by removing `SEED_EXAMPLE_DATA=1` from docker-compose.yml)

Open http://localhost:8080 and log in with "Continue as local developer" (development mode only).

## Running in Production

1. Set OIDC configuration with your provider details (URL, client ID, client secret)
2. Set `dev_bypass` to `false`
3. Configure database connection via environment variables:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
4. Ensure the database schema is initialized (run `sql/schema.sql`)

## Deployment

Container images are built and published to GHCR by `.github/workflows/deploy.yml` on every git tag and manually via workflow_dispatch.
