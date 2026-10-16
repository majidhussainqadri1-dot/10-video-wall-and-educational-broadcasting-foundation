# Review/Fix Round 2 — Adversarial, Concurrency and Failure Paths

## Attack/failure review

- Request-controlled authorization: blocked.
- IDOR/object ownership: command services recheck native ownership/state.
- Lost updates: versioned updates and transactions used for mutable canonical records.
- Duplicate side effects: idempotency keys and webhook/provider-event uniqueness.
- Key leakage: raw secrets absent from database, logs, audit and repository.
- Webhook replay: provider signature plus provider-event unique key.
- Queue outage: bounded exponential retry, dead-letter and operator repair.
- Cron delay: request-time live reconciliation plus scheduled worker.
- Partial media creation: asset and processing states remain explicit; publication gate blocks unready assets.
- Private cache/index leak: private history/studios use no-store/noindex.
- Provider outage: adapter errors return explicit degraded/unavailable states without guessing success.
- Copyright decision history: append-only audit and versioned takedown lifecycle.
- Accessibility failure: live regions, keyboard focus, captions, reduced motion, 44px controls and RTL logical layout.

## Residual external risks

Real provider APIs, Hostinger/LiteSpeed behavior, browser assistive technology, object storage/CDN deletion and backup restoration must be tested in staging; source review cannot fabricate that evidence.
