package com.example_app.scanner

import javax.crypto.Mac
import javax.crypto.spec.SecretKeySpec

/**
 * Verifies an inbound scan webhook signature.
 *
 *   Header: X-Scanner-Signature: t=<unix>,v1=<hex>
 *           hex = hmac_sha256("<t>.<rawBody>", secret)
 *
 * Returns true on valid signature within `toleranceSeconds`. Throws
 * on missing / malformed headers so misconfiguration fails loudly.
 */
object ScannerWebhook {

    fun verifySignature(
        rawBody: String,
        header: String?,
        secret: String,
        toleranceSeconds: Long = 300,
    ): Boolean {
        requireNotNull(header) { "Missing X-Scanner-Signature header." }

        val parts = header.split(",").mapNotNull { kv ->
            kv.split("=", limit = 2).takeIf { it.size == 2 }
                ?.let { it[0].trim() to it[1].trim() }
        }.toMap()

        val t = parts["t"]?.toLongOrNull()
            ?: throw IllegalArgumentException("Malformed signature header (no t).")
        val v1 = parts["v1"]
            ?: throw IllegalArgumentException("Malformed signature header (no v1).")

        val now = System.currentTimeMillis() / 1000
        if (kotlin.math.abs(now - t) > toleranceSeconds) return false

        val mac = Mac.getInstance("HmacSHA256").apply {
            init(SecretKeySpec(secret.toByteArray(Charsets.UTF_8), "HmacSHA256"))
        }
        val expected = mac.doFinal("$t.$rawBody".toByteArray(Charsets.UTF_8))
            .joinToString("") { "%02x".format(it) }

        return constantTimeEquals(expected, v1)
    }

    private fun constantTimeEquals(a: String, b: String): Boolean {
        if (a.length != b.length) return false
        var diff = 0
        for (i in a.indices) {
            diff = diff or (a[i].code xor b[i].code)
        }
        return diff == 0
    }
}
