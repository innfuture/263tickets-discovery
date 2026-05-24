package com.example_app.scanner

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.net.URLEncoder
import java.util.concurrent.TimeUnit

/**
 * Official Android / Kotlin / Server-side JVM client for the
 * example-app scanner API.
 *
 *   val client = ScannerClient("https://app.example.com")
 *   val pair = client.pair(PairRequest(code = code, deviceLabel = "Operator phone"))
 *   client.token = pair.token  // persist via EncryptedSharedPreferences
 *
 *   val result = client.scan(ScanRequest(payload = scannedQr))
 *   if (result.wasAdmitted) admit(result.ticket)
 */
class ScannerClient @JvmOverloads constructor(
    private val baseUrl: String,
    var token: String? = null,
    private val httpClient: OkHttpClient = defaultClient(),
    private val throwOnDeny: Boolean = true,
) {

    private val json = Json {
        ignoreUnknownKeys = true
        encodeDefaults = false
    }
    private val jsonMedia = "application/json; charset=utf-8".toMediaType()

    suspend fun pair(req: PairRequest): PairResponse {
        val response: PairResponse = request("POST", "/api/v1/scanning/pair", req, requiresAuth = false)
        this.token = response.token
        return response
    }

    suspend fun scan(req: ScanRequest): ScanResult {
        val result = runCatching { request<ScanResult>("POST", "/api/v1/scanning/scan", req) }
            .recoverCatching { e ->
                // 409 on deny carries a ScanResult body. Re-decode it.
                if (e is ScannerApiException && e.status == 409 && e.rawBody != null) {
                    json.decodeFromString<ScanResult>(e.rawBody)
                } else throw e
            }
            .getOrThrow()

        if (throwOnDeny && result.verdict == ScanVerdict.DENY) {
            throw ScanDeniedException(result.reasonCode, result.flags)
        }
        return result
    }

    suspend fun batchScan(scans: List<ScanRequest>): List<ScanResult> {
        require(scans.isNotEmpty()) { "Batch must be non-empty" }
        require(scans.size <= 100) { "Batch must be <= 100 scans" }
        @kotlinx.serialization.Serializable
        data class Body(val scans: List<ScanRequest>)

        @kotlinx.serialization.Serializable
        data class Wrap(val results: List<ScanResult>)

        return request<Wrap>("POST", "/api/v1/scanning/scan/batch", Body(scans)).results
    }

    suspend fun lookup(payload: String): ScanResult {
        val encoded = URLEncoder.encode(payload, "UTF-8")
        return request("GET", "/api/v1/scanning/tickets/$encoded")
    }

    suspend fun heartbeat(req: HeartbeatRequest = HeartbeatRequest()): HeartbeatResponse =
        request("POST", "/api/v1/scanning/heartbeat", req)

    private suspend inline fun <reified R> request(
        method: String,
        path: String,
        body: Any? = null,
        requiresAuth: Boolean = true,
    ): R = withContext(Dispatchers.IO) {
        if (requiresAuth && token == null) throw NotPairedException()

        val builder = Request.Builder()
            .url(baseUrl.trimEnd('/') + path)
            .header("Accept", "application/json")
            .header("User-Agent", "ExampleAppScannerSDK-Kotlin/1.0")

        if (requiresAuth) token?.let { builder.header("Authorization", "Bearer $it") }

        when {
            body == null && method == "GET" -> builder.get()
            body == null -> builder.method(method, ByteArray(0).toRequestBody(jsonMedia))
            else -> {
                val payload = json.encodeToString(serializerOf(body), body)
                builder.method(method, payload.toRequestBody(jsonMedia))
            }
        }

        val raw = httpClient.newCall(builder.build()).execute().use { resp ->
            val bodyStr = resp.body?.string().orEmpty()
            if (!resp.isSuccessful) {
                val code = runCatching { json.decodeFromString<ErrorEnvelope>(bodyStr).error }
                    .getOrNull() ?: "http_error"
                throw ScannerApiException(resp.code, code, rawBody = bodyStr)
            }
            bodyStr
        }
        json.decodeFromString<R>(raw)
    }

    @Suppress("UNCHECKED_CAST")
    private fun serializerOf(body: Any) = json.serializersModule.serializer(body::class.java)
        as kotlinx.serialization.KSerializer<Any>

    companion object {
        fun defaultClient(): OkHttpClient = OkHttpClient.Builder()
            .connectTimeout(10, TimeUnit.SECONDS)
            .readTimeout(10, TimeUnit.SECONDS)
            .build()
    }
}

@kotlinx.serialization.Serializable
private data class ErrorEnvelope(val error: String? = null, val message: String? = null)

class NotPairedException : RuntimeException("Not paired — call pair() first.")
class ScannerApiException(val status: Int, val code: String, val rawBody: String? = null) :
    RuntimeException("HTTP $status — $code")
class ScanDeniedException(val reasonCode: String?, val flags: List<FraudFlag>) :
    RuntimeException("Scan denied: ${reasonCode ?: "unknown"}")
