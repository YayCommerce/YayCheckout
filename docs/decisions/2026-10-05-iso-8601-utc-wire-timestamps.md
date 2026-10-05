# Timestamps cross the wire as ISO 8601 UTC and are shown in the viewer's time zone

**Purpose:** why REST responses carry timestamps as ISO 8601 with an explicit UTC offset, and why the admin shows them in the viewer's browser time zone rather than the site's.

**Update when:** never edit it. Supersede it with a new record if the wire format or the display zone changes.

## Status

Proposed, 2026-10-05. Backfilled: the decision was made on 2026-09-17, in `db9f32b`. A teammate confirms the reasons before it is accepted.

## Context

- WordPress stores dates as zone-less MySQL strings (`post_date_gmt`). Sent as they were, the Layouts list rendered them verbatim, so every admin saw UTC, and the settings footer had its own formatter.
- A zone-less string is ambiguous on the client: JavaScript parses it as local time, so the same value means different instants in different browsers.
- Core's own admin lists show times in the site's time zone. The merchants and support staff who use YayCheckout's admin may sit in other zones than the store.

## Decision

- PHP converts at the REST boundary only, through `Helpers\DateHelper::to_iso8601()`, to ISO 8601 with an explicit UTC offset. Storage keeps core's MySQL format.
- The SPA formats every timestamp through `apps/admin/src/lib/helpers/date.helper.ts`, applying the site's date and time formats in the viewer's browser time zone. A page never formats a raw wire value itself.

## Consequences

- A timestamp is unambiguous on the wire, and one helper on each side owns its format.
- Each admin reads times against their own clock, which differs from core's screens when the admin and the site are in different zones.
- A new date field needs both helpers; a raw `post_date_gmt` in a response is a bug.
