## 1. Dependabot configuration

- [ ] 1.1 Create `.github/dependabot.yml` with `composer` and `github-actions` ecosystem entries (weekly schedule, `open-pull-requests-limit: 10` each), and verify the file is valid YAML (`yamllint .github/dependabot.yml` or the repo's `ibiqlik/action-yamllint` config).

## 2. GitHub Actions version bumps

- [ ] 2.1 Bump `actions/checkout` in `wordpress-plugin.yml` (currently `@v2` for most jobs, `@v3` for `wordpress-phpunit`) to the latest stable major across all jobs, and verify the workflow YAML is still valid (`yamllint`).
- [ ] 2.2 Bump `overtrue/phplint@9.1.2`, `holyhope/test-wordpress-plugin-github-action@v2.0.2`, `mikepenz/action-junit-report@v3`, `holyhope/test-wordpress-languages-github-action@v4.0.1` to their latest stable released versions, confirming via each action's README/changelog that step inputs/outputs used in this workflow are unchanged, and verify the workflow YAML is still valid. Leave `ibiqlik/action-yamllint@v3` and `actions/upload-artifact@v4` unchanged if they already resolve to the latest major.
- [ ] 2.3 Declare `"php": ">=8.3"` in `composer.json`'s `require` (per design.md, matching WordPress.org's recommended baseline) and set `wordpress-phpunit`'s `holyhope/test-wordpress-plugin-github-action` step to `php_version: "8.3"` explicitly instead of relying on the action's implicit default; verify the workflow YAML is still valid.
- [ ] 2.4 Push the branch and confirm `wordpress-plugin.yml` runs green in CI with the bumped Action versions and the explicit PHP 8.3 baseline (verification: GitHub Actions run status on the PR).

## 3. Composer dependency bumps

- [ ] 3.1 Bump `composer/installers` from `^2.2` to `^2.3` in `composer.json`, run `composer update composer/installers`, and verify `composer test` still passes (or, absent a local WP test env, that `composer validate --strict` and PHP syntax checks pass, deferring full PHPUnit to CI per task 4.1).
- [ ] 3.2 Bump `wp-cli/wp-cli-bundle` (require-dev, currently unconstrained `*` resolving to `2.8.1`) to `^2.12` (or latest), run `composer update wp-cli/wp-cli-bundle --with-all-dependencies` if needed, and verify `composer validate --strict` passes and `composer audit` doesn't regress.
- [ ] 3.3 Bump `firebase/php-jwt` from `^6.10` to the latest major (`^7`) in `composer.json`, run `composer update firebase/php-jwt`, and confirm via the library's CHANGELOG/release notes which APIs used in `classes/CF0TLAuthentication.php` (`JWT::decode`) and `classes/CFCerts.php` (`Firebase\JWT\JWK::parseKey`) are unaffected by the signature history.
- [ ] 3.4 Explicitly verify `firebase/php-jwt` 7.x's new minimum key-size validation (added in 7.0.0 as a security fix) against this plugin's actual JWT/JWK decode usage: check whether `tests/` has any coverage of `CF0TLAuthentication`/`CFCerts` (it currently does not, per design.md), and if not, either add a minimal test/manual check against representative Cloudflare-shaped key material (e.g. a real or sample Cloudflare Access JWKS response) or explicitly document in this change's follow-up notes that this path remains unverified and why. Do not silently skip this — if it can't be verified, say so.
- [ ] 3.5 Determine the highest `phpunit/phpunit` major compatible with this plugin's declared PHP baseline (`>=8.3` per 2.3) and test tooling (`wp scaffold plugin-tests`-generated `phpunit.xml`). Attempt the bump (paired with a compatible `yoast/phpunit-polyfills` major) and run `composer update`.
- [ ] 3.6 If the PHPUnit major bump from 3.5 breaks (e.g., the same legacy test-discovery incompatibility documented in the sibling `wordpress-password2cloudflare` repo's equivalent change), fall back to the latest version within the previous major that still resolves any known CVE affecting the currently-locked version, and document the fallback rationale in this change's follow-up notes (task 4.3). If it does not break, keep the bumped major and note that no fallback was needed.
- [ ] 3.7 Regenerate `composer.lock` reflecting all bumped packages (re-resolving against the CI runner's actual PHP version if the local sandbox's PHP version differs), and verify `composer validate --strict` passes.

## 4. Full verification

- [ ] 4.1 Run the full CI pipeline (`wordpress-plugin.yml`) against the updated dependencies and Actions on the change's PR, and verify `wordpress-phpunit`, `php-lint`, `yaml-lint`, and `composer-validation` all pass. Note the `language-files-up-to-date` job's result separately — if it fails, check whether it's pre-existing/unrelated (compare against a baseline run on the unmodified branch) before treating it as blocking.
- [ ] 4.2 Run `composer audit` after all bumps and confirm the two currently-known advisories (PHPUnit CVE-2026-24765, symfony/process CVE-2024-51736) are resolved or explicitly explained if still present.
- [ ] 4.3 Document in the PR description any dependency deliberately left on a fallback version (per 3.6) and the outcome of the `firebase/php-jwt` key-size verification (per 3.4), so both are discoverable later.
