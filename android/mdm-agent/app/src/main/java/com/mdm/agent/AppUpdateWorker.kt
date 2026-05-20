package com.mdm.agent

import android.content.Context
import android.util.Log
import androidx.work.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.io.FileOutputStream
import java.net.URL
import java.util.concurrent.TimeUnit

/**
 * AppUpdateWorker
 *
 * Triggered by HeartbeatService when a newer version of the Chat App is
 * available in the OTA catalog returned by the heartbeat endpoint.
 *
 * As Device Owner, we use SilentInstallHelper for a fully silent install
 * with no user prompt required.
 */
class AppUpdateWorker(ctx: Context, params: WorkerParameters) : CoroutineWorker(ctx, params) {

    companion object {
        const val TAG              = "AppUpdate"
        const val KEY_APK_URL      = "apk_url"
        const val KEY_PACKAGE_NAME = "package_name"

        fun schedule(context: Context, apkUrl: String, packageName: String) {
            val request = OneTimeWorkRequestBuilder<AppUpdateWorker>()
                .setInputData(
                    workDataOf(
                        KEY_APK_URL      to apkUrl,
                        KEY_PACKAGE_NAME to packageName
                    )
                )
                .setConstraints(
                    Constraints.Builder()
                        .setRequiredNetworkType(NetworkType.CONNECTED)
                        .build()
                )
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 1, TimeUnit.MINUTES)
                .build()

            WorkManager.getInstance(context).enqueueUniqueWork(
                "update_$packageName",
                ExistingWorkPolicy.REPLACE,
                request
            )
            Log.i(TAG, "OTA update scheduled for $packageName from $apkUrl")
        }
    }

    override suspend fun doWork(): Result {
        val apkUrl      = inputData.getString(KEY_APK_URL)      ?: return Result.failure()
        val packageName = inputData.getString(KEY_PACKAGE_NAME) ?: return Result.failure()

        Log.i(TAG, "Starting OTA download for $packageName from $apkUrl")

        return withContext(Dispatchers.IO) {
            try {
                // 1. Download APK to cache
                val apkFile = File(applicationContext.cacheDir, "$packageName-update.apk")
                downloadApk(apkUrl, apkFile)
                Log.i(TAG, "APK downloaded: ${apkFile.length()} bytes → ${apkFile.absolutePath}")

                // 2. Silent install via PackageInstaller (Device Owner privilege)
                SilentInstallHelper.install(applicationContext, apkFile, packageName)

                Log.i(TAG, "OTA install triggered for $packageName")
                Result.success()
            } catch (e: Exception) {
                Log.e(TAG, "OTA update failed: ${e.message}")
                Result.retry()
            }
        }
    }

    private fun downloadApk(urlStr: String, dest: File) {
        val url        = URL(urlStr)
        val connection = url.openConnection().apply {
            connectTimeout = 30_000
            readTimeout    = 120_000
        }
        connection.connect()

        connection.getInputStream().use { input ->
            FileOutputStream(dest).use { output ->
                val buffer = ByteArray(8192)
                var bytes: Int
                while (input.read(buffer).also { bytes = it } != -1) {
                    output.write(buffer, 0, bytes)
                }
            }
        }
    }
}
