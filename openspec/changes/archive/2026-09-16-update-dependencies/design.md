## Context

See proposal.md - Why. This plugin has one runtime dependency with real behavioral risk on a major bump (`firebase/php-jwt`), one dev dependency with a known cross-major structural gotcha discovered in the sibling `wordpress-password2cloudflare` repo's equivalent change (`phpunit/phpunit`), and no declared PHP baseline (`composer.json` has no `require.php`).

CI (`wordpress-plugin.yml`) is the only realistic acceptance gate: there is no local WordPress/MySQL test environment in this sandbox, so `wordpress-phpunit`, `php-lint`, `yaml-lint`, `composer-validation`, and `language-files-up-to-date` job results on the PR are what "done" means for task-level verification, same approach as the sibling repo.

## Goals / Non-Goals

**Goals:**
- Bump every stale Action/Composer dependency to its latest stable version, unless doing so requires more than a mechanical fix.
- Explicitly verify the `firebase/php-jwt` major bump's key-size-validation change against this plugin's actual JWT/JWK decode paths, not just confirm `composer update` resolves.
- Establish an explicit minimum PHP baseline (`require.php`) grounded in WordPress.org's own recommended requirement, and pin CI to actually test it — same fix applied reactively (via reviewer feedback) in the sibling repo, done proactively here.

**Non-Goals:**
- No new dependabot ecosystem beyond `composer` and `github-actions`.
- No change to the plugin's authentication behavior or Cloudflare Zero Trust integration logic itself — only the JWT library version underneath it.
- No attempt to add a `pull_request`/`workflow_dispatch`-triggered release workflow — this repo has no `release.yml` at all (unlike the sibling repo), so that concern doesn't apply here.

## Decisions

- **Declare `"php": ">=8.3"` in `composer.json`, matching WordPress.org's recommended baseline** (wordpress.org/about/requirements: "PHP 8.3 or greater"), and pass `php_version: "8.3"` explicitly to `holyhope/test-wordpress-plugin-github-action` in CI instead of relying on its implicit default. Alternative considered: leave PHP baseline undeclared (as before) — rejected because it was flagged as a real gap by an automated reviewer on the sibling repo's PR, and it's better to fix it proactively here.
- **`firebase/php-jwt` bump requires functional verification, not just a version bump.** v7.0.0 added minimum key-size validation as a security fix (keys below the new minimum now fail to decode). Plan: after bumping, exercise `JWT::decode()` and `Firebase\JWT\JWK::parseKey()` against representative key material (existing test fixtures if present, or Cloudflare's actual public JWKS shape) to confirm no regression, rather than assuming CI's generic PHPUnit run exercises this path. If existing tests don't cover this path, that gap itself should be called out rather than silently bumping past it.
- **`phpunit/phpunit` bump strategy**: attempt the latest major first; if it breaks (per the sibling repo's precedent — PHPUnit 10+ dropped legacy filename→class test discovery relied on by `wp scaffold plugin-tests`-generated `phpunit.xml`), fall back to the latest version within the current major that still resolves the known CVE (CVE-2026-24765, fixed as of `9.6.33`+), and document why. Do not assume the same fallback is required here without re-testing — this repo's test suite/tooling may differ from the sibling repo's.
- **GitHub Actions bumped to latest stable majors** across the board (`actions/checkout`, `overtrue/phplint`, `holyhope/test-wordpress-plugin-github-action`, `mikepenz/action-junit-report`, `holyhope/test-wordpress-languages-github-action`), each verified via README/changelog for unchanged inputs/outputs before bumping, mirroring the sibling repo's approach.

## Risks / Trade-offs

- [`firebase/php-jwt` 7.0.0's key-size validation silently breaks decode for undersized keys] → Cloudflare's own JWKS keys are expected to meet standard minimums (RSA ≥2048-bit is Cloudflare's baseline), but this must be explicitly checked against real key material or existing fixtures rather than assumed; if no test coverage exists for this path, flag it rather than deferring silently.
- [PHPUnit major bump may hit the same WP-CLI-scaffold test-discovery incompatibility seen in the sibling repo] → Re-test rather than assume; fall back to latest-compatible `9.x` only if actually needed, with the same CVE-coverage check.
- [No local WP/MySQL test environment to validate `wordpress-phpunit` before pushing] → Use CI as the acceptance gate, same as the sibling repo; push early and iterate on CI failures rather than trying to fully replicate the environment locally.
- [Declaring `require.php >=8.3` could reveal the composer.lock was resolved for a different local PHP version] → Re-resolve the lock file with `composer update` and confirm platform requirements are satisfied by CI's actual runner PHP version, not just the local sandbox's PHP version.

## Migration Plan

1. Add `.github/dependabot.yml`.
2. Bump GitHub Actions (mechanical, verify inputs/outputs unchanged, validate YAML).
3. Declare `require.php >=8.3` and pin CI's PHP version explicitly.
4. Bump `composer/installers`, `wp-cli/wp-cli-bundle` (mechanical bumps, lowest risk first).
5. Bump `firebase/php-jwt` to `^7`, then explicitly verify JWT/JWK decode behavior against representative key material.
6. Attempt `phpunit/phpunit` + `yoast/phpunit-polyfills` major bump; fall back only if CI reveals a real incompatibility, with documented rationale.
7. Regenerate `composer.lock`, push, and use CI (`wordpress-plugin.yml`) as the acceptance gate for every step above.

Rollback: each dependency/Action bump is an independent, revertible commit; if a bump proves unsafe, revert just that commit rather than the whole change.
