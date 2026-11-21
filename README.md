# STILLWIRED — Gate 1 Candidate: Symfony AI + two real MCP transports

> **Status: partially tested integration candidate, not a validated hackathon release.**
>
> **Tested here:** PHP 8.4 syntax, Gate 0 (8/8), Gate 1 file-state & approvals (10/10).
> **Not tested here:** Composer installation, Symfony container compilation, actual MCP `initialize` / `tools/list` / `tools/call`, live agent call. The build environment has no Composer or outbound package access. **Those checks must run on your machine.**

## What is in the archive

- Preserved original Gate 0 (`php bin/stillwired.php test|demo`).
- Symfony 7.4/AI bundle/MCP bundle application scaffold (`bin/console`, `config/`, `src/Kernel.php`).
- Two MCP **STDIO** servers: `webhook` and `scheduler`, each advertising **its own tool set** and using independent file-backed source state.
- Persistent synthetic sink: `var/lab/sink.jsonl`.
- `sw:smoke-mcp`: checks advertised tools and actual `tools/call` through Symfony's MCP client.
- `sw:lab verify`: deterministic evaluator calling the **two MCP servers** and checking persisted canary deliveries; emits evidence JSON and checksums.
- Human-issued, one-time approval token required by mutating MCP stop tools.
- Optional live AI investigation command, kept separate from deterministic verification.

## Environment

Windows PowerShell, PHP 8.2+, Composer 2, Internet to install packages. PHP must be on PATH (`php -v`). **Extract this as a separate folder from Gate 0**, do not overwrite the validated copy.

## Step 1 — Install and check the framework

```powershell
composer install
php bin/console about
php bin/console debug:mcp
php bin/console debug:mcp --client=stillwired --server=webhook
php bin/console debug:mcp --client=stillwired --server=scheduler
```

**Fail-fast:** stop here if any `debug:mcp` call fails. Preserve the full error; do not claim Gate 1 MCP PASS.

## Step 2 — Real two-server MCP smoke test

```powershell
php bin/console sw:lab reset
php bin/console sw:lab revoke
php bin/console sw:smoke-mcp
```

Expected only **after successful actual execution**:

```text
PASS MCP tools/list + tools/call webhook
PASS MCP tools/list + tools/call scheduler
MCP_SMOKE_PASS; evidence: .../var/evidence/gate1-mcp-connection.json
```

This is the first acceptance criterion: two STDIO child processes, each with its own tool registry. The evidence contains the advertised tool names, returned state, and negotiated protocol information; it deliberately excludes token-bearing requests.

## Step 3 — Show the residual-flow outcome

```powershell
php bin/console sw:lab verify
```

Expected decision: `EXPOSED`, `account_active=false`, both source states `active`, two synthetic canary deliveries.

The operator must issue an **explicit approval for each individual flow**. Keep approval tokens local; never record them in screenshots, chat, or published traces:

```powershell
php bin/console sw:lab approve webhook
# Copy the token printed above, then invoke:
php bin/console sw:lab stop webhook YOUR_ONE_TIME_TOKEN

php bin/console sw:lab approve scheduled_export
php bin/console sw:lab stop scheduled_export YOUR_ONE_TIME_TOKEN

php bin/console sw:lab verify
```

Expected decision: `VERIFIED` with both source states `disabled` and zero new canary deliveries. The `stop` command itself uses the configured MCP client and a remote `tools/call`, **not** an in-process call to the `StateStore` method.

## Step 4 — Negative controls

```powershell
php bin/console sw:lab fault webhook down
php bin/console sw:lab verify
# Expected: INCONCLUSIVE (command exit code 1). The result is never VERIFIED.
php bin/console sw:lab fault webhook up
php bin/console sw:lab reactivate webhook
php bin/console sw:lab verify
# Expected: EXPOSED again
```

## Step 5 — Live Symfony AI (optional, requires a real API key)

Put your provider key in `.env.local`, **not** `.env` or Git:

```dotenv
OPENAI_API_KEY=your_real_key_here
```

```powershell
php bin/console sw:investigate "We disabled the contractor account. What outbound flows might still run? Verify both independent providers through their tools."
```

The agent is for **investigation only**. The **sole authoritative verdict** comes from `sw:lab verify`, never from generated prose. Do not present a deterministic replay as a live model call.

## Offline tests

These commands work even without Composer dependencies:

```powershell
php bin/stillwired.php test
php tests/run-gate1-domain.php
```

## Gate 1 acceptance checklist

- [ ] `composer install` succeeds and a `composer.lock` is committed.
- [ ] `debug:mcp` lists the two server capability sets.
- [ ] `sw:smoke-mcp` calls actual independent STDIO processes.
- [ ] `sw:lab verify` yields `EXPOSED` before stopping routes.
- [ ] Human-approved MCP stop operations yield `VERIFIED` after an independent re-probe.
- [ ] Unavailable provider yields `INCONCLUSIVE`; reactivation yields `EXPOSED`.
- [ ] Live Symfony AI investigation is captured separately with its actual model/tool trace.
- [ ] Measurable comparison against a deterministic discovery baseline is implemented.
- [ ] 90-second walkthrough, clean clone/setup, and project submission are ready.

## Scope / caveats

This lab does not access real organizations, security products, credentials, or data. A synchronous canary proves only the tested outbound paths during the observation window; delayed queues, retries, unknown integrations, and previously leaked data are outside scope. SHA-256 sidecars are integrity checks **not** signed provider attestations. The local approval-token mechanism is an illustration, not production IAM. Publishing a production MCP server with mutating tools would require authentication, transport policy, audit, stronger approval scoping, and security review.

## Official sources

- https://symfony.com/doc/current/ai/bundles/mcp-bundle.html
- https://symfony.com/doc/current/ai/bundles/ai-bundle.html#tools-of-a-remote-mcp-server
- https://symfony.com/hackathon
