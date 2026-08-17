# Open source prep — where we are

Working branch: `opensource-prep` (off `master`). Target repo:
`https://github.com/cactuska/webhook-hub` (already created, empty, standalone —
deliberately **not** a GitHub fork of webhook.site, so it stays searchable and
gets its own issues).

Images: `ghcr.io/cactuska/webhook-hub` (primary) and `dposztos/webhook-hub` on
Docker Hub (mirror).

## Done (commit 6180268)

- **i18n.** `resources/js/i18n.js` globs `lang/*.json` at build time; adding a
  language is one file, no code change. `LanguageSwitcher.vue` lists catalogs by
  their `_name`. Server strings live in `lang/{en,hu}/*.php`. English and
  Hungarian are complete and key-for-key equal — `tests/Unit/LanguageCatalogTest.php`
  enforces that, including placeholder parity.
- **Everything English**: UI strings, comments, test names, sample data.
- Twig filters renamed off Hungarian: `huf`→`money`, `hu_date`→`local_date`,
  `meta.received_at_hu`→`received_at_local`. **Old names kept as aliases** so
  rules already stored in a database keep working.
- **Leaks fixed**: SECURITY.md (pointed at `contact@webhook.site`), stack.yml
  (private Gitea registry), composer.json (`laravel/laravel`), .env.example
  (stock Laravel → this project's variables). Added root `docker-compose.yml`.
- Removed the unused stock Laravel `welcome.blade.php`.
- `APP_LOCALE=en` pinned in phpunit.xml so tests do not depend on the
  developer's `.env`.
- 43 tests pass (`./php php artisan test`), `npm run build` is clean.

## Left to do

1. **README** — English `README.md` (primary), Hungarian moved to
   `README.hu.md`, cross-linked. The current README is still Hungarian and
   still names `webhook.posztos.com` on line 29; replace with example.com.
2. **Security pass** — focused review of ingest path resolution, the JSON
   viewer, e-mail recipient templating and session/auth config. Already
   verified good: Twig runs sandboxed with empty `allowedMethods`/
   `allowedProperties`, no `v-html` anywhere, the preview iframe is
   `sandbox=""`, no outbound HTTP in `app/` so there is no SSRF surface.
3. **CI + repo hygiene** — GitHub Actions (phpunit on Postgres + `npm run
   build`, then multi-arch image to GHCR and Docker Hub), `CONTRIBUTING.md`
   including "how to add a language", issue templates.
4. **Push** — `git push` the branch to the new remote, merge to `master`.
   Requires the user's GitHub credentials; `gh` CLI is not installed on this
   box.

## Acceptance

`./php php artisan test` green, `npm run build` clean, and
`git grep -nIE '[áéíóöőúüű]' -- . ':!lang/hu*' ':!README.hu.md'` returns
nothing outside the Hungarian catalog and the Hungarian README.

## Watch out

- The live instance deploys from `docker/prod/stack.yml`, which still uses the
  `SESSION_SECURE` variable name (mapped to `SESSION_SECURE_COOKIE`). Kept on
  purpose — renaming it would break login on the next redeploy.
- `.env` is git-ignored and was never committed; it stays that way.
