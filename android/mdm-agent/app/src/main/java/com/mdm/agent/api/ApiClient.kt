package com.mdm.agent.api

import com.google.gson.annotations.SerializedName
import com.mdm.agent.BuildConfig
import com.mdm.agent.PolicyConfig
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.Body
import retrofit2.http.POST
import java.util.concurrent.TimeUnit

// ── Request bodies ────────────────────────────────────────────────────────────

data class EnrollRequest(
    val token: String,
    val imei: String,
    val manufacturer: String,
    val model: String,
    val os_version: String
)

data class HeartbeatRequest(
    val imei: String,
    val battery_level: Int?,
    val ip_address: String?,
    val os_version: String?,
    val mdm_agent_version: String?,
    val storage_free_mb: Long? = null,
    val wifi_ssid: String?    = null,
    val latitude: Double?     = null,
    val longitude: Double?    = null
)

// ── Response bodies ───────────────────────────────────────────────────────────

data class EnrollResponse(
    val success: Boolean,
    val device_id: Int?,
    val policy: PolicyConfig?,
    val ws_url: String?,
    val api_url: String?
)

data class HeartbeatResponse(
    val success: Boolean,
    val commands: List<DeviceCommand>?,
    val policy: PolicyConfig?,
    val interval: Int?,
    @SerializedName("app_versions")
    val app_versions: List<AppVersion>?   // OTA update catalog
)

data class DeviceCommand(
    val id: Int,
    val command_type: String,
    val payload: Map<String, Any>?
)

/** One entry in the OTA version catalog returned by the heartbeat endpoint */
data class AppVersion(
    val app_name: String,
    val package_name: String,
    val version_name: String,
    val version_code: Int,
    val apk_url: String
)

// ── Retrofit service ──────────────────────────────────────────────────────────

interface MdmApiService {
    @POST("api/enrollment/verify.php")
    suspend fun enroll(@Body request: EnrollRequest): EnrollResponse

    @POST("api/devices/heartbeat.php")
    suspend fun heartbeat(@Body request: HeartbeatRequest): HeartbeatResponse
}

// ── Singleton client ──────────────────────────────────────────────────────────

object ApiClient {
    val service: MdmApiService by lazy {
        val logger = HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BASIC }
        val client = OkHttpClient.Builder()
            .addInterceptor(logger)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(60, TimeUnit.SECONDS)
            .build()

        Retrofit.Builder()
            .baseUrl(BuildConfig.SERVER_URL + "/")
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(MdmApiService::class.java)
    }
}
