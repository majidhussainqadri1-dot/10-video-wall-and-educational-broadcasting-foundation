=== Video Wall and Educational Broadcasting Foundation ===
Contributors: sabrihomeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

The corrected foundation release of the Sabri Social Homeopathy Platform educational Video Wall.

== Features ==
Public educational video discovery; sixteen fixed categories; Founder and administrator direct publishing; verified-doctor moderation; strict custom-post authorization; safe YouTube and Vimeo normalization; validated local video uploads; transactional publication creation; pagination; views; Like and Dislike; saved videos; viewing history; local-video resume progress; reports; comments; transcripts; references; patient-case safeguards; moderation audit trails; privacy export and erasure; private-page no-cache/noindex controls; responsive design; accessibility improvements; and source-correct VideoObject structured data.

== Security and governance corrections in 0.2.0 ==
* Prevents WordPress admin publishing bypass by restricting post-type capabilities to manage_video_wall.
* Never overwrites unrelated existing page content during activation.
* Records activation snapshots, created pages, moderation decisions, report changes, actors, dates, states, and notes.
* Makes reject and hide notes mandatory.
* Validates official YouTube/Vimeo hosts and strict duration values.
* Uploads media before publication and rolls back attachments and posts on any failure.
* Uses factual author labels rather than assuming every non-Founder author is verified.
* Verifies AJAX HTTP/JSON success before showing confirmation.
* Adds private no-store headers and complete privacy callbacks.
* Cleans orphaned interaction data and recounts totals after erasure.
* Replaces 60-item unpaginated output and per-card N+1 state queries with bounded pagination and batched state loading.

== Installation ==
Keep Files 00, 02, 03, 04, 07, and 09 active where applicable. Back up the approved Hostinger staging site, upload the 0.2.0 ZIP, activate it, run Video Wall Management checks, and test each source, role, moderation, privacy, responsive, and rollback workflow before any live deployment.

== Viewing history boundary ==
Authenticated views are listed in Video History. Resume-position tracking is implemented for locally hosted HTML5 videos. YouTube and Vimeo entries are recorded in history, but precise external-player resume positions are not claimed because no external player API is loaded in this foundation release.

== Limitations ==
Live streaming, payments, advertising, automatic captions, AI summaries, playlists, fingerprinting, and Reels are not included. This package still requires real WordPress staging acceptance, companion-plugin integration tests, backup restoration, rollback acceptance, and post-deployment checks before production use.

== Changelog ==
= 0.2.0 =
* Corrected authorization, activation safety, moderation audit, privacy, AJAX truthfulness, history, data integrity, pagination, performance, URL validation, duration validation, structured data, and accessibility defects identified in the 0.1.0 audit.

= 0.1.0 =
* Original project-supplied foundation package preserved in repository provenance.
