package com.mdm.agent.api

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.content.Intent
import android.util.Log
import com.mdm.agent.PolicyManager

class CommandHandler(private val context: Context, private val policyManager: PolicyManager) {

    companion object { const val TAG = "MDMCommand" }

    fun handle(cmd: DeviceCommand) {
        Log.i(TAG, "Executing command: ${cmd.command_type} (id=${cmd.id})")
        when (cmd.command_type) {
            "lock"          -> lockDevice()
            "wipe"          -> wipeDevice()
            "restart"       -> restartDevice()
            "update_policy" -> updatePolicy(cmd)
            "ring"          -> ringDevice()
            "install_app"   -> installApp(cmd)
            "message"       -> showMessage(cmd)
            else            -> Log.w(TAG, "Unknown command: ${cmd.command_type}")
        }
    }

    private fun lockDevice() {
        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        dpm.lockNow()
        Log.i(TAG, "Device locked")
    }

    private fun wipeDevice() {
        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        // This permanently wipes the device — use with caution
        dpm.wipeData(DevicePolicyManager.WIPE_RESET_PROTECTION_DATA)
        Log.i(TAG, "Device wipe initiated")
    }

    private fun restartDevice() {
        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.N) {
            dpm.reboot(android.content.ComponentName(context, com.mdm.agent.DeviceAdminReceiver::class.java))
        }
    }

    private fun updatePolicy(cmd: DeviceCommand) {
        policyManager.applyCurrentPolicy()
    }

    private fun ringDevice() {
        // Play loud alarm tone
        try {
            val ringtone = android.media.RingtoneManager.getRingtone(
                context, android.media.RingtoneManager.getDefaultUri(android.media.RingtoneManager.TYPE_ALARM)
            )
            ringtone.play()
            // Stop after 10 seconds
            android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({ ringtone.stop() }, 10_000)
        } catch (e: Exception) { Log.w(TAG, "Ring failed: ${e.message}") }
    }

    private fun installApp(cmd: DeviceCommand) {
        val apkUrl = cmd.payload?.get("apk_url") as? String ?: return
        // Trigger download worker
        Log.i(TAG, "Install app from: $apkUrl")
    }

    private fun showMessage(cmd: DeviceCommand) {
        val message = cmd.payload?.get("message") as? String ?: return
        val i = Intent(context, com.mdm.agent.MainActivity::class.java).apply {
            putExtra("admin_message", message)
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        context.startActivity(i)
    }
}
