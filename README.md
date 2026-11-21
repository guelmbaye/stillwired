# STILLWIRED

### The dashboard is green. The data is still leaving.

**STILLWIRED is a Symfony AI + MCP investigation lab for a specific offboarding failure:** a human credential is revoked, but independent integrations (webhooks and scheduled exports) may continue sending data. The investigator tests the claimed state against fresh delivery evidence; a deterministic PHP verifier—not the model's prose—decides whether the tested flows are `EXPOSED`, `INCONCLUSIVE`, or `VERIFIED`.

**Hackathon demo:** [Open the offline jury showcase](dist/stillwired-jury-demo.html) (C1/C2 evidence snapshot; no live API call). See [Submission story](docs/SUBMISSION-STORY.md), [C4 GitHub adapter](README-C4.md), [C6 Symfony Webhook receiver](README-C6.md), and [REVOKELINE A/B/A′ method](REVOKELINE-METHOD-C3.md).

## The failure that STILLWIRED catches

A status-only checker sees both integrations reporting `disabled` and prematurely concludes that access has been closed. STILLWIRED generates controlled canaries and checks whether either integration *actually delivers* to a separate synthetic sink.

| Observation | Status-only baseline | Independent PHP verifier |
| --- | --- | --- |
| Both integrations report `disabled`, but two fresh canaries arrive | `VERIFIED` (false green) | **`EXPOSED`** |
| Both integrations stopped with separate operator approvals; fresh probes deliver nothing | `VERIFIED` | `VERIFIED` for the **tested paths/window** |
| Required source or evidence is unavailable | May appear clear | **`INCONCLUSIVE`** |

The agent can observe and conduct authorized *synthetic* probes through scoped MCP tools. It cannot call the `stop_*` tools or authorize remediation. A human must approve each stop; PHP independently verifies the result.

## What is implemented and tested

| Gate | Scope | Result / limitation |
| --- | --- | --- |
| C1 — False Green | Two synthetic MCP sources report disabled while independent canaries reach the sink | Status-only `VERIFIED` vs PHP `EXPOSED`; approved stops lead to a new `VERIFIED` probe |
| C2 — Active Symfony AI | Agent calls four observe/canary tools over local MCP HTTP; tool events and receipt IDs audited | `C2_ACTIVE_PROBE_PASS`; the agent cannot perform remediation |
| C3 — A → B → A′ | Reported status is `disabled` in all three controlled worlds; underlying deliveries change | PHP `VERIFIED → EXPOSED → VERIFIED`; synthetic adversarial benchmark |
| C4 — GitHub read-only connector | Queries a real repository webhook and its delivery history | Actual GitHub API result: post-cutoff HTTP 200; provider log is **not** an independent sink receipt |
| C5 — External revocation experiment | GitHub security log confirms deletion of a temporary provisioning PAT; subsequent `push` matches Webhook.site | Token removed **2026-11-21 13:14:33 UTC**; matching delivery **13:16:03 UTC**, **90 seconds later**; correlation performed by operator |
| C6 — Native Symfony Webhook | `symfony/webhook` + `RemoteEvent`, HMAC validation, deduplicated local hash-chained receipt ledger | Domain tests **16/16**; local signed HTTP: invalid signature **401**, valid **202**; **not a live GitHub-to-Symfony receiver test** |

**C5 is not a GitHub vulnerability claim.** Removing a provisioning credential does not necessarily delete resources previously created with it. The experiment demonstrates why credential revocation and downstream data-flow shutdown need separate verification. The C4 CLI is not integrated into the C2 agent's synthetic MCP investigation.

## Architecture

```text
Incident / offboarding claim
  ├─ Symfony AI Agent (investigation only)
  │    ├─ Symfony MCP client → webhook MCP source (localhost HTTP)
  │    └─ Symfony MCP client → scheduled-export MCP source (localhost HTTP)
  │                                ↓
  │                         controlled synthetic canaries
  │                                ↓
  │                         persistent synthetic sink
  └─ Independent PHP verifier → EXPOSED / INCONCLUSIVE / VERIFIED

Optional, separate evidence paths:
  ├─ C4: GitHub REST API (read-only provider configuration and delivery history)
  └─ C6: Symfony Webhook → HMAC parser → RemoteEvent consumer →
          deduplicated local receipt ledger (SHA-256 integrity chain)
```

**Built with:** PHP, Symfony, Symfony AI Agent, Symfony MCP Bundle / PHP MCP SDK, Streamable HTTP on loopback, Symfony Webhook / RemoteEvent, Node.js test harnesses, and a deterministic PHP evidence verifier.

## Reproduce on Windows

