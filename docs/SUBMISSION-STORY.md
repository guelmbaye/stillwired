# STILLWIRED — Symfony AI & MCP Hackathon submission text

**Project name:** STILLWIRED

**Tagline:** The dashboard is green. The data is still leaving.

## One paragraph (submission form)

STILLWIRED exposes a dangerous offboarding blind spot: access can be revoked while webhooks and exports continue delivering data. Built with Symfony AI and the PHP MCP SDK, its agent investigates two independent integration sources through MCP tools, performs controlled synthetic canary probes, and submits evidence to an independent PHP verifier that refuses false closure. Adversarial A→B→A′ tests reveal how identical “disabled” statuses can conceal different delivery outcomes. In a separate real GitHub experiment, a webhook remained operational 90 seconds after its provisioning token was deleted, with matching delivery evidence from GitHub and Webhook.site. A native Symfony Webhook receiver also validates signed HTTP events and stores deduplicated, hash-chained receipts in local tests.

## What it does

A revoked human account does not always revoke the integrations that person previously provisioned. In STILLWIRED's reproducible synthetic lab, a webhook and scheduled export can keep transmitting even when their dashboards claim `disabled`. The Symfony AI agent asks the right questions using separate, actual MCP source tools; it cannot issue approvals or call stop tools. A deterministic PHP verifier, independent of the LLM's prose, adjudicates `EXPOSED`, `INCONCLUSIVE`, or `VERIFIED` using persisted delivery observations.

The C3 A/B/A′ test fixes the advertised source status across three worlds while underlying delivery changes, making a status-only baseline demonstrably unreliable.

## Real-world evidence: GitHub webhook persistence (C4/C5)

In a controlled repository, GitHub's security log recorded deletion of the temporary provisioning PAT on **November 21, 2026 at 13:14:33 UTC**. At **13:16:03 UTC**, a configured repository webhook delivered another push event with provider-reported HTTP 200. Webhook.site independently displayed the same `X-GitHub-Delivery` GUID. The GitHub API connector, run with a separate read-only token and an operator-supplied cutoff, returned `EXPOSED` for the post-cutoff delivery.

This is **not** a GitHub vulnerability claim, a general claim about identity offboarding, or evidence that Symfony AI automatically observed the token deletion. It establishes persistence of a resource after the provisioning credential was revoked, in this controlled experiment.

## Native Symfony Webhook receiver (C6)

A separate local integration uses `symfony/webhook`, `RemoteEvent`, HMAC-SHA256 validation, GUID deduplication, and a local hash-chained receipt ledger. Tests: **16/16** domain checks; HTTP **401** for an invalid signature and **202** for a valid signed event; duplicate GUIDs yield one receipt and `HASH_CHAIN_OK`.

C6's HTTP tests were signed **synthetic local requests**; no live GitHub-to-Symfony delivery is claimed. Local hashes are not independent signed timestamps.

## Built with

Symfony AI Agent · Symfony MCP Bundle / PHP MCP SDK · MCP over localhost HTTP · Symfony Webhook / RemoteEvent · PHP deterministic verifier · Symfony HttpClient · Node.js controlled adversarial runners.

## Links

- **Public repository:** https://github.com/guelmbaye/stillwired
- **Offline demo:** `dist/stillwired-jury-demo.html` in the repository (C1/C2 snapshot)
- **90-second video:** add the final public/unlisted YouTube or Vimeo URL in the submission form when available
