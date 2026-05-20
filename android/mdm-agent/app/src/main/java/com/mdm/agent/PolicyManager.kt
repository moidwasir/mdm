package com.mdm.agent

import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.UserManager
import android.util.Log
import androidx.work.*
import java.util.concurrent.TimeUnit

data class PolicyConfig(
    val kiosk_mode: Boolean = true,
    val kiosk_app: String = "com.mdm.chat",
    val disable_play_store: Boolean = true,
    val disable_camera: Boolean = false,
    val disable_factory_reset: Boolean = true,
    val disable_usb: Boolean = false,
    val disable_bluetooth: Boolean = false,
    val disable_wifi_config: Boolean = false
)

class PolicyManager(private val context: Context) {

    private val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
    private val admin = ComponentName(context, DeviceAdminReceiver::class.java)
    private val prefs = context.getSharedPreferences("mdm", Context.MODE_PRIVATE)

    companion object { const val TAG = "MDMPolicyManager" }

    fun applyPolicy(policy: PolicyConfig) {
        if (!dpm.isDeviceOwnerApp(context.packageName)) {
            Log.w(TAG, "Not device owner — cannot apply policy")
            return
        }
        Log.i(TAG, "Applying policy: $policy")

        // 1. Kiosk / Lock Task Mode
        if (policy.kiosk_mode) {
            dpm.setLockTaskPackages(admin, arrayOf(policy.kiosk_app, context.packageName))
            Log.i(TAG, "Lock task enabled for ${policy.kiosk_app}")
        } else {
            dpm.setLockTaskPackages(admin, emptyArray())
        }

        // 2. Disable Play Store
        try {
            dpm.setApplicationHidden(admin, "com.android.vending", policy.disable_play_store)
            dpm.setApplicationHidden(admin, "com.google.android.gms.setup", policy.disable_play_store)
        } catch (e: Exception) { Log.w(TAG, "Play Store hide failed: ${e.message}") }

        // 3. Camera
        dpm.setCameraDisabled(admin, policy.disable_camera)

        // 4. Factory reset
        if (policy.disable_factory_reset) {
            dpm.addUserRestriction(admin, UserManager.DISALLOW_FACTORY_RESET)
        } else {
            dpm.clearUserRestriction(admin, UserManager.DISALLOW_FACTORY_RESET)
        }

        // 5. USB file transfer
        if (policy.disable_usb) {
            dpm.addUserRestriction(admin, UserManager.DISALLOW_USB_FILE_TRANSFER)
        } else {
            dpm.clearUserRestriction(admin, UserManager.DISALLOW_USB_FILE_TRANSFER)
        }

        // 6. Unknown sources — always block
        dpm.addUserRestriction(admin, UserManager.DISALLOW_INSTALL_UNKNOWN_SOURCES)

        // 7. Account modification — prevent adding Google accounts
        dpm.addUserRestriction(admin, UserManager.DISALLOW_MODIFY_ACCOUNTS)

        // 8. Block config VPN & safe mode
        dpm.addUserRestriction(admin, UserManager.DISALLOW_CONFIG_VPN)
        dpm.addUserRestriction(admin, UserManager.DISALLOW_SAFE_BOOT)

        // 9. Bluetooth config
        if (policy.disable_bluetooth) {
            dpm.addUserRestriction(admin, UserManager.DISALLOW_CONFIG_BLUETOOTH)
        } else {
            dpm.clearUserRestriction(admin, UserManager.DISALLOW_CONFIG_BLUETOOTH)
        }

        // 10. WiFi config
        if (policy.disable_wifi_config) {
            dpm.addUserRestriction(admin, UserManager.DISALLOW_CONFIG_WIFI)
        } else {
            dpm.clearUserRestriction(admin, UserManager.DISALLOW_CONFIG_WIFI)
        }

        // Save policy for next boot
        prefs.edit()
            .putBoolean("policy_kiosk", policy.kiosk_mode)
            .putString("policy_kiosk_app", policy.kiosk_app)
            .putBoolean("policy_disable_store", policy.disable_play_store)
            .putBoolean("policy_disable_camera", policy.disable_camera)
            .putBoolean("policy_disable_factory_reset", policy.disable_factory_reset)
            .apply()
    }

    fun applyCurrentPolicy() {
        val policy = PolicyConfig(
            kiosk_mode = prefs.getBoolean("policy_kiosk", true),
            kiosk_app = prefs.getString("policy_kiosk_app", "com.mdm.chat") ?: "com.mdm.chat",
            disable_play_store = prefs.getBoolean("policy_disable_store", true),
            disable_camera = prefs.getBoolean("policy_disable_camera", false),
            disable_factory_reset = prefs.getBoolean("policy_disable_factory_reset", true)
        )
        applyPolicy(policy)
    }

    fun startKioskMode(packageName: String = "com.mdm.chat") {
        val intent = context.packageManager.getLaunchIntentForPackage(packageName) ?: return
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK)
        context.startActivity(intent)
    }

    fun scheduleChatAppInstall() {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()
        val work = OneTimeWorkRequestBuilder<ChatAppInstallWorker>()
            .setConstraints(constraints)
            .build()
        WorkManager.getInstance(context).enqueue(work)
    }
}