**Prerequisites:** PHP compatible with the committed lockfile (development runs were performed on PHP 8.5.12), Composer 2, Node.js 18+, and PowerShell. Symfony 8.1.11 was observed in the developer environment. The MCP laboratory binds **only to `127.0.0.1:8787`**; do not expose the development MCP server to the Internet.

```powershell
# From a new clone of this repository
git clone https://github.com/guelmbaye/stillwired.git
cd stillwired
composer install
Copy-Item .env.example .env

# Tests that do not require an LLM key or live GitHub access
php tests/run.php
php tests/run-gate1-domain.php
php tests/run-competitive.php
php tests/run-c2-contract.php
php tests/run-c4-github.php
php tests/run-c4-github-http.php
php tests/run-c6-domain.php
```

Start the **local** HTTP server in another PowerShell window:

```powershell
php -S 127.0.0.1:8787 -t public public/router.php
```

Then, in the project terminal:

```powershell
php bin/console sw:smoke-mcp
node .\run-competitive-false-green.cjs
```

Expected markers include `MCP_SMOKE_PASS` and `COMPETITIVE_FALSE_GREEN_PASS`. The false-green runner **resets and modifies only synthetic `var/lab` state**; use an isolated working copy when preserving another run.

### Live AI and A/B/A′ benchmark (optional)

Supply a real `OPENAI_API_KEY` **only** in `.env.local`, not in any tracked file. Keep the HTTP server running, then:

```powershell
node .\run-competitive-ai-c2.cjs
node .\run-competitive-aba.cjs
```

The AI tests make actual model calls, incur provider usage, and reset the synthetic lab. They are separate controlled executions, **not** a single chronological production incident.

### Local C6 signed HTTP test (optional)

Place a locally generated, nonempty random shared secret (`SW_C6_GITHUB_WEBHOOK_SECRET`) in `.env.local` and make the **same value** available to the Node.js test process. Restart the local PHP HTTP server after changing its environment/configuration. Do **not** reuse a GitHub PAT as the webhook HMAC secret.

```powershell
node .\tools\test-c6-native-http.cjs
php bin/console sw:c6:receipts
```

Expected: `C6_NATIVE_HTTP_PASS` (bad HMAC = HTTP 401, accepted signed POST = HTTP 202), with `HASH_CHAIN_OK` and exactly one receipt per repeated GUID. These requests are **locally generated**, not sent by GitHub. See [C6 setup and limits](README-C6.md).

### Real GitHub provider audit (optional)

On a repository you control, create a fine-grained token with **Webhooks: Read** and set `SW_GITHUB_TOKEN` **locally**. Never commit or paste the token. Then:

```powershell
php bin/console sw:github:webhook-audit `
  --owner=YOUR_OWNER --repo=YOUR_REPO --hook-id=NUMERIC_HOOK_ID `
  --since=YYYY-MM-DDTHH:MM:SSZ
```

The `--since` timestamp must be supported by a separately documented event. Provider-side 2xx receipts are informative, but GitHub history by itself does not prove a destination independently received the request. See [C4](README-C4.md).

## Reproduce or inspect jury evidence

- [Open the **committed offline demo**](dist/stillwired-jury-demo.html) immediately after cloning; it displays a sanitized evidence-backed snapshot of **C1/C2 only**.
- To **regenerate** that HTML, first reproduce **both** C1 and C2 locally, then run `node tools/build-jury-demo.cjs`. The builder requires six local SHA-256 sidecars under ignored `var/evidence/`; those raw files are intentionally **not in Git**.
- The generated page is a **snapshot**, not a live agent, hosted product, or replay of C5/C6.
- `node tools/preflight-release.cjs` checks selected release prerequisites; it is **not** a full secret scan or proof of a clean-clone installation.

## Security and evidence boundaries

- The synthetic lab covers **two enumerated integrations**, not unknown accounts, production systems, delayed queues, or all possible exfiltration paths.
- `VERIFIED` means no new delivery was observed on the **tested, reachable paths during the probe window**; it does not guarantee global termination of access.
- A missing receipt, unreachable source or incomplete observation is not proof of closure (`INCONCLUSIVE`).
- Local SHA-256 sidecars and the C6 hash chain detect some tampering, but **are not independent, trusted timestamp attestations**.
- GitHub C5 evidence was collected through GitHub security/delivery pages and a separate Webhook.site receiver; it does not mean the C6 Symfony receiver obtained a live GitHub event.
- The local MCP transports are development-only, unauthenticated and loopback-bound. Never expose `/mcp/*` publicly or run the Symfony development/debug server on an Internet-facing interface.
- `/.env`, `/.env.local`, `var/`, secrets, raw approval tokens and sensitive delivery payloads are **never published**.

**License:** MIT. **Status:** reproducible research/demo implementation for the Symfony AI & MCP Hackathon, not a production offboarding or IAM solution.
