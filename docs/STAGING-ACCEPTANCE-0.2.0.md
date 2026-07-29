# File 10 Staging Acceptance — Version 0.2.0

Do not merge as production-ready or deploy to live until every item is evidenced on `sabrisocialstaging.sabrihomeopathy.com`.

## Installation and migration

- Full backup recorded and restoration path verified.
- Fresh install activates without fatal error.
- Upgrade from 0.1.0 preserves existing posts, tables, terms, media, page maps, and companion data.
- `svw_history` and `svw_audit` are created once and idempotently.
- Unrelated existing pages are not overwritten.
- Activation snapshot and page conflict behavior are verified.

## Authorization matrix

- Founder frontend submission publishes immediately.
- Administrator frontend/admin submission publishes immediately.
- Currently verified doctor submission is pending.
- Pending/suspended/unverified doctor is denied.
- Student, patient, researcher, and general member are denied.
- A role with ordinary WordPress post capabilities cannot create/edit/publish `svw_video` through wp-admin.
- Reserved Founder Update and Platform News categories remain restricted.

## Media and transactional rollback

- Official YouTube watch/share/embed/shorts URLs accepted where intended.
- Fake host containing `youtube.com` in path/query rejected.
- Official Vimeo URLs accepted; fake hosts rejected.
- Local MP4, WebM, and OGG validated against real server MIME handling.
- Invalid MIME and over-limit files fail closed.
- Thumbnail failure leaves no post and no orphan attachment.
- Post/meta/term failure simulation leaves no public or orphaned item.

## Moderation and reports

- Approve/reject/hide/feature produce audit records.
- Reject/hide without note fails.
- Reviewer, time, previous/new state, and note display correctly.
- Report failure shows an error, not success.
- Report state change preserves current selection and audit history.

## Privacy and cache

- Saved Videos and Video History are inaccessible without login.
- LiteSpeed and Hostinger do not cache authenticated private output.
- Response headers contain private/no-store and noindex controls.
- WordPress export returns reactions, saves, history, reports, and audit records.
- Erasure removes private interactions, anonymizes retained reports/audits, and recounts totals.
- Two-user cache test proves no cross-user disclosure.

## History and interactions

- External video view enters authenticated history.
- Local video progress saves at intervals and resumes correctly.
- Completed local video is marked completed.
- Like/Dislike/Save toggles work and counters remain consistent.
- Video deletion removes owned interaction rows.
- User deletion removes/anonymizes owned rows.

## Performance, responsive, and accessibility

- Public and private pagination works with more than 25 videos.
- Management and reports pagination works with more than 50 records.
- Query inspection confirms no per-card reaction/save N+1 pattern.
- 320, 360, 390, 480, 768, 900, 1024, 1280, 1366, 1440, 1600, and 1920px pass without horizontal overflow.
- Keyboard operation, focus visibility, report form labels, live status, and disabled states pass.
- Chrome, Firefox, Edge, Safari/iOS, and Android browser smoke tests pass.

## Rollback and final acceptance

- Corrected package checksum matches repository.
- Backup restoration succeeds.
- Plugin rollback to preserved baseline is documented and tested without deleting data.
- Founder visually and functionally accepts the Video Wall.
- Only then may the corrective PR be marked ready and merged.
