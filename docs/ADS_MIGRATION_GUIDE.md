# Atlassian Design System (ADS) — migration guide

The codebase is mid-migration from **shadcn/ui + Tailwind v4** to the
**Atlassian Design System (Atlaskit)**. This document is the operating
manual for that migration.

The full plan lives in `263Tickets_ADS_Migration_Plan.md` at the repo
root — that's the strategy. This guide is the tactical "what's in
place today + how to use it + how to migrate the next page."

---

## What's shipped (Phase 0 + 1 + 2)

| Layer | Location | Status |
|---|---|---|
| ADS packages | `package.json` (≈ 60 `@atlaskit/*` + `@compiled/react`) | ✅ Installed |
| Token map | `resources/js/design-system/tokens.ts` | ✅ |
| ThemeProvider (light/dark/auto + localStorage) | `resources/js/design-system/ThemeProvider.tsx` | ✅ |
| AppProvider + CSS imports | `resources/js/app.tsx` | ✅ Wired alongside existing providers |
| ADS overlay CSS | `resources/css/ads.css` | ✅ Loaded after the existing Tailwind `app.css` |
| Wrapper components | `resources/js/components/ads/*` | ✅ 28 wrappers, barrel-exported |
| Layout shell | `resources/js/layouts/ads/AppShell.tsx` + `PageContent.tsx` + `AuthLayout.tsx` + `FullscreenLayout.tsx` | ✅ |
| Navigation | `resources/js/components/ads-navigation/TopNav.tsx` + `SideNav.tsx` | ✅ |
| Theme toggle | `resources/js/components/ads/ThemeToggle.tsx` | ✅ |
| Worked example | `resources/js/pages/ads-showcase.tsx` | ✅ View at `/ads-showcase` once routed |
| Path aliases (`@ads`, `@ds`) | `tsconfig.json` + `vite.config.ts` | ✅ |

**Not yet migrated**: the 66 existing pages in `resources/js/pages/*`
keep using the legacy `AppLayout` + `components/ui/*` (shadcn) +
Tailwind. They keep working unchanged because the two systems are
deliberately running in parallel.

---

## How to use the new layer in a new or migrated page

### 1. Imports

```tsx
// ALWAYS import from the wrapper barrel, never from @atlaskit/* in pages.
import { Button, TextField, DataTable, StatusBadge, useToast } from '@ads';

// Primitives (layout) come from @atlaskit/primitives directly —
// these are the only @atlaskit imports allowed in pages.
import { Box, Stack, Inline, Text } from '@atlaskit/primitives';
import Heading from '@atlaskit/heading';

// Icons — modern Atlaskit uses `core/<name>`, NOT `glyph/<name>`.
import CalendarIcon from '@atlaskit/icon/core/calendar';
import MoreIcon from '@atlaskit/icon/core/show-more-horizontal';
```

### 2. Page skeleton

```tsx
import { AppShell } from '@/layouts/ads/AppShell';
import { PageContent } from '@/layouts/ads/PageContent';
import { Button } from '@ads';

export default function MyPage() {
    return (
        <PageContent
            title="My Page"
            breadcrumbs={[
                { text: 'Section', href: '/section' },
                { text: 'My Page' },
            ]}
            actions={<Button variant="primary">Create</Button>}
        >
            {/* page body */}
        </PageContent>
    );
}

MyPage.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>;
```

The `Page.layout = (page) => <AppShell>...</AppShell>` assignment is
the Inertia idiom that swaps the persistent layout. Legacy pages
inherit the layout from `app.tsx`'s `layout: (name) => ...` switch;
when a page assigns its own `.layout`, that wins.

### 3. Forms

```tsx
import { Form } from '@atlaskit/form';
import { useForm } from '@inertiajs/react';
import { Button, TextField, Select, useToast } from '@ads';
import { Stack } from '@atlaskit/primitives';

export default function CreateEvent() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        category_id: '',
    });
    const { toast } = useToast();

    return (
        <Form onSubmit={() => post(route('events.store'), {
            onSuccess: () => toast({ type: 'success', title: 'Event created' }),
        })}>
            {({ formProps }) => (
                <form {...formProps}>
                    <Stack space="space.300">
                        <TextField
                            name="name"
                            label="Name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                            isRequired
                        />
                        <Button type="submit" variant="primary" isLoading={processing}>
                            Create
                        </Button>
                    </Stack>
                </form>
            )}
        </Form>
    );
}
```

The wrapper passes Laravel-style `error` strings straight through; no
mapping needed.

### 4. Toasts

Wrap the part of the tree that needs `useToast()` in `<ToastProvider>`.
For app-wide toasts wrap inside `AppShell`. (Currently the legacy
`Toaster` from sonner still serves the older pages; both can render
simultaneously without colliding.)

