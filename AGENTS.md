# Repository Guidelines

## Project Structure & Module Organization
- `aben-gw.php` is the plugin entry point and registers hooks, activation, and module loading.
- `includes/` contains core plugin classes (for example `includes/class-aben-gw.php`).
- `modules/custom-filters/` implements GW-specific content filters.
- `modules/magic-login/` contains the magic login flow and supporting classes.
- `assets/img/` holds brand assets like `assets/img/logo.png`.

## Build, Test, and Development Commands
- No build system or CLI scripts are defined in this repository.
- Development is done in a local WordPress install:
  - Activate the plugin from **Plugins** in WP Admin.
  - Configure settings under **Aben > GW Settings**.
- Manual smoke test: send a test email from **Aben > Auto Emails** and verify title/excerpt/logo and magic login behavior.

## Coding Style & Naming Conventions
- Follow WordPress PHP coding standards and the existing style in this repo.
- Indentation is 4 spaces; braces are on the same line for functions/classes.
- Functions use `snake_case` (e.g., `aben_gw_init`).
- Classes use `StudlyCaps` with underscores (e.g., `Aben_GW_Magic_Login`).
- Constants are uppercase with underscores (e.g., `ABEN_GW_PATH`).
- Prefer `array()` syntax to match existing code.

## Testing Guidelines
- There is no automated test suite in the repo.
- Test manually by:
  1. Sending a test email.
  2. Verifying country is appended to titles and `job_description` is used for excerpts.
  3. Clicking a magic link to confirm auto-login and redirect.
  4. Confirming external links do not receive magic login tokens.

## Commit & Pull Request Guidelines
- Git history is not available in this workspace, so no established commit message convention could be derived.
- Suggested convention: short, imperative subjects (for example `Add token expiry validation`) and include context in the body if behavior changes.
- PRs should include:
  - A concise summary of changes.
  - Steps to validate in WordPress (screenshots if UI changes).
  - Any settings or dependency changes (Aben plugin version, new hooks, etc.).

## Configuration & Dependencies
- Requires WordPress 5.0+ and PHP 7.2+.
- Depends on **Aben - Auto Bulk Email Notifications** being active.
- Default logo is expected at `assets/img/logo.png`.
