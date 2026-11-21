# STILLWIRED Jury Kit — integration steps

This overlay is for your **already-working Windows project**. It does not replace your installed `composer.json`, `composer.lock`, MCP configuration, C1/C2 code, or the recorded evidence.

1. Extract `tools/` and `docs/` into `C:\Projects\stillwired-gate1` (preserve folders).
2. Confirm the existing C1 and C2 runner output from your Windows environment. Do **not** re-run them unless missing evidence; they reset synthetic lab state.
3. Run `node .\tools\build-jury-demo.cjs`. This is read-only and checks all six input evidence sidecars before generating `dist/stillwired-jury-demo.html` and `dist/stillwired-jury-evidence.json`.
4. Open `dist/stillwired-jury-demo.html` in Chrome. Use the stage buttons or keyboard arrow keys for the 3-part visual demonstration.
5. If you still see `transport: stdio` in `src/Command/SmokeMcpCommand.php`, run `node .\tools\fix-smoke-metadata.cjs` (one narrowly scoped, backed-up source-label correction) and then PHP-lint the file.
6. Run `node .\tools\preflight-release.cjs` to identify gaps. Some failures are likely until you update `composer.json` and commit `composer.lock`.
7. Use `docs/README-PUBLIC-DRAFT.md` to replace the old Gate 1 README **after checking the actual project configuration**. It intentionally is **not** installed as `README.md` automatically.
8. Use `docs/VIDEO-90-SECONDS.md` for screen recording and `docs/SUBMISSION-STORY.md` for the SymfonyConnect submission text.

**Important:** `dist/` may be committed as a generated offline showcase, but never commit `var/lab`, `var/evidence`, `.env.local`, raw approval tokens or live API keys. The generated JSON manifest contains only synthetic facts plus the SHA-256 values checked by this builder. It is not a proof of source authenticity.

**Known limitation:** I have not executed this overlay against your live Windows filesystem; I tested generation and failure behavior using local synthetic fixtures. Your C2 run is validated by the results you supplied.
