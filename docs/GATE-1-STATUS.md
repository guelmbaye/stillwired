# Gate 1 status — Integration candidate, NOT PASS

## Validated in the build environment

- PHP syntax check on all generated classes: PASS.
- Gate 0: 8/8 PASS (same historical deterministic tests).
- Independent persistent source/sink, approvals, outage and reactivation: 10/10 PASS.
- Composer installation: NOT RUN (Composer unavailable; no outbound package DNS).
- Symfony container: NOT RUN.
- MCP transport and tool calls: NOT RUN.
- LLM tool calling: NOT RUN.

## Objective proof required on Windows

1. `php bin/console debug:mcp --client=stillwired --server=webhook`
2. `php bin/console debug:mcp --client=stillwired --server=scheduler`
3. `php bin/console sw:lab reset`
4. `php bin/console sw:smoke-mcp`
5. `php bin/console sw:lab revoke`
6. `php bin/console sw:lab verify` (EXPOSED + 2 deliveries)
7. Approval and stop for each flow via MCP
8. `php bin/console sw:lab verify` (VERIFIED + 0 new deliveries)
9. Provider outage (INCONCLUSIVE), reactivation (EXPOSED).

Only after steps 1–9 may this gate be called PASS. No fabricated logs or screenshots. No claim of AI novelty without a real provider-assisted investigation and deterministic baseline comparison.
