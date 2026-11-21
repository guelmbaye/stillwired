# STILLWIRED — Gate 2 tool-call audit (2026-11-21)

This patch adds **one** Symfony event subscriber; it does not alter Gate 0, StateStore, MCP tools, or the PHP verdict engine.

Its purpose is to audit the actual tool calls made by Symfony AI and fail closed on any tool other than `webhook_observe_webhook` and `scheduler_observe_export`. The two underlying MCP servers still advertise mutation tools to other authorized clients; this subscriber applies to AI Toolbox calls, **not to all MCP server clients**.

## Installation

Copy `src/EventListener/InvestigationToolAuditSubscriber.php` to the same relative path in `C:\Projects\stillwired-gate1`. The project's `config/services.yaml` already autowires/autoconfigures `Stillwired\` services, so no YAML change is normally needed.

## Commands (Windows PowerShell)

Keep the local PHP HTTP server running on `127.0.0.1:8787`. In PowerShell:

```powershell
cd C:\Projects\stillwired-gate1
$php = 'C:\xampp\php\php.exe'
& $php -l src\EventListener\InvestigationToolAuditSubscriber.php
& $php bin/console sw:lab reset
& $php bin/console sw:lab revoke
& $php bin/console sw:lab verify
# Preserve this evidence before the next verify overwrites it:
Copy-Item var\evidence\gate1-exposed.json var\evidence\gate2-before.json -Force
Copy-Item var\evidence\gate1-exposed.json.sha256 var\evidence\gate2-before.json.sha256 -Force
Remove-Item var\evidence\agent-tool-events.jsonl -ErrorAction SilentlyContinue
$env:SW_PHP_BIN = $php
node .\run-investigate-bounded.cjs
Get-Content var\evidence\agent-tool-events.jsonl
& $php bin/console sw:lab verify
Copy-Item var\evidence\gate1-exposed.json var\evidence\gate2-after.json -Force
Copy-Item var\evidence\gate1-exposed.json.sha256 var\evidence\gate2-after.json.sha256 -Force
```

## PASS criteria

- `AGENT_EXIT=0` and the report states webhook + scheduled export are still active.
- `agent-tool-events.jsonl` contains **one `requested` and one `succeeded` event for each** observation tool.
- The trace has no `denied` and no `failed` entries.
- `gate2-before.json` and `gate2-after.json` both have `decision=EXPOSED` and both observed flow states `active`; the canary identifiers may differ.
- The verified evidence snapshots' `.sha256` files must match their corresponding JSON bytes. SHA-256 is a local integrity check, not an independently authenticated signature.

If no audit entries are created, **do not declare trace PASS**. Verify the subscriber registration and installed Symfony AI event APIs; keep the existing run as evidence of the functional result only.

If the event listener denies an unfamiliar tool name, use the recorded name to compare with the currently advertised names. Do not automatically widen the allowlist to mutation tools.

## Known scope

Synthetic synchronous flows only; no real vendor integration, delayed-queue guarantee, or discovery of unknown credentials. Do not publish `.env.local`, API keys, raw approval tokens, or local lab data.
