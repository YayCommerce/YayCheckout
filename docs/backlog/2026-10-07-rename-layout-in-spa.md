# A layout can't be renamed once it is created

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** left open by the full-width checkout layout plan (`docs/plans/2026-10-07-full-width-checkout-layout.md`), 2026-10-07, branch `trace/feat/full-width-layout`.

## Today

- A layout's name is its `post_title`, set when `LayoutRepository::insert_draft()` creates it from the SPA, and read by the SPA list and its search (`LayoutRepository::match_search_to_title()`).
- `Engine\Admin\LayoutEditorCanvas` hides the block editor's title field on layout posts, and that field was the only place to change `post_title`.
- The SPA layouts list has no Rename action.

## Why it matters

A merchant who mistypes a layout's name, or wants to tell layouts apart after duplicating one, can't fix the name. The only workaround is to duplicate or recreate the layout under a new name.

## Suggested fix

Add a Rename action to the SPA layouts list that sends the new title to a layout REST route, which updates `post_title` through `LayoutRepository`. Validate a non-empty title there. Don't bring back the editor's title field: the canvas is meant to look like the storefront page, which has no title.

## Done when

- [ ] The SPA layouts list has a Rename action, and the route behind it updates `post_title` through `LayoutRepository` and refuses an empty title with a 4xx.
- [ ] A renamed layout shows its new name in the list and is found by the new name in search.
