# STILLWIRED — 90-second video plan (English)

**Rule:** record real terminals and actual outputs, not a narrated mockup. The HTML stage is a **replay from saved evidence**, and should be clearly described as such. It is fine to record the known-good terminal run before the final video edit.

| Window | On screen | Exact narration cue |
|---|---|---|
| 00–10 s | STILLWIRED title; incident description | “We revoked the contractor's user account. That should have stopped the data. But did it?” |
| 10–25 s | Live C1 `STATUS_ONLY=VERIFIED BUT CANARY_VERDICT=EXPOSED` + two event receipts in the offline viewer | “Two providers say disabled. Yet both deliver new canaries. A status-only check would incorrectly close the incident.” |
| 25–47 s | `node run-competitive-ai-c2.cjs`, focus on `4_TOOLS_SUCCEEDED`, `MODEL_CITED_TWO_RECEIPTS=PASS` and, if available, sanitized real agent report | “Symfony AI investigates through two independent MCP servers. It calls four tools, tests the delivery paths, and cites both receipts.” |
| 47–63 s | Actual `c2-agent-tool-events.jsonl` and `c2-php-verdict.json`, or the offline C2 evidence replay | “The agent has no remediation permissions. Independent PHP evaluates the persisted evidence and blocks closure: exposed.” |
| 63–78 s | Actual C1 approved stops and re-probe, or C1 replay explicitly labeled | “After operator-approved stops, a fresh verification finds no new deliveries in the tested window.” |
| 78–90 s | Repo tree / test outputs / final link | “No simulated vendor claim is trusted without a delivery test. Clone, run, and inspect the evidence.” |

**Production notes:** 1920×1080 or 1280×720, 30 fps, large terminal font, hide API keys, tokens, usernames and unrelated tabs. Clearly label C1 and C2 as **separate controlled experiments**. Keep under 90 seconds. Avoid implying real customer systems or real vendor misreporting.
