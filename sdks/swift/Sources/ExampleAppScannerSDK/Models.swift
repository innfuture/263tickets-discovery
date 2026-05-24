import Foundation

/// Wire shapes mirroring the platform's scanner API. Keep field
/// spellings exactly aligned with the PHP controllers — Codable will
/// map snake_case to camelCase via the decoder's keyDecodingStrategy.

public enum ScanVerdict: String, Codable, Sendable {
    case allow
    case warn
    case deny
}

public struct FraudFlag: Codable, Sendable {
    public let rule: String
    public let outcome: ScanVerdict
    public let severity: Int
    public let reasonCode: String?
    public let message: String?
}

public struct BiometricAttestation: Codable, Sendable {
    public let algorithm: String
    public let confidence: Double
    public let nonce: String
    public let signature: String

    public init(algorithm: String, confidence: Double, nonce: String, signature: String) {
        self.algorithm = algorithm
        self.confidence = confidence
        self.nonce = nonce
        self.signature = signature
    }
}

public struct PairRequest: Codable, Sendable {
    public let code: String
    public let deviceLabel: String
    public let platform: String?
    public let appVersion: String?
    public let hardwareId: String?

    public init(code: String, deviceLabel: String, platform: String? = "ios", appVersion: String? = nil, hardwareId: String? = nil) {
        self.code = code
        self.deviceLabel = deviceLabel
        self.platform = platform
        self.appVersion = appVersion
        self.hardwareId = hardwareId
    }
}

public struct Profile: Codable, Sendable {
    public let uuid: String
    public let name: String
    public let capabilities: [String]
    public let allowedEventIds: [Int]
    public let maxScansPerMinute: Int
    public let duplicateWindowSeconds: Int
}

public struct Device: Codable, Sendable {
    public let uuid: String
    public let label: String
    public let platform: String
}

public struct PairResponse: Codable, Sendable {
    public let token: String
    public let device: Device
    public let profile: Profile
}

public struct ScanRequest: Codable, Sendable {
    public let payload: String
    public let clientLat: Double?
    public let clientLng: Double?
    public let clientAt: String?
    public let deviceMeta: [String: AnyCodable]?
    public let biometric: BiometricAttestation?

    public init(
        payload: String,
        clientLat: Double? = nil,
        clientLng: Double? = nil,
        clientAt: String? = nil,
        deviceMeta: [String: AnyCodable]? = nil,
        biometric: BiometricAttestation? = nil
    ) {
        self.payload = payload
        self.clientLat = clientLat
        self.clientLng = clientLng
        self.clientAt = clientAt
        self.deviceMeta = deviceMeta
        self.biometric = biometric
    }
}

public struct TicketSummary: Codable, Sendable {
    public let uuid: String
    public let ticketNumber: String
    public let scanCount: Int
    public let isVoided: Bool
}

public struct EventRef: Codable, Sendable {
    public let id: Int
    public let slug: String
    public let name: String
}

public struct ScanResult: Codable, Sendable {
    public let scanUuid: String
    public let verdict: ScanVerdict
    public let reasonCode: String?
    public let wasAdmitted: Bool
    public let wasDuplicate: Bool
    public let wasVoided: Bool
    public let flags: [FraudFlag]
    public let latencyMs: Int
    public let ticket: TicketSummary?
    public let event: EventRef?
    public let serverTime: String?
}

public struct HeartbeatRequest: Codable, Sendable {
    public let lat: Double?
    public let lng: Double?
    public let battery: Double?
    public let connection: String?

    public init(lat: Double? = nil, lng: Double? = nil, battery: Double? = nil, connection: String? = nil) {
        self.lat = lat
        self.lng = lng
        self.battery = battery
        self.connection = connection
    }
}

public struct HeartbeatResponse: Codable, Sendable {
    public let ok: Bool
    public let revoked: Bool
    public let serverTime: String
}

/// AnyCodable shim so `device_meta` can carry arbitrary scanner-side
/// telemetry (battery, signal, build, …) without a typed schema.
public struct AnyCodable: Codable, Sendable {
    public let value: Any & Sendable

    public init(_ value: Any & Sendable) { self.value = value }

    public init(from decoder: Decoder) throws {
        let c = try decoder.singleValueContainer()
        if let v = try? c.decode(Bool.self) { self.value = v }
        else if let v = try? c.decode(Int.self) { self.value = v }
        else if let v = try? c.decode(Double.self) { self.value = v }
        else if let v = try? c.decode(String.self) { self.value = v }
        else { self.value = "" }
    }

    public func encode(to encoder: Encoder) throws {
        var c = encoder.singleValueContainer()
        switch value {
        case let v as Bool: try c.encode(v)
        case let v as Int: try c.encode(v)
        case let v as Double: try c.encode(v)
        case let v as String: try c.encode(v)
        default: try c.encodeNil()
        }
    }
}
