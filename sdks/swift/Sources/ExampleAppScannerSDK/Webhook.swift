import Foundation
import CryptoKit

/// Verifies an inbound scan webhook signature.
///
/// Header shape from the platform:
///   `X-Scanner-Signature: t=<unix>,v1=<hex>` where
///   `hex = hmac_sha256("<t>.<rawBody>", secret)`.
///
/// Returns true when the signature is valid and within `tolerance`
/// of the current time. Throws on missing / malformed headers so
/// misconfigured backends fail loudly.
public enum ScannerWebhook {

    public static func verifySignature(
        rawBody: String,
        header: String?,
        secret: String,
        tolerance: TimeInterval = 300
    ) throws -> Bool {
        guard let header else { throw NSError(domain: "ExampleAppScannerSDK", code: 1, userInfo: [NSLocalizedDescriptionKey: "Missing X-Scanner-Signature"]) }

        var parts: [String: String] = [:]
        for kv in header.split(separator: ",") {
            let halves = kv.split(separator: "=", maxSplits: 1).map(String.init)
            guard halves.count == 2 else { continue }
            parts[halves[0].trimmingCharacters(in: .whitespaces)] = halves[1].trimmingCharacters(in: .whitespaces)
        }
        guard let t = parts["t"], let v1 = parts["v1"], let ts = TimeInterval(t) else {
            throw NSError(domain: "ExampleAppScannerSDK", code: 2, userInfo: [NSLocalizedDescriptionKey: "Malformed signature header"])
        }
        if abs(Date().timeIntervalSince1970 - ts) > tolerance {
            return false
        }

        let payload = "\(Int(ts)).\(rawBody)"
        let key = SymmetricKey(data: Data(secret.utf8))
        let mac = HMAC<SHA256>.authenticationCode(for: Data(payload.utf8), using: key)
        let hex = mac.map { String(format: "%02x", $0) }.joined()
        return constantTimeEquals(hex, v1)
    }

    private static func constantTimeEquals(_ a: String, _ b: String) -> Bool {
        guard a.count == b.count else { return false }
        var diff: UInt8 = 0
        for (x, y) in zip(a.utf8, b.utf8) { diff |= x ^ y }
        return diff == 0
    }
}
