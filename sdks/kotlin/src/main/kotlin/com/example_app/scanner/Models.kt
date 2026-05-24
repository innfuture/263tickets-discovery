package com.example_app.scanner

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

/**
 * Wire shapes — kept aligned with the Laravel scanner controllers.
 * snake_case property names use `@SerialName` so request/response
 * bodies match the platform contract.
 */

@Serializable
enum class ScanVerdict {
    @SerialName("allow") ALLOW,
    @SerialName("warn") WARN,
    @SerialName("deny") DENY,
}

@Serializable
data class FraudFlag(
    val rule: String,
    val outcome: ScanVerdict,
    val severity: Int,
    @SerialName("reason_code") val reasonCode: String?,
    val message: String?,
)

@Serializable
data class BiometricAttestation(
    val algorithm: String,
    val confidence: Double,
    val nonce: String,
    val signature: String,
)

@Serializable
data class PairRequest(
    val code: String,
    @SerialName("device_label") val deviceLabel: String,
    val platform: String? = "android",
    @SerialName("app_version") val appVersion: String? = null,
    @SerialName("hardware_id") val hardwareId: String? = null,
)

@Serializable
data class Profile(
    val uuid: String,
    val name: String,
    val capabilities: List<String>,
    @SerialName("allowed_event_ids") val allowedEventIds: List<Int>,
    @SerialName("max_scans_per_minute") val maxScansPerMinute: Int,
    @SerialName("duplicate_window_seconds") val duplicateWindowSeconds: Int,
)

@Serializable
data class Device(
    val uuid: String,
    val label: String,
    val platform: String,
)

@Serializable
data class PairResponse(
    val token: String,
    val device: Device,
    val profile: Profile,
)

@Serializable
data class ScanRequest(
    val payload: String,
    @SerialName("client_lat") val clientLat: Double? = null,
    @SerialName("client_lng") val clientLng: Double? = null,
    @SerialName("client_at") val clientAt: String? = null,
    @SerialName("device_meta") val deviceMeta: Map<String, JsonElement>? = null,
    val biometric: BiometricAttestation? = null,
)

@Serializable
data class TicketSummary(
    val uuid: String,
    @SerialName("ticket_number") val ticketNumber: String,
    @SerialName("scan_count") val scanCount: Int,
    @SerialName("is_voided") val isVoided: Boolean,
)

@Serializable
data class EventRef(
    val id: Int,
    val slug: String,
    val name: String,
)

@Serializable
data class ScanResult(
    @SerialName("scan_uuid") val scanUuid: String,
    val verdict: ScanVerdict,
    @SerialName("reason_code") val reasonCode: String?,
    @SerialName("was_admitted") val wasAdmitted: Boolean,
    @SerialName("was_duplicate") val wasDuplicate: Boolean,
    @SerialName("was_voided") val wasVoided: Boolean,
    val flags: List<FraudFlag>,
    @SerialName("latency_ms") val latencyMs: Int,
    val ticket: TicketSummary?,
    val event: EventRef?,
    @SerialName("server_time") val serverTime: String?,
)

@Serializable
data class HeartbeatRequest(
    val lat: Double? = null,
    val lng: Double? = null,
    val battery: Double? = null,
    val connection: String? = null,
)

@Serializable
data class HeartbeatResponse(
    val ok: Boolean,
    val revoked: Boolean,
    @SerialName("server_time") val serverTime: String,
)
