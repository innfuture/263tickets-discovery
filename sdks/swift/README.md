# `ExampleAppScannerSDK` — Swift

Official Swift / iOS client for the example-app scanner API.

## Install

`Package.swift`:
```swift
.package(url: "https://github.com/example-app/scanner-sdk-swift.git", from: "1.0.0")
```

Targets:
```swift
.target(dependencies: ["ExampleAppScannerSDK"])
```

iOS 15+ / macOS 12+. No third-party dependencies.

## Pair + scan

```swift
import ExampleAppScannerSDK

let client = ScannerClient(baseURL: URL(string: "https://app.example.com")!)

// Operator types the code shown in the web admin
let pair = try await client.pair(.init(code: "ABC23XYZ", deviceLabel: "John iPhone"))

// Persist in Keychain — never UserDefaults
try Keychain.set(pair.token, for: "scannerToken")

// Scan
let result = try await client.scan(.init(
    payload: scannedQrPayload,
    clientLat: location.coordinate.latitude,
    clientLng: location.coordinate.longitude,
    deviceMeta: ["battery": AnyCodable(0.41), "signal": AnyCodable("4g")]
))

if result.wasAdmitted { admit(result.ticket) }
```

## Error handling

```swift
do {
    let r = try await client.scan(.init(payload: payload))
    // r.verdict is .allow or .warn
} catch ScannerSDKError.scanDenied(let reasonCode, let flags) {
    show("Denied: \(reasonCode ?? "unknown")", flags: flags)
} catch ScannerSDKError.notPaired {
    // user signed out / token revoked → redirect to pairing
} catch ScannerSDKError.apiError(let status, let code, _) {
    // any other 4xx/5xx
}
```

## Webhook verification (server-side Swift backends)

```swift
let isValid = try ScannerWebhook.verifySignature(
    rawBody: rawBody,
    header: request.headers["x-scanner-signature"],
    secret: ProcessInfo.processInfo.environment["SCANNER_SECRET"]!
)
```

## Status

This is a reference implementation. Production deployments should:
- Wire `URLSession` configuration (App Transport Security, certificate pinning) per their security policy.
- Use the Keychain (not `UserDefaults`) for token storage.
- Wrap `ScannerClient` calls in their own retry / queue layer for offline-first scanning.
