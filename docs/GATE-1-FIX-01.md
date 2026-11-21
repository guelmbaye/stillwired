# Gate 1 Fix 01 — MCP attribute namespace

## Symptom

All `bin/console` commands fail in `McpPass.php`: MCP element patterns do not match `Stillwired\Mcp\WebhookTools` or `Stillwired\Mcp\SchedulerTools`.

## Cause

The MCP classes imported the obsolete `Mcp\Server\Capability\Attribute\McpTool` namespace, while the installed `symfony/mcp-bundle` (v1.0.0-BETA1 with `mcp/sdk` ^0.9) expects `Mcp\Capability\Attribute\McpTool`.

## Exactly two PHP code changes

- `src/Mcp/WebhookTools.php`: replace `use Mcp\Server\Capability\Attribute\McpTool;` with `use Mcp\Capability\Attribute\McpTool;`.
- `src/Mcp/SchedulerTools.php`: same replacement.

Keep your original `vendor/`, `composer.lock`, and `.env.local`. You can use `fix-gate1-mcp.ps1` from the root of your EXISTING project for the minimal patch instead of extracting this full archive.

## Acceptance sequence

```powershell
php -l src/Mcp/WebhookTools.php
php -l src/Mcp/SchedulerTools.php
php bin/console about
php bin/console debug:mcp
php bin/console sw:smoke-mcp
```

`MCP_SMOKE_PASS` is not established by the offline tests here. Do not mark Gate 1 PASS until these commands really work on your machine.

References: https://ai.symfony.com/cookbook/build-an-mcp-server ; https://packagist.org/packages/symfony/mcp-bundle
