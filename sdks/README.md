# example-app SDKs

Open-source client libraries + edge integrations for the example-app
platform. MIT-licensed; build flags, hardware integrations, and
custom transports are welcome.

## What's here

| Folder | What | Status |
| --- | --- | --- |
| `kotlin/` | Scanner SDK for Android + JVM scanner devices. Pairing, scan, batch, webhook verify. | Stable. Used by reference scanner app. |
| `swift/` | Scanner SDK for iOS / iPadOS gate apps. Same surface as Kotlin. | Stable. |
| `typescript/` | Scanner SDK for Node / browser-based readers + kiosks. | Stable. Tests passing. |
| `storefront-typescript/` | Public-storefront client (event discovery, checkout, gift cards, refunds). | Stable. |

See [../workers/](../workers/) for the Cloudflare edge components:

- `scanner-edge/` — verdict-at-the-edge for the scanner API
- `storefront-edge/` — personalised storefront responses

## Contributing

We accept PRs that:

- **Add reader-hardware integrations** to the scanner SDKs (Bluestar
  Air, Honeywell ring scanners, ID Tech, etc.). One sub-module per
  vendor, gated behind an opt-in dependency.
- **Add language bindings** (Python / Go / .NET / Rust). The wire
  contract is documented in `STOREFRONT.md` + `STOREFRONT_ROADMAP.md`
  at the repo root.
- **Improve docs** — every method should have one usage example.
- **Fix bugs** — please include a regression test in the package's
  native test framework (vitest, pest, JUnit, XCTest).

We don't merge PRs that:

- Hard-fork the wire format (file a discussion first).
- Add packages outside the MIT-compatible deps already in each
  manifest.
- Phone home to non-example-app endpoints.

## Versioning

Each SDK has its own semver. The Stable surface is documented in
each package's README; anything not listed there is internal and
may change without a major bump.

## Security disclosure

Email `security@example.com` — do not file public issues. See
SECURITY.md for the disclosure timeline + safe-harbor terms.
