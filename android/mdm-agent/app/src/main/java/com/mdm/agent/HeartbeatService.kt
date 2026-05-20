package com.mdm.agent

import android.app.*
import android.content.Context
import android.content.Intent
import android.net.ConnectivityManager
import android.os.BatteryManager
import android.os.Build
import android.os.IBinder
import android.util.Log
import androidx.core.app.NotificationCompat
import com.mdm.agent.api.ApiClient
import com.mdm.agent.api.CommandHandler
import com.mdm.agent.api.HeartbeatRequest
import kotlinx.coroutines.*

class HeartbeatService : Service() {

    companion object {
        const val TAG = "MDMHeartbeat"
        const val CHANNEL_ID = "mdm_heartbeat"
        const val NOTIF_ID = 1001
        const val INTERVAL_MS = 60_000L
    }

    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())
    private val prefs by lazy { getSharedPreferences("mdm", Context.MODE_PRIVATE) }

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
        startForeground(NOTIF_ID, buildNotification())
        Log.i(TAG, "Heartbeat service started")
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        scope.launch {
            while (isActive) {
                sendHeartbeat()
                delay(INTERVAL_MS)
            }
        }
        return START_STICKY
    }

    private suspend fun sendHeartbeat() {
        val imei = prefs.getString("imei", "") ?: return
        try {
            val response = ApiClient.service.heartbeat(HeartbeatRequest(
                imei              = imei,
                battery_level     = getBatteryLevel(),
                ip_address        = getLocalIp(),
                os_version        = Build.VERSION.RELEASE,
                mdm_agent_version = BuildConfig.VERSION_NAME
            ))

            if (response.success) {
                // 1. Process pending remote commands (lock / wipe / ring etc.)
                response.commands?.forEach { cmd ->
                    CommandHandler(this@HeartbeatService, PolicyManager(this@HeartbeatService))
                        .handle(cmd)
                }

                // 2. OTA version check — update Chat App if server has a newer build
                response.app_versions?.forEach { serverApp ->
                    if (serverApp.package_name == "com.mdm.chat") {
                        val localCode  = prefs.getInt("chat_version_code", 0)
                        val serverCode = serverApp.version_code

                        if (serverCode > localCode && serverApp.apk_url.isNotBlank()) {
                            Log.i(TAG, "Chat App update available: v${serverApp.version_name} (code $serverCode)")
                            AppUpdateWorker.schedule(
                                context     = this@HeartbeatService,
                                apkUrl      = serverApp.apk_url,
                                packageName = serverApp.package_name
                            )
                            // Optimistically save new version code to avoid re-queueing on next heartbeat
                            prefs.edit().putInt("chat_version_code", serverCode).apply()
                        }
                    }
                }

                updateNotification("Last sync: ${java.text.SimpleDateFormat("HH:mm:ss", java.util.Locale.getDefault()).format(java.util.Date())}")
            }
        } catch (e: Exception) {
            Log.w(TAG, "Heartbeat failed: ${e.message}")
        }
    }

    private fun getBatteryLevel(): Int {
        val bm = getSystemService(Context.BATTERY_SERVICE) as BatteryManager
        return bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
    }

    private fun getLocalIp(): String {
        val cm = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        return try {
            val li = cm.getLinkProperties(cm.activeNetwork)
            li?.linkAddresses?.firstOrNull { !it.address.isLoopbackAddress }?.address?.hostAddress ?: "unknown"
        } catch (e: Exception) { "unknown" }
    }

    private fun createNotificationChannel() {
        val channel = NotificationChannel(CHANNEL_ID, "MDM Service", NotificationManager.IMPORTANCE_LOW).apply {
            description = "MDM Agent monitoring service"
        }
        (getSystemService(NotificationManager::class.java)).createNotificationChannel(channel)
    }

    private fun buildNotification(status: String = "Device managed"): Notification =
        NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("MDM Agent")
            .setContentText(status)
            .setSmallIcon(android.R.drawable.ic_lock_lock)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()

    private fun updateNotification(status: String) {
        val nm = getSystemService(NotificationManager::class.java)
        nm.notify(NOTIF_ID, buildNotification(status))
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onDestroy() {
        scope.cancel()
        super.onDestroy()
    }
}
