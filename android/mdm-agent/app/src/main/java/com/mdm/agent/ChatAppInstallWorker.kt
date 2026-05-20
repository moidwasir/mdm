package com.mdm.agent

import android.content.Context
import android.util.Log
import androidx.work.Worker
import androidx.work.WorkerParameters
import java.io.File
import java.net.URL

/**
 * ChatAppInstallWorker
 * Downloads the Chat App APK from the MDM server and silently installs it.
 * Scheduled once after DPC enrollment to push the chat app onto the device.
 */
class ChatAppInstallWorker(ctx: Context, params: WorkerParameters) : Worker(ctx, params) {

    companion object { const val TAG = "ChatInstall" }

    override fun doWork(): Result {
        val serverUrl = BuildConfig.SERVER_URL
        val apkUrl    = "$serverUrl/apk/chat-app.apk"

        return try {
            Log.i(TAG, "Downloading chat app APK from $apkUrl")

            val dest = File(applicationContext.cacheDir, "chat-app.apk")
            URL(apkUrl).openStream().use { input ->
                dest.outputStream().use { output -> input.copyTo(output) }
            }

            Log.i(TAG, "Download complete (${dest.length()} bytes) — installing")
            SilentInstallHelper.install(applicationContext, dest, "chat-app")
            Result.success()
        } catch (e: Exception) {
            Log.e(TAG, "Chat app install failed: ${e.message}")
            Result.retry()
        }
    }
}
