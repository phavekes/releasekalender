# Release Calendar (Releasekalender)

PHP + MariaDB replacement for the Confluence "Releases SURF Access" pages.

## Features

- Overview of all releases with per-environment status, current environment and progress.
- Each release has a responsible person (defaults to the creator, editable);
  the overview shows the responsible (nickname) instead of the testers and can
  be filtered by responsible (including "mine") and by application.
- Per environment the form has an "installed" checkbox (the application is
  administered on that environment) and an optional planned date.
- Releases whose planned PROD date has passed without the application being
  installed on PROD are highlighted ("behind").
- Releases planned on the same production date are highlighted ("overlap").
  Both alerts look only at the planned PROD date.
- Add-release form with fields from the Confluence page (version, tags, dependencies,
  database changes, testers, components/changes, notes, deployment steps) and optional
  planned dates per environment.
- Per application a GitHub releases URL can be configured; the add-form then lists the
  GitHub releases not yet in the calendar and prefills version + changes.
- Detail view where everything is editable, including deployed dates and notes
  per environment.
- OIDC login (authorization code flow with PKCE). Every authenticated user can
  create, edit and delete releases.

## Configuration

Everything configurable lives in `config/config.php`:

- managed applications (label + GitHub releases URL)
- environments (name + whether production)
- OIDC client info (url, client_id, client_secret) - env vars `OIDC_URL`,
  `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET` override the file
- optional GitHub token (or `GITHUB_TOKEN` env var) to raise the API rate limit
- `dev_bypass` enables a local login without an OIDC provider; use only for
  local development (env: `DEV_BYPASS`).

## Run locally

```fish
docker compose up --build
```

The database schema is created on startup. With `SEED_EXAMPLE_DATA=1` (set in
docker-compose.yml) the example data based on the Confluence export is loaded
when the database is empty.

Open http://localhost:8080 and log in with "Continue as local developer".

## Production

- Set `oidc` to your provider: url, client_id, client_secret, redirect_uri
  (`https://<host>/callback.php`) and `dev_bypass` to false.
- Configure the database via `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- The container image is built and published to GHCR by `.github/workflows/deploy.yml`
  on every git tag (and manually via workflow_dispatch).
