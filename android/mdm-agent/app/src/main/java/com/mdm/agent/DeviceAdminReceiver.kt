package com.mdm.agent

import android.app.admin.DeviceAdminReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

class DeviceAdminReceiver : DeviceAdminReceiver() {

    companion object {
        const val TAG = "MDMAdminReceiver"
    }

    /** Called when device provisioning via QR code is complete */
    override fun onProfileProvisioningComplete(context: Context, intent: Intent) {
        Log.i(TAG, "Device provisioning complete — starting enrollment")
        val prefs = context.getSharedPreferences("mdm", Context.MODE_PRIVATE)
        prefs.edit().putBoolean("provisioning_complete", true).apply()

        // Launch main activity to complete enrollment with server
        val i = Intent(context, MainActivity::class.java)
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
        context.startActivity(i)
    }

    override fun onEnabled(context: Context, intent: Intent) {
        Log.i(TAG, "Device Admin enabled")
    }

    override fun onDisabled(context: Context, intent: Intent) {
        Log.w(TAG, "Device Admin disabled — MDM lost control")
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)
        // Handle boot completed — restart heartbeat service
        if (intent.action == Intent.ACTION_BOOT_COMPLETED) {
            Log.i(TAG, "Boot completed — restarting heartbeat service")
            val serviceIntent = Intent(context, HeartbeatService::class.java)
            context.startForegroundService(serviceIntent)
        }
    }
}
