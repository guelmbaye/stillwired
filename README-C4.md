# STILLWIRED — C4 optional external integration: GitHub Webhooks

## Why this integration exists

C1/C2/C3 are controlled, reproducible **synthetic** experiments. This optional read-only adapter checks whether a **real GitHub repository webhook** has a provider-recorded HTTP delivery after a timestamp supplied by an operator.

It **does not** replace the independent canary/sink verifier, prove that a user was actually revoked, prove receipt by the destination, make a GitHub webhook send data, or declare global closure. It can report `EXPOSED`, `AT_RISK`, or `INCONCLUSIVE`; **never VERIFIED** based on GitHub history alone. It intentionally requests only `GET` endpoints and does not download full payload bodies.

Official API: <https://docs.github.com/en/rest/repos/webhooks> (Get repository webhook / List deliveries for repository webhook; fine-grained Webhooks repository permission **read**).

## Install

Extract the ZIP at the root of the **existing** `C:\Projects\stillwired-gate1` project. Preserve the directories `src/Integration/`, `src/Command/`, `tests/`. No existing PHP or configuration files are replaced; `config/services.yaml` already auto-discovers `Stillwired\` services. The optional command requires the existing `symfony/http-client` package.

Run from PowerShell:

```powershell
cd C:\Projects\stillwired-gate1
php -l src\Integration\GitHubWebhookEvidence.php
php -l src\Integration\GitHubWebhookReader.php
php -l src\Command\GitHubWebhookAuditCommand.php
php tests\run-c4-github.php
php tests\run-c4-github-http.php
php bin/console list sw:github
node .\tools\preflight-release.cjs
```

`run-c4-github-http.php` uses Symfony's `MockHttpClient` and **makes no real GitHub API request**.

## Optional real GitHub check (authorized test repository only)

1. Identify an existing webhook (or set one up in a repository you control), and find its numeric ID in **Settings > Webhooks**. Its receiver must be a legitimate, controlled endpoint; do not expose STILLWIRED's local MCP development server on the public internet.
2. Create a **fine-grained PAT restricted to only that repository with Webhooks: Read**. Set it securely as environment variable `SW_GITHUB_TOKEN` on your own machine. **Never paste the token into the chat, the README, logs or the repository.** Do not set write permissions merely for the audit.
3. Supply an ISO-8601 cutoff timestamp from a separately documented offboarding/revocation event. The integration cannot verify that the event really occurred.
4. Run (replace placeholders):

```powershell
php bin/console sw:github:webhook-audit `
  --owner=YOUR_OWNER --repo=YOUR_REPO --hook-id=12345 `
  --since=2026-11-21T09:00:00Z
```

5. Inspect the JSON. A provider-reported 2xx/3xx delivery after cutoff yields `EXPOSED` (provider-side HTTP acceptance). A failed attempt after cutoff yields `AT_RISK`. No attempts after cutoff yields `AT_RISK` if still configured active, else `INCONCLUSIVE`. None of those results establishes that no other residual flows exist.

**Privacy:** JSON intentionally omits webhook URLs, payloads, secrets and authorization tokens. Even a sanitized delivery ID may be internal operational information; do not publish unreviewed output. If the API fails, the CLI prints only the error type and exits non-zero, rather than leaking response bodies or headers.

## Acceptance / stop condition

**C4 offline integration readiness:** `C4_GITHUB_CONTRACT_PASS 9/9`, `C4_GITHUB_HTTP_MOCK_PASS 3/3`, `RELEASE_PREFLIGHT_PASS`.

**C4 external integration:** only claim it after the command completes against an authorized **real** GitHub webhook and returns provider-derived receipts. Do not claim that C4 is a verified independent canary or an actual compromised vendor scenario. Never block submitting C1–C3 if external access or time is insufficient.

## Evaluation: why it matters

- A real external API with actual permission scopes: **Webhooks(read)**.
- Explicit separation: provider metadata vs provider delivery history vs independent sink receipt.
- Conservative proof semantics: absence of delivery data does not produce VERIFIED.
- Works alongside the synthetic C1/C2/C3 experiment without changing or weakening the verifier.

## Optional next integration, not included here: FINOS CALM

Use CALM's nodes, relationships and flows to create an **expected flow inventory**. Compare it to the MCP-discovered/probed flows: an unprobed modeled route becomes a `SCOPE_GAP`, not `VERIFIED`. It should only be claimed as an integration if a real CALM document is parsed, schema-validated with the CALM CLI and its coverage affects a test/verdict. CALM design documents are architecture evidence, not live asset discovery or proof of data cessation.

Do not add Fluxnova BPMN/DMN now; it would duplicate the Revokeline control plane and divert attention from Symfony AI/MCP. Concentrate on one real evidence source and a clean jury experience.
