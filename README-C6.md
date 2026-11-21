# STILLWIRED C6 — Symfony Native GitHub Webhook Evidence Sink

**Scope:** additive Symfony Webhook + RemoteEvent integration. No changes to C1–C5, existing MCP tools, AI agent, local HTTP router or verifier. C6 is optional and fails closed on missing HMAC.

## What this does

A signed GitHub `push`/`ping` POST is received by Symfony's **native `WebhookController`**, validated by a custom `AbstractRequestParser`, converted to a `RemoteEvent`, and synchronously consumed into an append-only local evidence ledger (`var/evidence/c6-github-receiver.jsonl`). The ledger stores only GitHub delivery GUID, event type, repository, optional hook ID, SHA-256 of raw request body, receipt time, and a *local hash chain*. Duplicate deliveries are counted once; a collision or altered ledger fails closed. No raw webhook payload, commit metadata, signature or destination URL is persisted.

This does **not** replace C4's read-only provider audit. C5 is historical and **cannot be retroactively signed**. A new signed delivery is required for live C6 validation.

## Install (PowerShell, Windows)

1. Back up the project (or commit it) and unzip C6 to the **root** of `C:\Projects\stillwired-gate1`, retaining directory structure. C6 uses unique YAML filenames so it does not overwrite `mcp.yaml` or existing routes.
2. In the project directory:

```powershell
composer require symfony/webhook --no-interaction
php tests\run-c6-domain.php
php -l src\NativeWebhook\GitHubWebhookRequestParser.php
php bin/console debug:router | Select-String 'webhook'
```

`composer require` must update both `composer.json` and `composer.lock` in the public repository. `symfony/webhook` brings Symfony RemoteEvent and Messenger as dependencies, but **this patch does not configure asynchronous processing**.

3. In the terminal that **starts the HTTP server**, configure a new 32+-character random secret. Do not use GitHub's API bearer token or a C5 token as your HMAC webhook secret:

```powershell
$secure = Read-Host "New C6 shared webhook secret (>= 24 chars)" -AsSecureString
$env:SW_C6_GITHUB_WEBHOOK_SECRET = [System.Net.NetworkCredential]::new('', $secure).Password
Remove-Variable secure
# Stop the old HTTP server if needed, then run in this terminal:
C:\xampp\php\php.exe -S 127.0.0.1:8787 -t public public/router.php
```

The same secret must be set in the **second terminal** running the local test. If the PHP server was already running before the variable was set, restart it: child processes inherit environment at startup.

4. In another terminal, set the secret again (same value) and run:

```powershell
$secure = Read-Host "Repeat C6 shared webhook secret" -AsSecureString
$env:SW_C6_GITHUB_WEBHOOK_SECRET = [System.Net.NetworkCredential]::new('', $secure).Password
Remove-Variable secure
$env:SW_PHP_BIN = 'C:\xampp\php\php.exe'
node .\tools\test-c6-native-http.cjs
php bin/console sw:c6:receipts
```

Expected (only after running on Windows): `C6_NATIVE_HTTP_PASS` and `HASH_CHAIN_OK`. The Node test sends a **synthetic signed push** to the local Symfony route, not to GitHub, then checks invalid signatures, duplicate delivery and receipt persistence. It times out each HTTP request after 8 seconds.

## Live GitHub test (optional, separate)

For a new *real* GitHub signed webhook, a reachable **HTTPS** endpoint must route `/webhook/stillwired_github` to this Symfony app. Do **not** expose the loopback-only MCP endpoints (`/mcp/*`), the PHP debug toolbar, `var/evidence`, or any development environment. Deploy a dedicated hardened test instance/proxy, disable `APP_DEBUG`, validate TLS and set a separate receiver HMAC secret in the server's environment. In the GitHub repository's webhook configuration set this exact secret, Content-Type `application/json`, events `push` only; trigger a new test commit. Compare its `X-GitHub-Delivery` with:

```powershell
php bin/console sw:c6:receipts --guid="GITHUB_DELIVERY_GUID"
```

The receipt means **a verified signed HTTP request arrived at your Symfony receiver**. It is not an independent trusted timestamp or proof that all outbound pathways have stopped. The local GUID header is not itself covered by GitHub's HMAC (which signs the raw body); check its correlation with the GitHub provider API. Never publish receiver secrets or raw webhook payloads.

## Security and scientific limits

- Fail closed on an absent/invalid HMAC, unsupported event, non-JSON body, or malformed GUID.
- Local ledger detects accidental tampering by a hash chain; an attacker controlling the host can rewrite and rehash the entire ledger. An external signed timestamp would be required for stronger provenance.
- Duplicate GUID + same body is idempotent; duplicate GUID + different body rejects.
- No matching receipt means **INCONCLUSIVE**, never `VERIFIED`.
- Receiver HMAC proves knowledge of the shared webhook secret, not an independently attested identity or revocation.
- A deliberately public endpoint must have normal network protections (rate limiting, data minimization, TLS, monitoring). C6's parser caps the raw body at 1 MiB.
- No Messenger async retry handling is enabled; those are a **separate testable future scenario**.

## Rollback

Delete only the newly added `src/NativeWebhook/*`, `src/Command/C6ReceiptAuditCommand.php`, `config/packages/stillwired_native_webhook.yaml`, `config/routes/stillwired_native_webhook.yaml`, `tests/run-c6-domain.php` and `tools/test-c6-native-http.cjs`. Remove `symfony/webhook` via Composer **only if no other component needs it**. Preserve existing C1–C5 code and evidence.
