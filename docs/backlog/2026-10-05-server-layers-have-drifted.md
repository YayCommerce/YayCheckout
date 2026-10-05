# Four spots in the server code have drifted from its layers and its wiring

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- **Not a controller.** `includes/Controllers/LayoutTemplateCatalogue.php` registers no route. It holds a static fixture list and the plan rule `assert_available()`, the same kind of class as `Support\CompatibilityCatalogue` (`includes/Support/CompatibilityCatalogue.php`). `docs/architecture.md` already says it isn't a controller.
- **Routes in constructors.** Each REST controller registers its routes in its constructor. `Engine\RestAPI::checkout_endpoints()` and `Pro\ProServiceProvider::rest_endpoints()` build them through `SingletonTrait::get_instance()`, so a second `rest_api_init` in one process gets the existing instance and registers nothing. Nothing fires it twice today; a REST test that builds a fresh `WP_REST_Server` per test would (inferred). `.claude/rules/php-standards.md` (REST) asks for this pattern.
- **Public plan switch.** `Support\Plan::mark_pro()` is `public static`. Its docblock says only `ProServiceProvider::boot()` may call it, but nothing enforces that. One call from any plugin turns on Pro behaviour in Free: no layout limit, every template unlocked, several active layouts. Free still has no Pro routes. It came in `48ea118`.
- **A Service that reads state.** `Pro\Services\AssignmentStoreData::get()` calls `wp_roles()`, though `.claude/rules/php-standards.md` (Layers) says Services take already-fetched data.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

A reader looking for routes finds a fixture in `includes/Controllers/`. The first REST test will find its routes missing from the second test on, with no clue why. Free's limits are one line away for anyone who wants them gone. A Service that reads WordPress state can't be tested as the pure computation its layer promises.

## Suggested fix

Move `LayoutTemplateCatalogue` to `includes/Support/`. Register routes in a method that the two builders call on every `rest_api_init`, keeping the singletons, and update the REST rule in `.claude/rules/php-standards.md`. Let only the boot set the plan: for example, `YayCheckout::boot_pro()` sets a private flag once the provider has booted, `Plan::is_pro()` reads it, and `mark_pro()` goes. Have `ProServiceProvider::admin_data()` read the roles and pass them to `AssignmentStoreData`, or move that class out of `includes/Pro/Services/`.

## Done when

- [ ] `LayoutTemplateCatalogue` is in `includes/Support/`, not `includes/Controllers/`.
- [ ] No class in `includes/Controllers/` or `includes/Pro/Controllers/` calls `register_rest_route()` from its constructor.
- [ ] `.claude/rules/php-standards.md` (REST) no longer tells a controller to register its routes in its constructor.
- [ ] `includes/Support/Plan.php` has no public method that sets the plan.
- [ ] No class under `includes/Pro/Services/` calls `wp_roles()`.
