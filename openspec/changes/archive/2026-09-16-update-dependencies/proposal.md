## Why

`composer.json` and `.github/workflows/wordpress-plugin.yml` pin stale versions: `firebase/php-jwt` `^6.10` (latest `7.1.1`), `phpunit/phpunit` `^9` (latest `13.x`, current lock `9.6.11` carries an unpatched CVE), `yoast/phpunit-polyfills` `^2` (latest `4.x`), `composer/installers` `^2.2` (latest `2.3.0`), `wp-cli/wp-cli-bundle` unconstrained `*` but locked to `2.8.1` (latest `2.12.0`). GitHub Actions are similarly behind: `actions/checkout@v2`/`@v3`, `overtrue/phplint@9.1.2`, `holyhope/test-wordpress-plugin-github-action@v2.0.2`, `mikepenz/action-junit-report@v3`, `holyhope/test-wordpress-languages-github-action@v4.0.1`. There is also no `.github/dependabot.yml`, so this drift goes unnoticed going forward. `composer audit` currently reports 2 advisories (PHPUnit CVE-2026-24765, symfony/process CVE-2024-51736 as a transitive dev dependency).

## What Changes

- Add `.github/dependabot.yml` with `composer` and `github-actions` ecosystems (weekly, `open-pull-requests-limit: 10`), matching the convention already adopted in the sibling `wordpress-password2cloudflare` repo.
- Bump pinned GitHub Actions in `wordpress-plugin.yml` to their latest stable released versions: `actions/checkout`, `overtrue/phplint`, `holyhope/test-wordpress-plugin-github-action`, `mikepenz/action-junit-report`, `holyhope/test-wordpress-languages-github-action`, `ibiqlik/action-yamllint`, `actions/upload-artifact` (verifying each action's inputs/outputs used here are unchanged, same as the prior change).
- Bump Composer dependencies to their latest stable versions: `composer/installers`, `wp-cli/wp-cli-bundle`, `phpunit/phpunit`, `yoast/phpunit-polyfills`, and `firebase/php-jwt` (major bump `^6.10` → `^7`).
- **BREAKING (upstream, not this plugin's API)**: `firebase/php-jwt` 7.0.0 added minimum key-size validation as a security fix — JWTs/JWKs signed with keys below the newly-enforced minimum size will now fail to decode. This plugin decodes JWTs from Cloudflare Zero Trust using keys fetched from Cloudflare's certs endpoint (`classes/CFCerts.php`, `classes/CF0TLAuthentication.php`), so this needs explicit verification against real/representative Cloudflare key material before being considered safe, not just a mechanical version bump.
- If any major bump surfaces an incompatibility beyond a mechanical fix (mirroring what happened with PHPUnit 11 in the sibling repo's equivalent change), fall back to the latest compatible version within the previous major and document the fallback rationale, rather than forcing the bump.
- Regenerate `composer.lock`, confirm `composer validate --strict` and `composer audit` are clean (or documented), and confirm CI (`wordpress-plugin.yml`) is green on the change branch.

## Capabilities

_No capability specs apply — this is dependency/CI maintenance with no plugin-behavior or spec-level change beyond what upstream libraries change internally (verified via task-level checks below)._

### New Capabilities

(none)

### Modified Capabilities

(none)

## Impact

- **Affected files**: `composer.json`, `composer.lock`, `.github/workflows/wordpress-plugin.yml`, new `.github/dependabot.yml`.
- **Affected code paths**: `classes/CF0TLAuthentication.php` (`JWT::decode`), `classes/CFCerts.php` (`Firebase\JWT\JWK::parseKey`) — indirectly, via the `firebase/php-jwt` major bump.
- **Dependencies**: `firebase/php-jwt`, `composer/installers`, `wp-cli/wp-cli-bundle`, `phpunit/phpunit`, `yoast/phpunit-polyfills`; GitHub Actions listed above.
- **CI**: `wordpress-plugin.yml` (`yaml-lint`, `php-lint`, `composer-validation`, `wordpress-phpunit`, `language-files-up-to-date` jobs) is the acceptance gate, same approach as the sibling repo's `update-dependencies` change.