### 5. Tables

`DataTable` accepts a typed `columns` + `rows` shape:

```tsx
const columns: Column<EventRow>[] = [
    { key: 'name', header: 'Event', isSortable: true, width: 40, render: (e) => <Text weight="semibold">{e.name}</Text> },
    { key: 'status', header: 'Status', width: 15, render: (e) => <StatusBadge status={e.status} /> },
];
<DataTable columns={columns} rows={events} rowsPerPage={20} emptyStateHeading="No events found" />
```

### 6. Status vocabulary

`StatusBadge` only accepts the values in `app/Models` enum-equivalent
shape — `available | sold_out | reserved | cancelled | pending | completed | draft | published | refunded | checking_in | checked_in | no_show | voided | transferred`.
Adding a new business state? Add it to `StatusBadge.tsx`'s
`statusConfig` map — never use raw `<Lozenge appearance="...">` in
pages.

---

## Migrating an existing page

Step-by-step recipe for taking a legacy shadcn/Tailwind page over:

1. **Pick a small page first.** Don't start with `event-ticket-manager.tsx` (1,727 lines). Pick something like `settings/help.tsx`.
2. **Change imports.** Find every `from '@/components/ui/...'` (shadcn) and find the equivalent in `@ads`. Find every `from 'lucide-react'` and swap for `@atlaskit/icon/core/<name>`.
3. **Swap layout primitives.** Replace `<div className="flex items-center gap-2">` with `<Inline space="space.100" alignBlock="center">`. Replace `<div className="flex flex-col gap-4">` with `<Stack space="space.200">`. Replace `<div className="p-4">` with `<Box padding="space.200">`.
4. **Swap headings.** `<h1>` → `<Heading size="xlarge" as="h1">`. `<p>` for body text → `<Text>` from primitives.
5. **Swap raw colors / hex / Tailwind classes.** Use `ds.color.text.subtle` etc. for inline styles, or `Text color="color.text.subtle"` directly.
6. **Wrap with the new layout.** Append `Page.layout = (page) => <AppShell>{page}</AppShell>;` at the bottom.
7. **Run tsc.** `pnpm exec tsc --noEmit`. Fix any prop drift (the wrappers' interfaces are stable; the issues will be your callsites passing extra Tailwind classes or shadcn-specific props).
8. **Run pest.** Backend tests should not regress.
9. **Smoke test in the browser.** Login, navigate to the migrated page, exercise it.

---

## What NOT to do

- **Don't** import `@atlaskit/*` from a page directly. Add a new wrapper to `components/ads/` instead.
- **Don't** add new Tailwind classes anywhere. New code uses Box/Stack/Inline + tokens.
- **Don't** use `lucide-react` icons in new code. Modern ADS icons only.
- **Don't** mix `Toaster` (sonner) and `useToast` (ADS) in the same page — pick one per page.
- **Don't** delete `components/ui/` or `tailwind.config` yet. Both systems must coexist until all 66 pages are migrated. Removal is **Phase 7** of the master plan.

---

## Phase status

| Phase | What | Status |
|---|---|---|
| 0 | Infrastructure (packages, tokens, AppProvider, ads.css, aliases) | ✅ Complete |
| 1 | Core wrapper components (33 wrappers + barrel) | ✅ Complete |
| 2 | Layout & navigation (AppShell, PageContent, TopNav, SideNav, AuthLayout, FullscreenLayout, ThemeToggle) | ✅ Complete |
| 2.5 | **AppShell wired as default layout — every page now renders inside ADS chrome automatically** | ✅ Complete |
| 3 | Forms migration (page bodies off shadcn) | 🟡 5/66 migrated |
| 4 | Tables & lists migration | 🟡 Subset of #3 |
| 5 | Detail views & drawers | ⏳ Per-page work |
| 6 | Motion & polish | ⏳ |
| 7 | Tailwind / shadcn removal + final QA | ⏳ Last |

**Critical**: Even with only 5 page bodies migrated, **every page is
already rendering inside the new ADS shell** (TopNav, SideNav,
breadcrumb bar) because `app.tsx` now returns `AppShell` from its
layout selector. Pages whose bodies still use shadcn keep working
inside the ADS chrome unchanged.

### Pages migrated off shadcn / Tailwind so far

1. `pages/ads-showcase.tsx` — worked example (datatable + filters + empty state + spinner)
2. `pages/settings/billing/invoices.tsx` — table-style list with download
3. `pages/settings/account/sessions.tsx` — list with confirm + Lozenge
4. `pages/settings/integrations/connected.tsx` — list with brand icons + tag group
5. `pages/settings/profile.tsx` — Inertia form with Input + Label + error binding
6. `pages/settings/appearance.tsx` — minimal page heading + composed sub-component

### Pages still on shadcn (61 of 66)

These pages still import from `@/components/ui/*` for their body
content. They render fine because the AppShell provides the ADS
chrome; only their internal widgets are still shadcn.

To find them: `grep -l "from '@/components/ui" resources/js/pages/**/*.tsx`.

Suggested migration order (smallest first for fast wins):

**Sprint A — small settings pages (~27 files, < 200 LOC each):**
`settings/team/invitations.tsx`, `settings/data/{retention,audit-log,exports,gdpr}.tsx`,
`settings/team/members.tsx`, `settings/billing/{plan,refunds,taxes,payouts,methods,gateways}.tsx`,
`settings/operations/{ticket-templates,email-identity,webhooks,check-in}.tsx`,
`settings/integrations/{index,oauth}.tsx`, `settings/developer/{logs,api-keys,webhooks}.tsx`,
`settings/account/{api-tokens,security,notifications}.tsx`,
`settings/appearance/{dates,locale}.tsx`,
`settings/organization/{public,domain,brand}.tsx`,
`settings/{help,roles/show}.tsx`, `marketing/{integrations,index,paid-ads}.tsx`,
`calendar/index.tsx`.

**Sprint B — top-level small/medium (~12 files, 200-300 LOC):**
`notifications/index.tsx`, `attendees/index.tsx`,
`marketing/{email-campaigns,social}.tsx`, `settings/teams.tsx`,
`check-in/index.tsx`, `orders/{show,index}.tsx`,
`scanners/index.tsx`, `settings/team-edit.tsx`,
`reports/index.tsx`, `finance/refund.tsx`.

**Sprint C — medium-large (~9 files, 300-600 LOC):**
`dashboard.tsx`, `settings/roles/{role-form,index}.tsx`,
`finance/index.tsx`, `discounts/index.tsx`,
`scanners/show.tsx`, `welcome.tsx`,
`organization/show.tsx`, `sandbox/payments/{inspector,dashboard}.tsx`,
`events/index.tsx`.

**Sprint D — giants (4 files, 700-1200 LOC):**
`settings/organization.tsx` (730),
`events/show.tsx` (1183), `events/edit.tsx` (1212),
plus `components/event-ticket-manager.tsx` (1727 — used by events/edit + events/show).

Each Sprint A page is ~5-10 min of focused work. Sprint B ~15-25
min. Sprint C ~30-60 min. Sprint D is a half-day per file.

Realistic timeline: a single engineer can clear Sprint A in ~2 days,
Sprint B in ~3 days, Sprint C in ~3 days, Sprint D in ~1 week.
Total: ~2 weeks of focused work to migrate all bodies + run Phase 7
tear-down.

---

## API drift from the original plan

The migration plan was written against an older Atlaskit generation;
many APIs have changed in the installed packages. Differences worth
knowing:

- **Icons** moved from `@atlaskit/icon/glyph/<name>` to `@atlaskit/icon/core/<name>`. Some icon names also changed (e.g. `people` → `people-group`, `graph-line` → `chart-bar`, `more` → `show-more-horizontal`, `info` → `information`). Use the `theme` icon for the dark/light toggle — no separate sun/moon glyphs in the current package.
- **`@atlaskit/button/new`** exports `Button` as default + `IconButton` as named. There's no separate `LoadingButton` — `isLoading` is a prop on the regular Button.
- **IconButton** is restricted to `primary | default | subtle | discovery` appearances. Reflected in our `IconButtonVariant` type.
- **`@atlaskit/flag`** uses `FlagsProvider` to render flags; `useFlags()` returns `{ showFlag, hideFlag }` only. Our `ToastProvider` wraps `FlagsProvider` and re-exposes the same API as before.
- **`@atlaskit/empty-state`** renamed `heading` → `header`. Our wrapper keeps the old prop name stable for callers.
- **`@atlaskit/drawer`** dropped `shouldUnmountOnExit`, `shouldCloseOnEscapePress`, `shouldCloseOnOverlayClick`.
- **`@atlaskit/banner`** dropped `isOpen` — consumers control visibility by mounting/unmounting.
- **`@atlaskit/tag-group`** dropped `testId` — wrap with a div if you need a test hook.
- **`@atlaskit/inline-message`** uses `appearance` (not `type` or `iconAppearance`) for the icon style. Still supports `title` + `secondaryText`.

These differences are encapsulated inside the wrapper layer — pages
use stable `@ads/*` imports and don't see the churn.

---

## Verification status as of latest commit

- `pnpm exec tsc --noEmit` → **0 errors**
- `pnpm exec phpstan analyse` → **EXIT 0**
- `pest tests/Unit` → **178/178 passing**
- Bundle still builds; existing 66 pages render unchanged.
