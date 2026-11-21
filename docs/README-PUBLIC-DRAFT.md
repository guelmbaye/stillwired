# STILLWIRED — The dashboard is green. The data is still leaving.

> A **synthetic incident-response laboratory**, built with Symfony AI and the official PHP MCP SDK. It exposes the gap between revoked user access and still-active outbound data flows.

## The 20-second story

An operator revokes a contractor account. A webhook and a scheduled export survive because they belong to independent source-owned integrations. In the more adversarial **false-green** scenario, both systems *report disabled* while real synthetic canaries continue reaching a persisted sink.

| Check | Status-only baseline | STILLWIRED verifier |
|---|---|---|
| Providers report `disabled` | `VERIFIED` | — |
| Two canary deliveries are persisted | Not checked | `EXPOSED` |
| Both routes stopped with one-use lab approvals, then re-probed | `VERIFIED` | `VERIFIED` |

**The key point:** the model can investigate and explain the evidence, but it cannot authorize remediation or issue the authoritative verdict. Deterministic PHP uses actual canary receipts to decide whether the tested incident can close.

## Built with

- Symfony Framework and Symfony AI Agent: natural-language investigation and scoped tool calling.
- Symfony MCP Bundle and PHP MCP SDK: two separate MCP servers, `webhook` and `scheduler`, consumed over **local Streamable HTTP**.
- PHP domain verifier: independent, fail-closed decision (`EXPOSED`, `INCONCLUSIVE`, `VERIFIED`).
- File-backed synthetic source states and sink: observable cross-process delivery receipts.
- One-time lab approval tokens for stop actions, intentionally **never exposed** to the agent.

## Reproduce (Windows PowerShell, verified development path)

Requirements: Node.js 18+, PHP and Composer 2; tested by the author on Windows with PHP 8.5.12, Symfony 8.1.11 and a local PHP HTTP server. The original Symfony 7.4 scaffold and its lockfile may differ; the actual committed `composer.lock` is authoritative.

```powershell
composer install
php tests\run.php
php tests\run-gate1-domain.php
php tests\run-competitive.php
php tests\run-c2-contract.php

# Start the local HTTP MCP server in a separate PowerShell window:
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start-stillwired-http.ps1

# Run the no-LLM, deterministic adversarial case:
$env:SW_PHP_BIN = "C:\xampp\php\php.exe" # replace with your PHP path
node .\run-competitive-false-green.cjs
```

Expected: `COMPETITIVE_FALSE_GREEN_PASS`. This scenario does **not require an LLM key**. Verify your environment and local HTTP server are active.

### Live Symfony AI investigation

Configure `OPENAI_API_KEY` locally in `.env.local` (never commit it), then:

```powershell
node .\run-competitive-ai-c2.cjs
```

Expected: `C2_ACTIVE_PROBE_PASS`. The runner checks the four real MCP tool-call lifecycle events, two matching persisted receipts, read-only state and the independent PHP verdict. A successful output has been obtained in the development environment; reproducibility in a fresh clone must still be independently checked.

### Offline jury evidence viewer

```powershell
node .\tools\build-jury-demo.cjs
start .\dist\stillwired-jury-demo.html
```

The builder reads the **actual** C1/C2 evidence files, verifies six SHA-256 sidecars and generates a standalone HTML page plus a sanitized JSON manifest. It fails closed on missing or inconsistent evidence. This replay does not perform live MCP calls or re-run the model: use the commands above to reproduce those actions.

## Architecture and trust boundary

```text
Incident description
  → Symfony AI Agent (observe + synthetic canary tools only)
      → webhook MCP server ──┐
      → scheduler MCP server ├─> persistent synthetic delivery sink
                             ┘
  → deterministic PHP verifier (separate from model text)
      → EXPOSED / INCONCLUSIVE / VERIFIED + JSON + local SHA-256
  → operator-issued, one-use lab approval → stop_* MCP tools
```

**Scope:** only enumerated simulated integrations; synchronous synthetic deliveries, no delayed-queue guarantee, unknown-credential discovery or proof about a real vendor. Local SHA-256 sidecars detect accidental tampering against the stored digest, but do not authenticate the evidence's origin. The HTTP server binds to `127.0.0.1` and has **no production-grade authentication**. Do not expose it to the Internet.

## Video and evidence

- `dist/stillwired-jury-demo.html` is a **snapshot replay**, not a live production dashboard.
- `var/evidence/` contains local detailed outputs and should **not be committed wholesale**.
- `dist/stillwired-jury-evidence.json` exposes only synthetic safe fields and the SHA-256 values checked by the builder.

See `docs/SUBMISSION-STORY.md` and `docs/VIDEO-90-SECONDS.md` for the story and recording order.

## Notes on reproducibility

The project uses experimental AI / MCP releases. Pin the resolved versions in `composer.lock`; use the same PHP version for the local server and CLI. Windows STDIO client integration was unstable during development, so Symfony AI and MCP client tests use local HTTP instead. The **MCP servers themselves were also verified independently over STDIO**, but the published default transport should be HTTP.
