package com.mdm.agent

import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.UserManager
import android.util.Log
import androidx.work.*
import java.util.concurrent.TimeUnit
import android.content.pm.PackageManager
import android.view.inputmethod.InputMethodManager

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

        // Apply secure mode state
        val secureModeEnabled = prefs.getBoolean("policy_secure_mode", true)
        setSecureMode(secureModeEnabled)
    }

    fun isSecureModeActive(): Boolean {
        return prefs.getBoolean("policy_secure_mode", true)
    }

    fun setSecureMode(enabled: Boolean) {
        if (!dpm.isDeviceOwnerApp(context.packageName)) {
            Log.w(TAG, "Not device owner — cannot toggle secure mode")
            return
        }
        Log.i(TAG, "Setting secure mode: $enabled")

        val pm = context.packageManager
        val flags = PackageManager.MATCH_UNINSTALLED_PACKAGES or PackageManager.MATCH_DISABLED_COMPONENTS
        
        // 1. Get all installed launcher apps
        val mainIntent = Intent(Intent.ACTION_MAIN, null).apply {
            addCategory(Intent.CATEGORY_LAUNCHER)
        }
        val launcherApps = pm.queryIntentActivities(mainIntent, flags)
        
        // 2. Identify Camera apps handling image capture
        val cameraIntent = Intent(android.provider.MediaStore.ACTION_IMAGE_CAPTURE)
        val cameraApps = pm.queryIntentActivities(cameraIntent, flags).map { it.activityInfo.packageName }

        // 3. Identify Files/Document apps
        val filesIntent = Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = "*/*"
        }
        val filesApps = pm.queryIntentActivities(filesIntent, flags).map { it.activityInfo.packageName }

        // 4. Keyboard/IME apps
        val ims = context.getSystemService(Context.INPUT_METHOD_SERVICE) as android.view.inputmethod.InputMethodManager
        val keyboardApps = ims.inputMethodList.map { it.packageName }

        // 5. Default Launcher/Home apps (MUST NOT HIDE!)
        val homeIntent = Intent(Intent.ACTION_MAIN).apply {
            addCategory(Intent.CATEGORY_HOME)
        }
        val homeApps = pm.queryIntentActivities(homeIntent, flags).map { it.activityInfo.packageName }

        // 6. Define strict allowlist (packages that should remain visible in Secure Mode)
        val allowList = mutableSetOf<String>().apply {
            add(context.packageName) // MDM Agent itself
            add("com.mdm.chat")       // Chat app

            // ── Camera packages ────────────────────────────────────────────
            addAll(cameraApps)
            add("com.oplus.camera")               // OnePlus / Oppo stock camera (modern)
            add("com.android.camera")             // AOSP camera fallback
            add("com.android.camera2")            // AOSP camera2 fallback
            add("com.google.android.GoogleCamera") // Pixel Camera
            add("com.oneplus.camera")             // Legacy OnePlus camera package

            // ── Files / Documents packages ────────────────────────────────
            addAll(filesApps)
            add("com.oplus.filemanager")           // OnePlus/Oppo File Manager (modern)
            add("com.oneplus.filemanager")         // Legacy OnePlus File Manager
            add("com.coloros.filemanager")         // ColorOS File Manager
            add("com.google.android.apps.nbu.files") // Google Files
            add("com.google.android.documentsui") // Google document picker
            add("com.android.documentsui")        // AOSP document picker

            // ── OnePlus/Oppo system services (MUST NOT hide) ──────────────
            add("com.oplus.safecenter")            // Oppo/OnePlus Safe Center (system manager)
            add("com.coloros.safecenter")          // ColorOS Safe Center
            add("com.oneplus.security")            // Legacy OnePlus security app
            add("com.oplus.phonemanager")          // Oppo Phone Manager
            add("com.heytap.market")               // Oppo App Market (needed for silent installs)

            // ── Phone and Contacts background telephony (do NOT hide background services) ──
            add("com.android.phone")
            add("com.android.providers.telephony")
            add("com.android.server.telecom")
            add("com.oplus.telephony")             // OnePlus telephony services

            // ── Keyboards & launcher apps (NEVER hide system interface!) ──
            addAll(keyboardApps)
            addAll(homeApps)

            // ── Critical Android system packages ─────────────────────────
            add("android")
            add("com.android.systemui")
            add("com.google.android.gms")          // Google Play Services (required for auth/push)
            add("com.google.android.gsf")          // Google Services Framework
        }

        Log.i(TAG, "Allowlist packages: $allowList")

        // 7. Hide/Unhide packages using DevicePolicyManager
        if (enabled) {
            for (resolveInfo in launcherApps) {
                val pkg = resolveInfo.activityInfo.packageName
                // If secure mode is enabled, hide anything NOT in the allowlist
                if (!allowList.contains(pkg)) {
                    try {
                        dpm.setApplicationHidden(admin, pkg, true)
                        Log.d(TAG, "Hidden package: $pkg")
                    } catch (e: Exception) {
                        Log.w(TAG, "Failed to hide package $pkg: ${e.message}")
                    }
                }
            }
        } else {
            // If secure mode is disabled, unhide everything that is currently hidden
            try {
                val allPackages = pm.getInstalledPackages(PackageManager.MATCH_UNINSTALLED_PACKAGES or PackageManager.MATCH_DISABLED_COMPONENTS)
                for (pkgInfo in allPackages) {
                    val pkg = pkgInfo.packageName
                    if (dpm.isApplicationHidden(admin, pkg)) {
                        try {
                            dpm.setApplicationHidden(admin, pkg, false)
                            Log.i(TAG, "Unhidden package: $pkg")
                        } catch (e: Exception) {
                            Log.w(TAG, "Failed to unhide package $pkg: ${e.message}")
                        }
                    }
                }
            } catch (e: Exception) {
                Log.e(TAG, "Failed to query all packages for unhiding: ${e.message}")
                // Fallback: unhide from the query launcher list
                for (resolveInfo in launcherApps) {
                    val pkg = resolveInfo.activityInfo.packageName
                    try {
                        dpm.setApplicationHidden(admin, pkg, false)
                        Log.d(TAG, "Fallback unhidden package: $pkg")
                    } catch (ex: Exception) {
                        Log.w(TAG, "Failed to unhide package $pkg in fallback: ${ex.message}")
                    }
                }
            }
        }
        
        // 8. If secure mode is active, make sure standard Lock Task packages are cleared
        if (enabled) {
            try {
                dpm.setLockTaskPackages(admin, emptyArray())
            } catch (e: Exception) {
                Log.w(TAG, "Failed to clear lock task packages: ${e.message}")
            }
        }

        // Save mode state in shared preferences
        prefs.edit().putBoolean("policy_secure_mode", enabled).apply()
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
