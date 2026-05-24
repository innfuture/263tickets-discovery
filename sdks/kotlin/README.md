# `com.example_app:scanner-sdk`

Official Kotlin SDK for the example-app scanner API. Targets the JVM
(Android 24+ / desktop), uses coroutines for async, and ships with
`kotlinx.serialization` wire types.

## Install

```kotlin
dependencies {
    implementation("com.example_app:scanner-sdk:0.1.0")
}
```

## Pair a device

```kotlin
import com.example_app.scanner.ScannerClient
import com.example_app.scanner.PairRequest

val sdk = ScannerClient(baseUrl = "https://app.example.com")

// Organizer issues the code from the web UI; operator types it in.
val paired = sdk.pair(PairRequest(
    code = "ABC23XYZ",
    deviceLabel = "John's Pixel",
    platform = "android",
    appVersion = "1.0.0",
))

// Store in the Android Keystore — never log it.
keystore.put("scanner-token", paired.token)
```

## Scan a ticket

```kotlin
import com.example_app.scanner.ScanRequest
import com.example_app.scanner.ScanDeniedException

val sdk = ScannerClient(
    baseUrl = "https://app.example.com",
    token = keystore.get("scanner-token"),
)

try {
    val result = sdk.scan(ScanRequest(
        payload = scannedQrPayload,
        clientLat = location.latitude,
        clientLng = location.longitude,
    ))

    when (result.verdict) {
        "allow" -> admitGuest(result.ticket)
        "warn"  -> showWarning(result.flags)
        else    -> {}
    }
} catch (e: ScanDeniedException) {
    showDenialReason(e.reasonCode, e.flags)
}
```

## Offline-first batch

```kotlin
val queued = db.queue.all()
val response = sdk.batchScan(queued)
response.results.forEachIndexed { i, r ->
    val isTransient = r.verdict == "deny" && r.reasonCode == "processing_error"
    if (!isTransient) db.queue.delete(queued[i].id)
}
```

## Webhook verification

If your backend (Ktor, Spring, etc.) receives scan webhooks, verify the
signature before trusting the payload:

```kotlin
import com.example_app.scanner.ScannerWebhook

val rawBody = call.receiveText()
val sig = call.request.headers["X-Scanner-Signature"]

if (!ScannerWebhook.verifySignature(rawBody, sig, System.getenv("SCANNER_SECRET"))) {
    call.respond(HttpStatusCode.Unauthorized)
    return@post
}

val event = Json.decodeFromString<ScanEvent>(rawBody)
// process event.scan …
```

## Exception types

- `ScanDeniedException` — verdict came back `deny`. Carries `reasonCode` + `flags`.
- `ScannerApiException` — HTTP non-2xx response. Carries the server's `error` code and `message`.
- `NotPairedException` — no token was supplied to an authenticated call.

## Build + test

```bash
./gradlew build
./gradlew test
```
