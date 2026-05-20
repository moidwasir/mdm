package com.mdm.agent

import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInstaller
import android.util.Log
import java.io.File

/**
 * SilentInstallHelper
 *
 * Shared utility for silently installing APKs as Device Owner.
 * Used by both ChatAppInstallWorker (initial install) and AppUpdateWorker (OTA updates).
 */
object SilentInstallHelper {

    const val TAG = "SilentInstall"

    /**
     * Silently installs an APK file using PackageInstaller.
     * Requires the app to be a Device Owner (DPC) — no user prompt shown.
     *
     * @param context Application context
     * @param apkFile The APK file to install
     * @param sessionName Label for the PackageInstaller session (e.g. package name)
     */
    fun install(context: Context, apkFile: File, sessionName: String = "mdm-install") {
        if (!apkFile.exists() || apkFile.length() == 0L) {
            Log.e(TAG, "APK file missing or empty: ${apkFile.absolutePath}")
            return
        }

        try {
            val packageInstaller = context.packageManager.packageInstaller

            val sessionParams = PackageInstaller.SessionParams(
                PackageInstaller.SessionParams.MODE_FULL_INSTALL
            )

            val sessionId = packageInstaller.createSession(sessionParams)
            val session   = packageInstaller.openSession(sessionId)

            apkFile.inputStream().use { apkStream ->
                session.openWrite(sessionName, 0, apkFile.length()).use { out ->
                    apkStream.copyTo(out)
                    session.fsync(out)
                }
            }

            val intent = Intent(context, MainActivity::class.java)
            val pi = PendingIntent.getActivity(
                context, sessionId, intent,
                PendingIntent.FLAG_MUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
            )

            session.commit(pi.intentSender)
            session.close()

            Log.i(TAG, "APK install committed for session '$sessionName' (id=$sessionId)")
        } catch (e: Exception) {
            Log.e(TAG, "Silent install failed: ${e.message}")
        }
    }
}
