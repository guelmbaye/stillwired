# STILLWIRED — C3 / REVOKELINE method: evidence-first A-B-A

This package is a **controlled competitive experiment**, not a new product feature or a replacement for C1/C2.

## Why C3 is needed

C1 proves that status-only checks can falsely close an incident. C2 demonstrates Symfony AI making four real MCP tool calls and citing two delivery receipts. But a single **false-green** scenario might reward an agent that simply expects exposure every time.

C3 asks the harder causal question: **same incident question, same two source declarations (`disabled`), but different actual data movement — does the agent change its recommendation and does the independent PHP verifier change its verdict?**

| World | Source-reported states | Hidden actual flows | Expected AI recommendation | PHP verdict |
| --- | --- | --- | --- | --- |
| A | Both disabled | Both actually stopped | `REQUIRE_PHP_VERIFIER` | `VERIFIED` |
| B | Both disabled | Both actually delivering | `BLOCK_CLOSURE` | `EXPOSED` |
| A′ | Both disabled | Both stopped again | `REQUIRE_PHP_VERIFIER` | `VERIFIED` |

The **same command, prompt, model configuration, MCP servers and tool allowlist** are used in each world. Hidden `enabled` state and world labels are never passed as model messages. **Qualification:** the providers' `revision` fields can differ between worlds; their reported `state=disabled` is identical. The system instruction includes a general rule for recognizing contradictions; this is not a completely unprompted blind investigation.

Each stage independently checks (a) four permitted MCP tool calls requested and succeeded, (b) no stop/approval action by the agent, (c) exact canary receipts in the persisted sink, (d) unchanged source state during the investigation, (e) the signed-off JSON verifier result and its local SHA-256 sidecar, and (f) the agent recommendation. The agent's text **never decides the authoritative PHP verdict**.

## Run from the existing working Windows project

Extract `run-competitive-aba.cjs`, `tools/build-aba-proof.cjs` and this document into `C:\Projects\stillwired-gate1` preserving paths. This does **not overwrite** any Symfony, MCP, C1, C2, Composer or test files.

Preconditions: local HTTP MCP server already running on `127.0.0.1:8787`, Composer dependencies installed, Symfony AI key configured locally, and C2 proven working.

```powershell
cd C:\Projects\stillwired-gate1
$env:SW_PHP_BIN = 'C:\xampp\php\php.exe'
node .\run-competitive-aba.cjs
node .\tools\build-aba-proof.cjs
Start-Process .\dist\stillwired-aba-proof.html
```

Each subprocess has a timeout: 15 seconds for ordinary commands, 60 seconds for each of three AI investigations. The runner **resets only the local synthetic `var/lab` state**, generates multiple synthetic deliveries, uses one-use local approval tokens for fixture setup, and saves its evidence under `var/evidence/c3-*`. It does not transmit tokens to the model and does not print approval tokens. This run consumes three real model calls, so token usage and cost depend on the configured provider.

**Expected (not yet validated against your Windows system):**

```text
WORLD_A: STATUS_ONLY=VERIFIED ACTUAL=disabled AGENT=REQUIRE_PHP_VERIFIER PHP=VERIFIED RECEIPTS=0
WORLD_B: STATUS_ONLY=VERIFIED ACTUAL=active AGENT=BLOCK_CLOSURE PHP=EXPOSED RECEIPTS=2
WORLD_A2: STATUS_ONLY=VERIFIED ACTUAL=disabled AGENT=REQUIRE_PHP_VERIFIER PHP=VERIFIED RECEIPTS=0
BASELINE_VERDICTS=VERIFIED,VERIFIED,VERIFIED
PHP_VERDICTS=VERIFIED,EXPOSED,VERIFIED
FALSE_SAFE_ON_EXPOSED_WORLDS=status_only:1/1,php:0/1
C3_REVOKELINE_ABA_PASS
C3_EVIDENCE_SHA256_PASS: 10 files
```

The scripts were syntax-checked and executed successfully against a **separate simulated CLI fixture**, not against the actual Windows project. A model can fail this strict A-B-A test through wording or interpretation; inspect the archived model outputs in `var/evidence/c3-*-agent.txt` to determine whether the problem was genuine reasoning failure or just an output-format mismatch. Do not change the expected benchmark results to make a failure pass.

## Interpretation: REVOKELINE / competitive-first gates

1. **Pain / buyer:** post-revocation incident owner must not close while an outbound route still delivers.
2. **Blind-ish world:** source `state` is held identical while hidden actual behavior changes. No world truth is inserted into the model's user/system messages.
3. **Independent outcome:** PHP verifies new canary receipts in a persistent sink; the model cannot fabricate the authoritative decision.
4. **A-B-A causal adaptation:** a non-adaptive `EXPOSED`-always or `VERIFIED`-always agent fails at least one world.
5. **Fail-closed:** Gate 1 already demonstrated `INCONCLUSIVE` on unavailability and `EXPOSED` on reactivation. Preserve those as release regressions.
6. **Sponsor / technology necessity:** Symfony AI must perform real, audited MCP observation/probing. Do not assert that the LLM is mathematically necessary for the fixed two-flow deterministic verdict; its demonstrated role is evidence-grounded interpretation and investigation in natural language.
7. **Obvious Magic / anti-MIRROR-OPS:** Show the same green provider declarations above three visibly different delivery outcomes in under 12 seconds, then show the evidence receipts.

**Important limitations:** This is one controlled exposed world, not a statistical false-safe estimate, nor a comparison against the best third-party incident response products. Local SHA-256 sidecars check file consistency, not independent source authenticity. No unknown integrations, delayed queues, real external vendors, production IAM, or customer traffic are validated.

## Clear the current preflight blocker first

The previously supplied jury kit already contains the required narrow fix. From the project root:

```powershell
node .\tools\fix-smoke-metadata.cjs
php -l src\Command\SmokeMcpCommand.php
node .\tools\preflight-release.cjs
```

Expected: `METADATA_HTTP_PATCH_PASS` (or `METADATA_ALREADY_HTTP`) and `RELEASE_PREFLIGHT_PASS: 0 blocking, 0 warnings`. This fixes labels describing the already-validated HTTP transport; it does **not** modify the business verifier, MCP transport configuration, or evidence files already generated. Existing historic STDIO smoke evidence should be preserved as historical, not relabeled retroactively.

**Publication:** `dist/stillwired-aba-proof.html` is a sanitized offline replay generated from verified local evidence. `var/lab`, `var/evidence`, `.env.local` and approval tokens must stay untracked. Do not present C1/C2/C3 as one continuous live incident; they are separate controlled tests.
