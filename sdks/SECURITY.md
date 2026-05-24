# Security policy

## Reporting a vulnerability

Email **security@example.com**. Encrypt with PGP if you have keys
worth using.

We acknowledge reports within 48 hours and aim for a fix
acknowledgement (or "won't-fix" with reasoning) within 7 calendar
days.

## Scope

In scope:
- All SDK packages in this folder (`kotlin/`, `swift/`,
  `typescript/`, `storefront-typescript/`).
- The edge workers in `../workers/`.
- Wire-format bugs in the platform's public API that could be
  exploited via these SDKs.

Out of scope:
- The organizer back-office / dashboards (separate disclosure
  channel — same email).
- Third-party hardware drivers (please file with the vendor).
- Social engineering of platform staff.

## Safe harbour

Good-faith security research is welcomed. We will not pursue legal
action against researchers who:

- Test only against accounts they own.
- Avoid accessing or modifying other people's data.
- Give us reasonable time to fix the issue before disclosing
  publicly (90 days unless otherwise agreed).
- Don't degrade service availability.

## Acknowledgements

Researchers who report valid issues are credited in this file
unless they prefer to remain anonymous.

- _(none yet)_
