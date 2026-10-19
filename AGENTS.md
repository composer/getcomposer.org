# AGENTS.md

Source of getcomposer.org: a small Symfony 8 app (PHP 8.4) that renders the
Composer docs, changelog and download pages, and serves `composer.phar` builds.
There is no database, no forms, no security layer and no console commands.
Check `composer.json` and `symfony.lock` before assuming a component is available.

## Layout

- `src/Controller/`: `HomeController` (home, sponsor, download, phar/checksum
  endpoints), `DocsController`, `ChangelogController`.
- `src/Version/`: `Versions` is the single source of truth for release channels
  and their maintenance metadata. It generates `web/versions` (via
  `bin/generate-versions.php`, called from `update.sh`) and drives the download
  page's maintenance policy table.
- `src/EventSubscriber/RequestSubscriber.php`: forces HTTPS and sets HSTS outside `dev`.
- `templates/`: Twig templates, all extending `layout.html.twig`.
- `js/`, `css/`: asset sources, bundled by esbuild into `web/build/`.
- `web/` is the public dir (not `public/`).

## Things that live elsewhere

- The docs are not in this repo. They are read from
  `vendor/composer/composer/doc` (installed from source and refreshed by
  `update-docs.sh`), so doc content changes go to composer/composer.
- `update.sh` builds the phars from composer/composer on the server. Tagged
  builds in `web/download/<version>/` are committed to this repo, while the
  snapshot `web/composer.phar` and the generated `web/versions` are git-ignored.
  Locally you need a hand-written `web/versions` file (see README).

## Conventions

- Routes are `#[Route]` attributes on controllers. Short redirect URLs
  (`/xdebug`, `/root`, ...) are `RedirectController` entries in
  `config/routes.yaml`; add new shortcuts there.
- Services are autowired from `src/`. Paths and the environment reach
  services as the bound `$projectDir`, `$docDir` and `$environment` arguments
  (`config/services.yaml`), so tests can point them at fixtures.
- Use `Composer\Pcre\Preg` instead of the `preg_*` functions.
- Native functions returning `false` on failure are checked explicitly (throw,
  or `assert(is_string(...))`), as PHPStan runs at level 8 with
  `checkExplicitMixed`.

## Testing and checks

- `composer test` runs PHPUnit. It fails on any deprecation, notice or warning.
- `composer phpstan` must pass. Fix the cause instead of adding ignores or growing
  `phpstan-baseline.neon`.
- Controller tests extend `App\Tests\Controller\WebTestCase` and use
  `createHttpsClient()`, since plain HTTP requests get redirected. In the test
  env `$docDir` and `$projectDir` point to `tests/Fixtures/`, so add fixture
  files there instead of depending on `vendor/` or real downloads.
- Changes to pages or endpoints come with a functional test that requests them.

## Assets

`npm install`, then `npm run dev` (watch) or `npm run build`. The built
`web/build/` output is committed for deployment, but pull requests should not
include it.

## Dependencies

`composer.json` has a 7-day cooldown policy, so very recent releases won't
resolve. After updating Symfony packages, check `composer recipes` and apply
recipe updates one package at a time with `composer recipes:update <package>`.
