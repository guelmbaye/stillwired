# Gate 0 — Proof contract

## The baseline to defeat

An operator disables the human account and assumes all data flow from the contractor has stopped. In the synthetic organization, the webhook subscription and scheduled export belong to separate integration identities. Both still deliver canary records.

## Primary question

**Can a revocation workflow detect and eliminate independently authorized outgoing flows, and substantiate that result with observed deliveries and source-state checks?**

## Three decisions

| Decision | Deterministic rule |
| --- | --- |
| EXPOSED | Any enumerated source reports active **or** any canary is delivered |
| INCONCLUSIVE | No active/delivery evidence, but any source observation is unknown |
| VERIFIED | Every enumerated source reports disabled **and** no canary delivered |

## Exclusions (not hidden claims)

- Not a universal proof of no residual access; unknown integrations are out of scope.
- This synchronous simulator is not a test for delayed job queues and cannot exclude future scheduled deliveries.
- It is an internal, synthetic contract, not a third-party system connector.
- Human review, tool authorization and forensic chain of custody must be separately implemented.

## Next Gate

Install a real Symfony 7.4 project; implement independent MCP HTTP/STDIO endpoints with the official PHP SDK through the MCP bundle. Move FlowLab behind individual service adapters and test over the actual protocol. Connect the AI agent through MCP and display evidence side by side in Twig. Final submission requires video and public repository; Gate 0 alone does not meet this condition.
