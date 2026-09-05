# File 10 — R112 Live Attendance, Consent and Reminder Durability Review

Baseline: `d5fbe5bdc13b2cbf8b3024dad6c42a531d480fbf`

## Review scope
The entire round was reviewed before any correction was prepared. Scope: waiting-room policy, attendee row locking/re-entry, capacity enforcement, recording-consent writes and R73 finalization proof, reminder scheduling/reconciliation, reminder job execution, live resources and relevant direct-DB failure behavior.

## Frozen findings
R112 is defect-bearing.

1. The waiting-room mutation did not enforce the persisted `access_policy_json.waiting_room` switch; a disabled waiting room could still accept joins.
2. Capacity truth used direct database reads without local error checks inside a transaction. A failed `COUNT(*)` could be cast to zero and later transaction queries/rollback could clear `$wpdb->last_error`, allowing a fail-open capacity decision.
3. A previously inactive attendee row bypassed the capacity check on re-entry because capacity was checked only when no attendee row existed.
4. Direct attendee/event reads in the waiting-room and consent mutations could collapse database unavailability into ordinary not-found/conflict outcomes after transaction cleanup cleared the database error.
5. Reminder reconciliation examined only the first 500 global pending/retry reminder jobs, so an idempotent/replayed setup could leave stale duplicate jobs for the same event outside that window.
6. Reminder execution did not revalidate current live-event state or the schedule timestamp. A stale reminder job could emit after a schedule change or after the event was no longer in a remindable state.

R73's finalization proof already fails closed on unreadable consent state and was retained.

## Correction batch
After findings freeze, `VWLB_R112_Live_Attendance_Durability_Guard` was added. It replaces the public waiting-room and consent mutations with fail-closed row-locking paths, enforces the waiting-room switch, applies capacity to inactive-row re-entry, validates reminder input, reconciles all event-specific pending/retry reminder jobs in bounded pages after successful creation, and replaces reminder execution with current-state/schedule revalidation. Reconciliation failures after live creation use the existing reconciliation-required external-effect error contract so retry protection remains locked.

Regression contracts were added to `tests/file10-r101-r120-contracts.sh`.

## Gate
Do not begin R113 until the exact R112 correction HEAD passes the complete File 10 Release QA matrix and package/source parity checks.
