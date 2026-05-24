import Foundation
#if canImport(FoundationNetworking)
import FoundationNetworking
#endif

/// Official Swift client for the example-app scanner API.
///
///   let client = ScannerClient(baseURL: URL(string: "https://app.example.com")!)
///   let pair = try await client.pair(.init(code: code, deviceLabel: "John iPhone"))
///   // Persist `pair.token` in Keychain.
///
///   let result = try await client.scan(.init(payload: scannedQr))
///   if result.wasAdmitted { admit(result.ticket) }
public actor ScannerClient {

    private let baseURL: URL
    private var token: String?
    private let session: URLSession
    private let timeout: TimeInterval
    private let throwOnDeny: Bool

    public init(
        baseURL: URL,
        token: String? = nil,
        session: URLSession = .shared,
        timeoutSeconds: TimeInterval = 10,
        throwOnDeny: Bool = true
    ) {
        self.baseURL = baseURL
        self.token = token
        self.session = session
        self.timeout = timeoutSeconds
        self.throwOnDeny = throwOnDeny
    }

    public var currentToken: String? { token }

    public func setToken(_ token: String?) { self.token = token }

    // MARK: - Endpoints

    public func pair(_ req: PairRequest) async throws -> PairResponse {
        let response: PairResponse = try await request("POST", "/api/v1/scanning/pair", body: req, requiresAuth: false)
        self.token = response.token
        return response
    }

    public func scan(_ req: ScanRequest) async throws -> ScanResult {
        do {
            let result: ScanResult = try await request("POST", "/api/v1/scanning/scan", body: req)
            try assertNotDenied(result)
            return result
        } catch let ScannerSDKError.apiError(status, _, raw) where status == 409 {
            // The deny path returns 409 with a ScanResult body. Unwrap.
            guard let raw = raw, let result = try? decoder.decode(ScanResult.self, from: raw) else {
                throw ScannerSDKError.apiError(status: status, code: "http_error", raw: raw)
            }
            try assertNotDenied(result)
            return result
        }
    }

    public func batchScan(_ scans: [ScanRequest]) async throws -> [ScanResult] {
        precondition(scans.count > 0, "Batch must be non-empty")
        precondition(scans.count <= 100, "Batch must be <= 100 scans")
        struct Body: Codable { let scans: [ScanRequest] }
        struct Wrap: Codable { let results: [ScanResult] }
        let w: Wrap = try await request("POST", "/api/v1/scanning/scan/batch", body: Body(scans: scans))
        return w.results
    }

    public func lookup(_ payload: String) async throws -> ScanResult {
        let path = "/api/v1/scanning/tickets/\(payload.addingPercentEncoding(withAllowedCharacters: .urlPathAllowed) ?? payload)"
        return try await request("GET", path)
    }

    public func heartbeat(_ req: HeartbeatRequest = .init()) async throws -> HeartbeatResponse {
        try await request("POST", "/api/v1/scanning/heartbeat", body: req)
    }

    // MARK: - Internals

    private let encoder: JSONEncoder = {
        let e = JSONEncoder()
        e.keyEncodingStrategy = .convertToSnakeCase
        return e
    }()

    private let decoder: JSONDecoder = {
        let d = JSONDecoder()
        d.keyDecodingStrategy = .convertFromSnakeCase
        return d
    }()

    private func assertNotDenied(_ result: ScanResult) throws {
        if throwOnDeny && result.verdict == .deny {
            throw ScannerSDKError.scanDenied(reasonCode: result.reasonCode, flags: result.flags)
        }
    }

    private func request<R: Decodable>(
        _ method: String,
        _ path: String,
        body: Encodable? = nil,
        requiresAuth: Bool = true
    ) async throws -> R {
        guard !requiresAuth || token != nil else {
            throw ScannerSDKError.notPaired
        }

        var urlRequest = URLRequest(url: baseURL.appendingPathComponent(path))
        urlRequest.httpMethod = method
        urlRequest.timeoutInterval = timeout
        urlRequest.setValue("application/json", forHTTPHeaderField: "Accept")
        urlRequest.setValue("ExampleAppScannerSDK-Swift/1.0", forHTTPHeaderField: "User-Agent")
        if requiresAuth, let token { urlRequest.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization") }
        if let body {
            urlRequest.setValue("application/json", forHTTPHeaderField: "Content-Type")
            urlRequest.httpBody = try encoder.encode(AnyEncodable(body))
        }

        let (data, response) = try await session.data(for: urlRequest)
        guard let http = response as? HTTPURLResponse else {
            throw ScannerSDKError.transport("Non-HTTP response")
        }

        guard (200..<300).contains(http.statusCode) else {
            let envelope = try? decoder.decode(ErrorEnvelope.self, from: data)
            throw ScannerSDKError.apiError(
                status: http.statusCode,
                code: envelope?.error ?? "http_error",
                raw: data
            )
        }

        return try decoder.decode(R.self, from: data)
    }
}

// MARK: - Error type

public enum ScannerSDKError: Error, Sendable {
    case notPaired
    case transport(String)
    case apiError(status: Int, code: String, raw: Data?)
    case scanDenied(reasonCode: String?, flags: [FraudFlag])
}

struct ErrorEnvelope: Decodable {
    let error: String?
    let message: String?
}

/// Type erasure so we can pass any `Encodable` as `body` and still
/// hand a concrete type to the encoder.
private struct AnyEncodable: Encodable {
    private let _encode: (Encoder) throws -> Void
    init(_ wrapped: Encodable) {
        self._encode = wrapped.encode
    }
    func encode(to encoder: Encoder) throws {
        try _encode(encoder)
    }
}
