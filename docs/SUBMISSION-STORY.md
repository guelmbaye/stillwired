# Symfony hackathon — STILLWIRED submission copy (English)

**Title:** STILLWIRED — The Dashboard Is Green. The Data Is Still Leaving.

**One-line pitch:** A Symfony AI + MCP incident investigator that catches “false-green” revocations by probing whether supposedly disabled integrations still deliver data.

## What it does

Revoking a user is not the same as stopping every integration that user originally configured. STILLWIRED models this precise security failure in a fully synthetic lab: a webhook and an export service keep delivering even after the contractor is revoked. The hardest scenario is a false green: both services report `disabled`, yet fresh canaries still arrive.

A Symfony AI agent investigates the two sources through real MCP connections. It uses their observation and controlled canary tools, cites receipt IDs and recommends blocking closure. An independent PHP verifier checks the persisted sink and returns `EXPOSED`, `INCONCLUSIVE` or `VERIFIED`, without taking its decision from the model's prose. Only separately approved operator actions can call the mutation tools.

## Why Symfony AI and MCP matter

The interesting boundary is not “LLM calls an API.” Symfony AI builds an evidence-grounded investigation from separate MCP sources, while the MCP protocol gives each source an independently invocable capability surface. The agent is restricted to investigation tools, with an additional event-level guard. PHP independently decides what the evidence proves. The same lab can show what happens when sources are unavailable or a supposedly disabled flow is reactivated.

## Demo outcome

In the adversarial synthetic run, the **status-only baseline** returned `VERIFIED`, while the canary-based verifier returned `EXPOSED` after two observed deliveries. A separate AI run executed four MCP tools, cited two persisted delivery receipts, made no remediation calls, and left the PHP verdict `EXPOSED`. After approved stops in the separate C1 run, a fresh test returned `VERIFIED` with zero new deliveries.

These are controlled lab tests, **not** measurements of any real provider or evidence of unknown integrations. The SHA-256 sidecars check local integrity; they are not independent source signatures.

## Built with

PHP · Symfony · Symfony AI Agent · MCP SDK · Symfony MCP Bundle · Streamable HTTP · JavaScript test runners · SHA-256 verification

## Useful links

- Public repository: **ADD ACTUAL GITHUB URL AFTER PUSH**
- Demo video (90 seconds): **ADD ACTUAL VIDEO URL AFTER UPLOAD**
- Testable offline replay: `dist/stillwired-jury-demo.html` after running `node tools/build-jury-demo.cjs`
