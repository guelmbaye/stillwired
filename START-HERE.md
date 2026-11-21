# STILLWIRED — start here

**Latest project overview, safe setup and reproducible tests:** [README.md](README.md).

For judges:

1. Open the committed [offline C1/C2 demo](dist/stillwired-jury-demo.html) (an evidence snapshot, not a live AI execution).
2. Follow the Windows reproducibility commands in [README.md](README.md). Re-run C1 without an API key; C2/C3 require a real model API key and reset synthetic lab state.
3. Read the separate [C4 GitHub REST adapter](README-C4.md) and [C6 Symfony Webhook receiver](README-C6.md) documentation.
4. Use [submission copy](docs/SUBMISSION-STORY.md) and [the video shot list](docs/VIDEO-90-SECONDS.md).

**Safety:** the local MCP development server is loopback-only; never expose it publicly. `.env`, `.env.local`, `var/`, API keys, approval tokens and raw logs are intentionally not committed. C5's real GitHub/Webhook.site experiment is separate from C6's synthetic signed local HTTP test.
