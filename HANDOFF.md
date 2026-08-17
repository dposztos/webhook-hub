# Open source prep — done, waiting on the push

Branch: `opensource-prep` (3 commits off `master`), 94 files changed.
Target: `https://github.com/cactuska/webhook-hub` — standalone repo, deliberately
**not** a GitHub fork of webhook.site, so it stays searchable and gets its own
issues.

Green: `./php php artisan test` → 43 passed, `npm run build` clean,
`./php ./vendor/bin/pint --test` clean.

## What was done

- **i18n.** `resources/js/i18n.js` globs `lang/*.json` at build time; a new
  language is one JSON file plus one PHP directory, no code change. The switcher
  lists catalogs by their `_name`. English and Hungarian are complete and
  key-for-key equal; `tests/Unit/LanguageCatalogTest.php` fails the build on
  missing keys, extra keys, placeholder drift or a missing `_name`.
- **Whole codebase in English**: UI strings, comments, test names, sample data.
  Twig filters renamed off Hungarian (`huf`→`money`, `hu_date`→`local_date`,
  `meta.received_at_hu`→`received_at_local`) **with the old names kept as
  aliases**, so rules already stored in the live database keep rendering.
- **Leaks fixed**: SECURITY.md pointed reports at `contact@webhook.site`;
  stack.yml referenced the private Gitea registry; composer.json still said
  `laravel/laravel`; .env.example was stock Laravel. README no longer names
  `webhook.posztos.com`.
- **Security**: `MessageRecorder::clientIp()` parsed `X-Forwarded-For` by hand,
  bypassing trusted-proxy handling — any direct client could fake the recorded
  IP. Now `$request->ip()`, with `TRUSTED_PROXIES` configurable (default `*`
  unchanged, cost documented in SECURITY.md).
- **CI**: `.github/workflows/tests.yml` (PHPUnit on real Postgres + Vite build +
  Pint) and `image.yml` (multi-arch → GHCR always, Docker Hub when secrets
  exist). Pint was applied once across the repo, which had never run it.
- **Docs**: English `README.md`, Hungarian `README.hu.md`, `CONTRIBUTING.md`
  leading with how to add a language, issue templates.

## The only step left

Pushing — needs the user's GitHub credentials, and `gh` is not installed here:

```bash
cd ~/webhook-hub
git remote set-url origin https://github.com/cactuska/webhook-hub.git
git checkout master && git merge --ff-only opensource-prep
git push -u origin master
git push origin upstream-master     # provenance of the fork
```

Then in the repo settings: enable private vulnerability reporting (Security →
"Private vulnerability reporting"), and add `DOCKERHUB_USERNAME` = `dposztos`
plus `DOCKERHUB_TOKEN` as Actions secrets — without them the image workflow
still pushes to GHCR and just skips the Docker Hub mirror.

## Watch out

- `docker/prod/stack.yml` still uses the `SESSION_SECURE` variable name (mapped
  to `SESSION_SECURE_COOKIE`). Kept deliberately — renaming it would break login
  on the live instance's next redeploy.
- `.env` is git-ignored and was never committed; verified with
  `git log --all -- .env`.
- Delete this file before or shortly after the first public push; it is a work
  note, not documentation.
