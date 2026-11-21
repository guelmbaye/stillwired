# STILLWIRED — Competitive Upgrade C1: False Green

## Why this matters

A disabled status is not evidence that data stopped moving. This synthetic adversarial
scenario makes both providers **report disabled** while their underlying, independent
outbound delivery routes still work. A status-only checker would say VERIFIED;
STILLWIRED's MCP canary checks must say EXPOSED, with two real lab-sink entries.

This is an intentionally injected **control-plane telemetry mismatch**, not a claim that
any real vendor lies. All claims are restricted to the synthetic lab and enumerated flows.

## Files changed

* `src/Lab/StateStore.php` — new `stale-report` fault (observation != delivery), removed by reset; canary emission checks actual route, not reported state
* `src/Lab/McpVerifier.php` — reports `status_only_baseline` and `status_delivery_conflicts`; authoritative verdict logic retained
* `src/Command/LabCommand.php` — `sw:lab fault <flow> stale|honest`, existing `up|down` unchanged
* `tests/run-competitive.php` — 8 new independent, no-Composer tests
* `run-competitive-false-green.cjs` — bounded real Symfony -> MCP end-to-end comparison

No MCP tool declarations, transport configuration, provider adapters, composer files,
AI configuration or approval logic are modified.

## Install

Start with a working copy of `C:\Projects\stillwired-gate1`. Back up the three
existing PHP files before overlaying the archive. Extract the archive **into the
project root**; do not delete `vendor/`, `composer.lock`, `var/` or `.env.local`.

```powershell
cd C:\Projects\stillwired-gate1
Copy-Item src\Lab\StateStore.php src\Lab\StateStore.php.before-C1
Copy-Item src\Lab\McpVerifier.php src\Lab\McpVerifier.php.before-C1
Copy-Item src\Command\LabCommand.php src\Command\LabCommand.php.before-C1
# Extract the ZIP into this folder, overwriting the three changed PHP files.
php -l src\Lab\StateStore.php
php -l src\Lab\McpVerifier.php
php -l src\Command\LabCommand.php
php tests\run.php
php tests\run-gate1-domain.php
php tests\run-competitive.php
```

## Real MCP experiment (requires existing local HTTP server running)

```powershell
$env:SW_PHP_BIN = 'C:\xampp\php\php.exe'
node .\run-competitive-false-green.cjs
```

Successful output should include:

```
WINNING_MOMENT: STATUS_ONLY=VERIFIED BUT CANARY_VERDICT=EXPOSED
CONTRADICTED_SOURCES=webhook,scheduled_export
AFTER_APPROVED_STOP: STATUS_ONLY=VERIFIED CANARY_VERDICT=VERIFIED
COMPETITIVE_FALSE_GREEN_PASS
```

The runner resets only `var/lab` synthetic state, invokes the existing `sw:lab`
commands over the configured Symfony MCP HTTP client, executes both one-time
approvals and saves two checked evidence snapshots:

* `var/evidence/competitive-false-green.json` (+ SHA-256)
* `var/evidence/competitive-truly-closed.json` (+ SHA-256)
* `var/evidence/competitive-showcase.html` — a single-file offline, browser-ready,
  side-by-side visual proof for the 90-second video (not a live UI)

The runner treats missing replies, failed commands, a SHA mismatch, unexpected
observed state, or an incorrect verdict as a failure. It cannot independently
prove that the remote HTTP server is isolated from the client process; that
is established by the previous Gate 1 evidence.

## Negative controls

After C1 passes, rerun `node .\stillwired-gate1-e2e.mjs --negative` to confirm
the original EXPOSED -> VERIFIED -> INCONCLUSIVE -> EXPOSED -> VERIFIED path
is unchanged. That runner also resets `var/lab`, so archive C1 evidence first.

## Boundaries

- Synthetic synchronous data sink, **not** delayed queue proof.
- Does **not** discover unknown integrations.
- Does **not** establish that the agent itself detected the discrepancy:
  the deterministic PHP verifier does. No unverified agent claim should be made.
- The two status-only mismatches are injected by a dedicated lab flag and are
  not asserted to represent a bug in any real SaaS provider.

## Why this beats a purely cosmetic upgrade

The side-by-side contradiction is executable and falsifiable, uses the existing
PHP MCP SDK servers and Symfony client, exercises one-use approvals and proves
that the source's `state=disabled` alone is insufficient for closure.
