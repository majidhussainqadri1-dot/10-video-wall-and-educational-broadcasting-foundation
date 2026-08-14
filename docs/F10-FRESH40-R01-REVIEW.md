# File 10 Fresh-40 — R01 Review Freeze

Baseline: `c5431ea9ef505759a476b822c041131dca4bda13`
Focus: live-provider create/ingest credential atomicity, malformed-provider behavior, external compensation, and secret/public boundary.
Method: review completed before any R01 correction.

## Supported findings
1. `VWLB_Live::schedule()` accepts any non-WP_Error provider return. A null/malformed adapter result can be treated as success and persisted without a verified provider event reference.
2. Provider live creation occurs before local persistence. If local insert/audit/outbox/idempotency completion fails, the external live event is not synchronously cancelled/confirmed; an action is emitted but orphan prevention is not proved.
3. `VWLB_Live::issue_credential()` dereferences the provider without proving that the provider still exists and accepts malformed non-WP_Error ingest returns.
4. Ingest URL/stream-secret shape is not validated before persistence/one-time return.
5. If an external ingest credential is issued but the local credential transaction fails (including commit/audit failure), no confirmed provider-side revocation/compensation is required.
6. The core credential service returns/audits an internal numeric credential primary key. REST hardening currently strips the response field, but the core contract remains unnecessarily leaky for internal callers/future surfaces.

R01 REVIEW COMPLETE. No R01 fix had been applied when this ledger was frozen.
