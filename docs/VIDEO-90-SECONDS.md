# STILLWIRED — 90-second demo storyboard

**Core rule:** show genuine terminal outputs or screenshots of actual evidence. Label replayed HTML as a **saved C1/C2 evidence snapshot**. Do not present separate synthetic and real experiments as one end-to-end live integration.

| Time | Visual | Narration cue |
| --- | --- | --- |
| 00–12 s | Title and false-green screen | “The dashboard says access is disabled. But is the data still leaving?” |
| 12–29 s | GitHub C5: security log `personal_access_token.destroy` at 13:14:33 UTC; subsequent webhook delivery at 13:16:03 UTC | “In a controlled GitHub test, the provisioning token was removed. Ninety seconds later, the webhook still delivered.” |
| 29–39 s | Webhook.site matching delivery GUID | “A separate receiver captured the matching delivery ID.” |
| 39–59 s | C2 actual Symfony AI/MCP tool events and evidence, or clearly labeled saved C1/C2 viewer | “Symfony AI investigates two synthetic integration sources through MCP, runs controlled canary probes, and cites delivery receipts.” |
| 59–73 s | PHP independent verdict; C3 A/B/A′ statuses | “The agent cannot remediate. The PHP verifier blocks false closure: VERIFIED, EXPOSED, VERIFIED—even when reported statuses stay identical.” |
| 73–85 s | C6 terminal: invalid HMAC 401, accepted signed event 202, `HASH_CHAIN_OK` | “Symfony Webhook also verifies and stores signed receiver evidence in a separate local test.” |
| 85–90 s | Repository URL and one-line value | “STILLWIRED. Verify the flow—not the green status.” |

**Editing:** 16:9, readable 18–24 pt terminal text, large timestamps, no raw API keys/HMAC, approval tokens, personal IP addresses, unrelated browser tabs, or screenshots of private settings beyond sanitized evidence. Keep runtime **at most 90 seconds**. Consider placing the C1/C2 offline viewer on screen between real terminal clips, labeled accordingly.
