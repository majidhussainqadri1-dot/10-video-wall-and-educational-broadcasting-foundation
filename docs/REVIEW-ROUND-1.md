# Review/Fix Round 1 — Architecture, Security and Data Integrity

## Defects found and corrected

1. REST permission context could have been supplied as a request argument. Replaced with closed-over server-side callbacks.
2. Direct route rendering bypassed theme lifecycle and late asset handling. Replaced with `template_include` and route-aware enqueue.
3. Scheduled publication used reflection to invoke a private gate. Publication gate made explicit and directly tested.
4. Provider-issued stream keys were discarded. Credential issuance now displays the provider key once and stores only its hash.
5. External custom provider lacked a built-in signed webhook option. Added HMAC adapter using wp-config constants.
6. Caption tracks had no safe delivery route. Added public VTT endpoint with video visibility recheck.
7. Activation used a custom cron schedule before registering it. Activation now installs the schedule definition before scheduling.
8. Legacy plugin coexistence could duplicate routes. Added visible migration/cutover warning.

## Retest

PHP syntax, JavaScript syntax, state-machine unit tests, secret scan, requirements contracts and deterministic build are run by `tests/run-all.sh` and GitHub Actions.
