# File 10 R101 — Activation role/capability rollback

Review was completed in full before any correction was started.

## Review scope
Activation ordering, schema reconciliation, role/capability mutation, companion-filter extension points, stale activation recovery, shutdown compensation, commit closure, and regression coverage were reviewed against the File 10 release/rollback discipline.

## Frozen finding
`VWLB_Activator::capabilities()` applies `vwlb_activation_role_capabilities` before mutating roles, so companion code may extend the role/capability mutation map. The R61 rollback guard, however, snapshotted and restored only its own hardcoded canonical capability list. A later activation failure could therefore leave a capability added through the approved filter on a role even after rollback. This was an activation-compensation integrity defect.

## Correction batch after review completion
The R61 guard now installs a final-priority capture callback for the approved activation filter, captures the exact fully-filtered role/capability map immediately before mutation, persists each pre-mutation capability state, restores that exact map on shutdown/stale recovery, and refuses activation commit if the mutation snapshot was never captured. A dedicated R101 regression contract was added to the full File 10 QA suite.

The next review round must not begin until the corrected exact head passes the complete PHP 8.3/8.4 source/package/parity workflow.
