# Record architecture decisions, and supersede a record only whole

**Purpose:** how this repo records an architectural decision: when to write a record, what stays out of one, its file name, its format, its status values and how one record replaces another.

**Update when:** never edit it. To change the practice, write a record that supersedes it.

## Status

Accepted, 2026-10-05, by Trace Bui, who asked for YayLoyalty's harness in this repo. It restates [YayLoyalty's practice](https://github.com/YayCommerce/YayLoyalty/blob/dev/edd/docs/decisions/2026-10-04-decision-record-practice.md) for YayCheckout.

## Context

- The code shows what YayCheckout does, not why it was built that way or what was turned down. [architecture.md](../architecture.md) describes how the parts fit, and [conventions.md](../conventions.md) the rules the code follows. Neither keeps the reasoning behind a choice, or the options it beat.
- The previous harness kept that reasoning in one long architecture doc, mixed with descriptions of the code, and in dated journals. Reasons drifted with the descriptions around them, and nothing said which statements were decisions still in force.
- YayLoyalty first let a record supersede single decisions inside an earlier one. Within three days, finding out which skills existed meant walking four records and working out which of their lines still held. Its practice now allows only whole supersession, and keeps skill internals and project process out of records.
- Options turned down:
  - letting a record supersede single decisions: YayLoyalty's chain showed the cost;
  - a "current state" doc beside the records: it would restate the records, and drift from them.

## Decision

We record each architectural decision in its own file in `docs/decisions/`, in Michael Nygard's format.

- **When:** write a record when a change makes or reverses a choice that is costly to undo and that the code can't justify by itself. Examples: how Free and Pro are split, how a layout is stored, how dates cross the wire, a dependency that ships in the zip, the PHP floor, how the SPA or the editor reaches WordPress, and the shape of this harness. The record lands in the PR that makes the change.
- **What stays out:**
  - A choice internal to one skill, such as its modes, its rules, its formats or the length of its description, lives in that skill's files. A record is written when the harness's shape changes, not when a skill does.
  - Plan and process content, such as reviews, freezes, assignments, deadlines and tool versions, lives in a plan or in `README.md`. A Status names only who accepted the record.
  - A record cites no plan or question IDs. It states each decision in its own words.
- **File name:** `YYYY-MM-DD-<slug>.md`. The date is the day the record is written. The slug is lowercase words, joined by hyphens, that name the decision.
- **Format:** a title that states the decision, the **Purpose** and **Update when** lines that every harness doc opens with, then four headings:
  - `## Status`: the status, the date it was set and who accepted it;
  - `## Context`: the facts and forces that applied when the decision was made, including the options turned down;
  - `## Decision`: what we do, stated as rules;
  - `## Consequences`: what gets easier and what gets harder, costs included.
- **Statuses:**
  - `proposed`: open for discussion. A PR may merge it, but nothing acts on it.
  - `accepted`: in force. The code follows it.
  - `rejected`: turned down. It stays, so the question isn't reopened without new facts.
  - `superseded by <link>`: replaced by a later record. It stays as history, and nothing in it is in force.
- **An accepted record doesn't change.** Only two edits are allowed: its Status line, when a later record supersedes it, and a link that a moved file broke.
- **A record is superseded whole.** To change any decision in a record, write a new record that restates every decision of the old one still in force, along with the change, and in the same PR set the old one's Status to `superseded by` the new one. A record is never partly superseded.
- **Backfills:** a decision made before this practice gets a record dated the day it is written. Its Status names the commit that made the decision, and it stays `proposed` until a teammate confirms the reasons; then it becomes `accepted`.
- **What goes where:** a record holds the why. The mechanism stays in `architecture.md`, and the rules that follow from a decision stay in `conventions.md` or `CLAUDE.md`; they link to the record for the reasoning. Like those docs, a record cites paths and symbols, never line numbers.

## Consequences

- A reason outlives the session and the person that had it: a teammate, or Claude, finds why a choice was made and what it cost in one short file.
- Every accepted record is wholly in force, so a reader never has to check which of its lines a later record replaced.
- A change that contradicts an accepted record has to supersede it, so the change of mind shows up in review. Changing one decision costs restating the rest of its record.
- A record keeps its context as it was when it was written, so it ages. A reader checks today's mechanism in `architecture.md`.
- Each record costs a short file. Choices that are cheap to reverse, skill changes included, don't get one.
