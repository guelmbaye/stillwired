# STILLWIRED — Competitive Upgrade C2: Active AI contradiction investigation

**Objective:** make Symfony AI discover, actively test, and explain a specific inconsistency: both MCP providers report `disabled`, but a controlled synthetic canary still reaches the delivery sink. The PHP verifier remains the *only* authoritative verdict engine.

This is a **patch for Gate 1 with C1 already installed**. It changes only:

- `src/Command/InvestigateCommand.php` (opt-in `--active-probe`, explicit tool filtering, grounded investigation instructions)
- `src/EventListener/InvestigationToolAuditSubscriber.php` (server-side allowlist for observations and synthetic canaries; still blocks all revocation tools)

It adds:

- `run-competitive-ai-c2.cjs` (60-second-bounded live investigation and independent evidence checks)
- `tests/run-c2-contract.php` (static contract checks)

No changes to MCP server tools, HTTP configuration, composer, state store, or deterministic verifier.

## Apply on Windows

Back up your existing files *before* extraction:

```powershell
cd C:\Projects\stillwired-gate1
Copy-Item src\Command\InvestigateCommand.php src\Command\InvestigateCommand.php.before-C2 -Force
Copy-Item src\EventListener\InvestigationToolAuditSubscriber.php src\EventListener\InvestigationToolAuditSubscriber.php.before-C2 -Force
```

Extract the C2 archive **inside the project root**, preserving the `src` and `tests` directories. It overwrites only the two files listed above.

```powershell
php -l src\Command\InvestigateCommand.php
php -l src\EventListener\InvestigationToolAuditSubscriber.php
php tests\run.php
php tests\run-gate1-domain.php
php tests\run-competitive.php
php tests\run-c2-contract.php
php bin/console list sw
```

**Prerequisite:** the existing local HTTP MCP service is still running at `127.0.0.1:8787`, and the Symfony AI provider key is configured locally. No secrets should be committed. Avoid exposing this lab server publicly.

## Run the competitive C2 experiment

**This resets ONLY the synthetic `var/lab` state**, then injects false-green reports and executes four permitted agent tools. The canary probes intentionally record *synthetic* delivery events in `var/lab/sink.jsonl`.

```powershell
$env:SW_PHP_BIN = "C:\xampp\php\php.exe"
node .\run-competitive-ai-c2.cjs
```

Expected success markers (*not yet validated on your Windows installation*):

```text
STEP reset: PASS
STEP revoke: PASS
STEP metadata-stale-webhook: PASS
STEP metadata-stale-scheduled-export: PASS
STEP investigate-active-probe: PASS
STEP independent-php-verifier: PASS
STATUS_ONLY=VERIFIED
CANARY_DELIVERIES=2
PHP_VERDICT=EXPOSED
AGENT_TOOL_TRACE=4_TOOLS_SUCCEEDED
NO_REMEDIATION_BY_AGENT=PASS
MODEL_CITED_TWO_RECEIPTS=PASS
C2_ACTIVE_PROBE_PASS
```

If `C2_ACTIVE_PROBE_FAIL`, the reason is printed. Most often the model failed to execute one tool or to cite both exact `event_id` values; check `var/evidence/c2-ai-transcript.txt` and `var/evidence/agent-tool-events.jsonl`. **Do not claim C2 PASS if the runner fails.** Do not weaken the grader simply to make the model pass.

## Evidence

- `var/evidence/c2-ai-transcript.txt` + `.sha256` — raw LLM investigation output including the declared controlled canary ID
- `var/evidence/c2-agent-tool-events.jsonl` + `.sha256` — the audited agent tool lifecycle
- `var/evidence/c2-php-verdict.json` + `.sha256` — immutable copy of the authoritative verification
- `var/evidence/c2-summary.json` + `.sha256` — correlation between model calls, the persisted canary receipts and PHP decision

This proves a synthetic controlled fault, not that any real provider lies about its state. The baseline is *status-only*, not the strongest available deterministic competitor.

## Return to observation-only behavior

The existing `sw:investigate` command with no `--active-probe` option remains observation-only; it offers the model only the two `observe_*` tools. The server-side audit policy denies `stop_*` calls in both modes. Restore the two `.before-C2` files to roll back.
