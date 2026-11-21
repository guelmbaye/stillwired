# Gate 1 — Symfony AI + MCP integration contract

## Non-negotiable differentiator

A fixed two-tool script is not an AI innovation. In the next gate, the agent receives a natural-language incident description, discovers the relevant independent integrations via actual MCP servers, and explains why revoking a person does not remove a source-owned subscription or a scheduler-owned credential. **All final exposure verdicts remain deterministic PHP decisions.**

## Real MCP topology

- Symfony web/CLI operator console: `IncidentController`, `InvestigateCommand`, Twig read-only proof view.
- Source A MCP server exposes state/trigger/stop for a *source-owned outbound webhook*.
- Source B MCP server exposes state/trigger/stop for a *scheduler-owned recurring export*.
- Each service owns independent persistent state; do not put both behind a shared in-process data object or fabricated tool transcript.
- Symfony AI agent uses `symfony/ai-mcp-tool` and the `mcp_server` config entry to communicate with both.
- A dedicated PHP `Verifier` calls read-only source inspection and reads the controlled sink. The AI never self-certifies success.

Official configuration reference: https://symfony.com/doc/current/ai/bundles/ai-bundle.html#tools-of-a-remote-mcp-server
Official server reference: https://symfony.com/doc/current/ai/bundles/mcp-bundle.html

## Fail-fast milestones

1. Install Symfony and bundles (`symfony/ai-bundle`, `symfony/ai-mcp-tool`, `symfony/mcp-bundle`) from official docs; check installed versions and PHP requirements. Do not assume the prerelease ecosystem is bug-free.
2. Launch two independently addressed MCP servers. From the web app run `php bin/console debug:mcp --client=<client> --server=<server>` for each and capture their advertised tools.
3. Prove baseline: disable human account; inject canary; show deliveries from *both* independent sources in a persisted sink.
4. Agent investigates the incident through real MCP calls; observed network/stdio tool calls should be in sanitized trace.
5. Approval-gated stop operations: explicit per-flow scope, never infer approval from model output.
6. Reprobe. Assert `EXPOSED -> VERIFIED` on healthy services and `INCONCLUSIVE` when one provider is unavailable.
7. Alternate incident phrasing and shuffled candidate integrations: test whether AI identifies the relevant tools from noisy descriptions rather than following a fixed hard-coded sequence.
8. Compare an AI-run to a **deterministic discovery baseline**; if the agent adds nothing measurable beyond a fixed script, drop the AI novelty claim.

## Demo proof package

- `evidence/before.json` + after state, canary IDs, source inspection results.
- `evidence/after.json` with explicit source scope and verifier decision.
- Sanitized MCP transcript proving two distinct servers and actual tool calls.
- Failing negative test (`INCONCLUSIVE` when one server cannot answer).
- Tests, lockfile, git commit, README with one-command quickstart, 90-second video.

## Guardrail

This is a lab for synthetic data and controlled test systems; no production secrets or unauthorized external systems. Checksum != signed attestation. For a real product, queue-drain verification, scheduled follow-up checks, source coverage, and authenticated attestations would be additional work.
