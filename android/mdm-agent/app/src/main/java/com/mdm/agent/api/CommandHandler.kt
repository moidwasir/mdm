package com.mdm.agent.api

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.content.Intent
import android.util.Log
import com.mdm.agent.PolicyManager
import com.mdm.agent.RingActivity
import com.mdm.agent.LockdownActivity
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class CommandHandler(private val context: Context, private val policyManager: PolicyManager) {

    companion object { const val TAG = "MDMCommand" }

    suspend fun handle(cmd: DeviceCommand) {
        Log.i(TAG, "Executing command: ${cmd.command_type} (id=${cmd.id})")
        try {
            when (cmd.command_type) {
                "lock"          -> lockDevice()
                "unlock"        -> unlockDevice()
                "wipe"          -> wipeDevice()
                "restart"       -> restartDevice()
                "update_policy" -> updatePolicy(cmd)
                "ring"          -> ringDevice()
                "install_app"   -> installApp(cmd)
                "message"       -> showMessage(cmd)
                else            -> {
                    Log.w(TAG, "Unknown command: ${cmd.command_type}")
                    reportStatus(cmd.id, "failed", "Unknown command type: ${cmd.command_type}")
                    return
                }
            }
            reportStatus(cmd.id, "executed")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to execute command ${cmd.command_type} (id=${cmd.id}): ${e.message}")
            reportStatus(cmd.id, "failed", e.message ?: "Unknown error occurred")
        }
    }

    private fun lockDevice() {
        val i = Intent(context, LockdownActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
        }
        context.startActivity(i)
        Log.i(TAG, "Device Lockdown screen launched")
    }

    private fun unlockDevice() {
        val intent = Intent(LockdownActivity.ACTION_UNLOCK)
        context.sendBroadcast(intent)
        Log.i(TAG, "Unlock broadcast sent")
    }

    private fun wipeDevice() {
        val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
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
        val i = Intent(context, RingActivity::class.java).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
        }
        context.startActivity(i)
        Log.i(TAG, "RingActivity started")
    }

    private fun installApp(cmd: DeviceCommand) {
        val apkUrl = cmd.payload?.get("apk_url") as? String ?: return
        Log.i(TAG, "Install app from: $apkUrl")
        // Trigger download/update flow if needed
    }

    private fun showMessage(cmd: DeviceCommand) {
        val message = cmd.payload?.get("message") as? String ?: return
        val i = Intent(context, com.mdm.agent.MainActivity::class.java).apply {
            putExtra("admin_message", message)
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        context.startActivity(i)
    }

    private suspend fun reportStatus(commandId: Int, status: String, errorMessage: String? = null) {
        withContext(Dispatchers.IO) {
            try {
                val req = UpdateCommandStatusRequest(
                    command_id = commandId,
                    status = status,
                    error_message = errorMessage
                )
                val response = ApiClient.service.updateCommandStatus(req)
                Log.i(TAG, "Reported command $commandId status '$status' back to server, response success=${response.success}")
            } catch (e: Exception) {
                Log.w(TAG, "Failed to report command $commandId status to server: ${e.message}")
            }
        }
    }
}
