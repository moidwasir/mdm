package com.mdm.agent

import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.os.UserManager
import android.util.Log
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.mdm.agent.api.ApiClient
import com.mdm.agent.api.CheckRegistrationRequest
import com.mdm.agent.api.CheckRegistrationResponse
import com.mdm.agent.api.EnrollRequest
import kotlinx.coroutines.*

// Dashboard Imports
import android.view.View
import android.widget.TextView
import android.widget.Button
import android.widget.EditText
import android.graphics.Color
import android.graphics.drawable.GradientDrawable
import android.provider.Settings

@Suppress("HardwareIds", "MissingPermission")
class MainActivity : AppCompatActivity() {

    companion object {
        const val TAG = "MDMMain"
        const val CHAT_PKG = "com.mdm.chat"
    }

    private val dpm by lazy { getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager }
    private val adminComponent by lazy { ComponentName(this, DeviceAdminReceiver::class.java) }
    private val prefs by lazy { getSharedPreferences("mdm", Context.MODE_PRIVATE) }
    private val scope = CoroutineScope(Dispatchers.Main + SupervisorJob())

    // UI elements
    private lateinit var tvStatusTitle: TextView
    private lateinit var tvStatusDesc: TextView
    private lateinit var vStatusDot: View
    private lateinit var btnLaunchChat: Button
    private lateinit var btnLaunchCamera: Button
    private lateinit var btnLaunchFiles: Button
    private lateinit var tvDeviceId: TextView
    private lateinit var tvImei: TextView
    private lateinit var tvActivationImei: TextView
    private lateinit var btnActivateSecureMode: Button
    private lateinit var cardActivation: View
    private lateinit var cardAppsDrawer: View
    private lateinit var layoutWorkspace: View
    private lateinit var layoutSettings: View
    private lateinit var btnTabWorkspace: TextView
    private lateinit var btnTabSettings: TextView
    private lateinit var layoutSystemVersion: View
    private lateinit var tvWorkspaceLabel: TextView

    // Secret Toggle State
    private var versionClickCount = 0
    private var lastVersionClickTime: Long = 0

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // 1. Set our beautiful glassmorphic layout
        setContentView(R.layout.activity_main)

        // 2. Setup UI references and click listeners
        setupUI()

        // Check if token was passed in the intent (manual developer enrollment command)
        val tokenExtra = intent?.getStringExtra("enrollment_token")
        if (!tokenExtra.isNullOrEmpty()) {
            prefs.edit().putString("enrollment_token", tokenExtra).apply()
        }

        val enrolled = prefs.getBoolean("enrolled", false)

        if (enrolled) {
            // Already enrolled — apply policy, start services, and refresh UI
            applyCurrentPolicy()
            startHeartbeatService()
            updateUI()
            // Request battery optimization exemption on each launch (only prompts once)
            requestBatteryOptimizationExemption()
        } else {
            // First run — enroll with server
            updateUI()
            val token = prefs.getString("enrollment_token", "") ?: ""
            if (token.isNotEmpty()) {
                enrollWithServer(token)
            } else {
                // Check registration with IMEI
                checkRegistration()
            }
        }
    }

    private fun setupUI() {
        tvStatusTitle = findViewById(R.id.tv_status_title)
        tvStatusDesc = findViewById(R.id.tv_status_desc)
        vStatusDot = findViewById(R.id.v_status_dot)
        btnLaunchChat = findViewById(R.id.btn_launch_chat)
        btnLaunchCamera = findViewById(R.id.btn_launch_camera)
        btnLaunchFiles = findViewById(R.id.btn_launch_files)
        tvDeviceId = findViewById(R.id.tv_device_id)
        tvImei = findViewById(R.id.tv_imei)
        tvActivationImei = findViewById(R.id.tv_activation_imei)
        btnActivateSecureMode = findViewById(R.id.btn_activate_secure_mode)
        cardActivation = findViewById(R.id.card_activation)
        cardAppsDrawer = findViewById(R.id.card_apps_drawer)
        layoutWorkspace = findViewById(R.id.layout_workspace)
        layoutSettings = findViewById(R.id.layout_settings)
        btnTabWorkspace = findViewById(R.id.btn_tab_workspace)
        btnTabSettings = findViewById(R.id.btn_tab_settings)
        layoutSystemVersion = findViewById(R.id.layout_system_version)
        tvWorkspaceLabel = findViewById(R.id.tv_workspace_label)

        btnLaunchChat.setOnClickListener { launchChatApp() }
        btnLaunchCamera.setOnClickListener { launchCamera() }
        btnLaunchFiles.setOnClickListener { launchFiles() }

        // Activation button handler
        btnActivateSecureMode.setOnClickListener { handleActivateSecureMode() }

        // Navigation tab switching
        btnTabWorkspace.setOnClickListener { switchTab(true) }
        btnTabSettings.setOnClickListener { switchTab(false) }

        // Hidden multi-tap row for system settings toggle
        layoutSystemVersion.setOnClickListener { handleSystemVersionClick() }
    }

    private fun switchTab(showWorkspace: Boolean) {
        if (showWorkspace) {
            layoutWorkspace.visibility = View.VISIBLE
            layoutSettings.visibility = View.GONE
            btnTabWorkspace.setBackgroundResource(R.drawable.accent_btn_bg)
            btnTabWorkspace.setTextColor(Color.WHITE)
            btnTabSettings.setBackgroundResource(android.R.color.transparent)
            btnTabSettings.setTextColor(Color.parseColor("#94A3B8"))
        } else {
            layoutWorkspace.visibility = View.GONE
            layoutSettings.visibility = View.VISIBLE
            btnTabWorkspace.setBackgroundResource(android.R.color.transparent)
            btnTabWorkspace.setTextColor(Color.parseColor("#94A3B8"))
            btnTabSettings.setBackgroundResource(R.drawable.accent_btn_bg)
            btnTabSettings.setTextColor(Color.WHITE)
        }
    }

    private fun handleSystemVersionClick() {
        val currentTime = System.currentTimeMillis()
        if (currentTime - lastVersionClickTime > 2000) {
            versionClickCount = 0
        }
        lastVersionClickTime = currentTime
        versionClickCount++

        if (versionClickCount >= 5) {
            versionClickCount = 0
            showPinDialog()
        } else if (versionClickCount >= 2) {
            val stepsRemaining = 5 - versionClickCount
            Toast.makeText(
                this,
                "You are $stepsRemaining steps away from system controller...",
                Toast.LENGTH_SHORT
            ).show()
        }
    }

    private fun checkRegistration() {
        scope.launch {
            try {
                val imei = getImei()
                val response = withContext(Dispatchers.IO) {
                    ApiClient.service.checkRegistration(CheckRegistrationRequest(imei = imei))
                }

                if (response.success && response.registered) {
                    // Save token for enrollment
                    if (response.token != null) {
                        prefs.edit().putString("enrollment_token", response.token).apply()
                    }

                    if (response.enrolled == true) {
                        // Already enrolled, proceed normally
                        prefs.edit().putBoolean("enrolled", true).apply()
                        applyCurrentPolicy()
                        startHeartbeatService()
                        updateUI()
                    } else {
                        updateUI()
                    }
                } else {
                    Toast.makeText(this@MainActivity, response.message ?: "Device not registered", Toast.LENGTH_LONG).show()
                }
            } catch (e: Exception) {
                Log.e(TAG, "Check registration failed: ${e.message}")
                Toast.makeText(this@MainActivity, "Check registration failed: ${e.message}", Toast.LENGTH_LONG).show()
            }
        }
    }

    private fun handleActivateSecureMode() {
        // Verify if Device Owner is active
        if (!dpm.isDeviceOwnerApp(packageName)) {
            // Device Owner not active - show dialog explaining ADB command
            val dialog = android.app.AlertDialog.Builder(this)
                .setTitle("Device Owner Not Active")
                .setMessage("Device Owner must be set via ADB before activating Secure Mode.\n\nConnect your device via USB and run:\n\nadb shell dpm set-device-owner com.mdm.agent/com.mdm.agent.DeviceAdminReceiver")
                .setPositiveButton("OK", null)
                .create()
            dialog.show()
            return
        }

        // Device Owner is active - proceed with enrollment
        val token = prefs.getString("enrollment_token", "") ?: ""
        if (token.isNotEmpty()) {
            enrollWithServer(token)
        } else {
            Toast.makeText(this, "No enrollment token found", Toast.LENGTH_SHORT).show()
        }
    }

    private fun updateUI() {
        val enrolled = prefs.getBoolean("enrolled", false)
        val prefsImei = prefs.getString("imei", "") ?: ""
        val imei = if (prefsImei.isNotEmpty()) prefsImei else getImei()
        val deviceId = prefs.getInt("device_id", 0)

        tvImei.text = imei
        tvDeviceId.text = if (deviceId > 0) "#$deviceId" else "Offline (Awaiting Server ID)"
        tvActivationImei.text = "IMEI: $imei"

        val pm = PolicyManager(this)
        val isSecureMode = pm.isSecureModeActive()

        // Configure glowing round status dot background
        val dotBg = GradientDrawable().apply {
            shape = GradientDrawable.OVAL
            setColor(if (isSecureMode) Color.parseColor("#10B981") else Color.parseColor("#F59E0B"))
        }
        vStatusDot.background = dotBg

        if (isSecureMode) {
            tvStatusTitle.text = "MDM SECURE MODE ACTIVE"
            tvStatusTitle.setTextColor(Color.parseColor("#10B981"))
            tvStatusDesc.text = "All personal and non-approved applications are programmatically hidden. Only verified workspace utilities (Chat, Camera, Files) are visible to the user."
        } else {
            tvStatusTitle.text = "NORMAL PHONE MODE ACTIVE"
            tvStatusTitle.setTextColor(Color.parseColor("#F59E0B"))
            tvStatusDesc.text = "The device is currently running in a fully unlocked normal operating state. All user applications are visible. Use the administrator panel below to return to the secure MDM workspace."
        }

        if (!enrolled) {
            tvStatusTitle.text = "AWAITING ENROLLMENT..."
            tvStatusTitle.setTextColor(Color.parseColor("#F59E0B"))
            tvStatusDesc.text = "Device is not yet enrolled with the MDM administration server. Please register the device IMEI on the admin portal."
            cardActivation.visibility = View.VISIBLE
            cardAppsDrawer.visibility = View.GONE
            tvWorkspaceLabel.text = "ACTIVATION REQUIRED"
        } else {
            cardActivation.visibility = View.GONE
            cardAppsDrawer.visibility = View.VISIBLE
            tvWorkspaceLabel.text = "SECURE WORKSPACE UTILITIES"
        }
    }

    private fun showPinDialog() {
        val pm = PolicyManager(this)
        val isSecureMode = pm.isSecureModeActive()

        // Inflate the gorgeous custom PIN dialog
        val dialogView = layoutInflater.inflate(R.layout.dialog_pin, null)
        val dialog = android.app.AlertDialog.Builder(this)
            .setView(dialogView)
            .create()

        dialog.window?.setBackgroundDrawableResource(android.R.color.transparent)

        val tvDialogDesc = dialogView.findViewById<TextView>(R.id.tv_dialog_desc)
        val etPin = dialogView.findViewById<EditText>(R.id.et_pin)
        val btnCancel = dialogView.findViewById<Button>(R.id.btn_cancel)
        val btnConfirm = dialogView.findViewById<Button>(R.id.btn_confirm)

        if (!isSecureMode) {
            // Entering MDM Secure Mode ("new world") from Normal Phone Mode.
            // Shows passcode PIN (8888) as requested, so the user knows what to enter.
            tvDialogDesc.text = "Switching to MDM Secure Workspace.\nPasscode PIN: 8888"
        } else {
            // Exiting MDM Secure Mode to Normal Phone Mode.
            // Prompts for passcode PIN (8888) to verify admin identity.
            tvDialogDesc.text = "Enter administrator passcode to return to Normal Phone Mode."
        }

        btnCancel.setOnClickListener {
            dialog.dismiss()
        }

        btnConfirm.setOnClickListener {
            val pin = etPin.text.toString().trim()
            if (pin == "8888") {
                dialog.dismiss()
                val nextMode = !isSecureMode

                // Toggle secure mode using PolicyManager
                pm.setSecureMode(nextMode)

                // Refresh our dashboard UI
                updateUI()

                if (nextMode) {
                    Toast.makeText(this, "MDM Secure Mode activated! Apps hidden.", Toast.LENGTH_LONG).show()
                } else {
                    Toast.makeText(this, "Normal Phone Mode restored! All apps visible.", Toast.LENGTH_LONG).show()
                }
            } else {
                Toast.makeText(this, "Incorrect passcode. Access Denied.", Toast.LENGTH_SHORT).show()
                etPin.setText("")
            }
        }

        dialog.show()
    }

    private fun launchCamera() {
        try {
            val intent = Intent(android.provider.MediaStore.INTENT_ACTION_STILL_IMAGE_CAMERA).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            startActivity(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to launch Camera: ${e.message}")
            Toast.makeText(this, "Failed to launch Camera", Toast.LENGTH_SHORT).show()
        }
    }

    private fun launchFiles() {
        try {
            val intent = packageManager.getLaunchIntentForPackage("com.oneplus.filemanager")
                ?: packageManager.getLaunchIntentForPackage("com.google.android.apps.nbu.files")
                ?: packageManager.getLaunchIntentForPackage("com.google.android.documentsui")
                ?: packageManager.getLaunchIntentForPackage("com.android.documentsui")
                ?: Intent(Intent.ACTION_OPEN_DOCUMENT).apply {
                    addCategory(Intent.CATEGORY_OPENABLE)
                    type = "*/*"
                }
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            startActivity(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to launch Files app: ${e.message}")
            Toast.makeText(this, "Failed to launch Files App", Toast.LENGTH_SHORT).show()
        }
    }

    private fun enrollWithServer(token: String) {
        scope.launch {
            try {
                val imei = getImei()
                val response = withContext(Dispatchers.IO) {
                    ApiClient.service.enroll(EnrollRequest(
                        token = token,
                        imei = imei,
                        manufacturer = android.os.Build.MANUFACTURER,
                        model = android.os.Build.MODEL,
                        os_version = android.os.Build.VERSION.RELEASE
                    ))
                }

                if (response.success) {
                    prefs.edit()
                        .putBoolean("enrolled", true)
                        .putString("imei", imei)
                        .putInt("device_id", response.device_id ?: 0)
                        .apply()

                    val pm = PolicyManager(this@MainActivity)
                    response.policy?.let { pm.applyPolicy(it) }
                    pm.applyCurrentPolicy()

                    startHeartbeatService()
                    installChatApp()
                    Log.i(TAG, "Enrollment successful!")
                    updateUI()
                }
            } catch (e: Exception) {
                Log.e(TAG, "Enrollment failed: ${e.message}")
                Toast.makeText(this@MainActivity, "Enrollment failed: ${e.message}", Toast.LENGTH_LONG).show()
            }
        }
    }

    private fun applyCurrentPolicy() {
        PolicyManager(this).applyCurrentPolicy()
    }

    private fun startHeartbeatService() {
        try {
            startForegroundService(Intent(this, HeartbeatService::class.java))
        } catch (e: Exception) {
            Log.e(TAG, "Failed to start HeartbeatService: ${e.message}")
        }
    }

    /**
     * Requests that Android's battery manager ignore battery optimizations for this app.
     * Critical for OnePlus/Oppo (OxygenOS/ColorOS) which aggressively kill background
     * services even when declared as foreground services.
     *
     * The prompt is shown once — after the user responds, the decision is remembered
     * and we never ask again.
     */
    private fun requestBatteryOptimizationExemption() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return

        val pm = getSystemService(POWER_SERVICE) as PowerManager
        val packageName = packageName

        if (pm.isIgnoringBatteryOptimizations(packageName)) {
            // Already whitelisted — nothing to do
            Log.i(TAG, "Battery optimization already ignored for $packageName")
            return
        }

        // Only ask once (track in prefs)
        val alreadyAsked = prefs.getBoolean("battery_opt_asked", false)
        if (alreadyAsked) return

        prefs.edit().putBoolean("battery_opt_asked", true).apply()

        try {
            val intent = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS).apply {
                data = Uri.parse("package:$packageName")
            }
            startActivity(intent)
            Log.i(TAG, "Battery optimization exemption dialog launched")
        } catch (e: Exception) {
            // Fallback: open battery optimization settings page manually
            Log.w(TAG, "Direct battery exemption dialog failed, opening settings: ${e.message}")
            try {
                startActivity(Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS))
            } catch (se: Exception) {
                Log.e(TAG, "Could not open battery settings: ${se.message}")
            }
        }
    }

    private fun launchChatApp() {
        try {
            val intent = packageManager.getLaunchIntentForPackage(CHAT_PKG)
            if (intent != null) {
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                startActivity(intent)
            } else {
                Toast.makeText(this, "Personal Chat App is not installed yet", Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Failed to launch Chat App: ${e.message}")
            Toast.makeText(this, "Failed to launch Chat App", Toast.LENGTH_SHORT).show()
        }
    }

    private fun installChatApp() {
        PolicyManager(this).scheduleChatAppInstall()
    }

    @Suppress("HardwareIds", "MissingPermission")
    private fun getImei(): String {
        val raw = try {
            val tm = getSystemService(Context.TELEPHONY_SERVICE) as android.telephony.TelephonyManager
            @Suppress("DEPRECATION")
            val id = tm.deviceId
            if (!id.isNullOrEmpty() && id != "unknown") {
                id
            } else {
                @Suppress("DEPRECATION")
                val serial = android.os.Build.SERIAL
                if (!serial.isNullOrEmpty() && serial != "unknown") serial else ""
            }
        } catch (e: Exception) {
            @Suppress("DEPRECATION")
            val serial = try { android.os.Build.SERIAL } catch (se: Exception) { "" }
            if (!serial.isNullOrEmpty() && serial != "unknown") serial else ""
        }

        // If raw is a valid 15-digit numeric string, return it
        if (raw.matches(Regex("^\\d{15}$"))) {
            return raw
        }

        // Otherwise, construct a stable 15-digit dummy IMEI starting with 12345
        val hash = raw.hashCode().toString().replace("-", "")
        val paddedHash = hash.padEnd(10, '0').take(10)
        return "12345$paddedHash"
    }

    override fun onNewIntent(intent: Intent?) {
        super.onNewIntent(intent)
        setIntent(intent)
        val tokenExtra = intent?.getStringExtra("enrollment_token")
        if (!tokenExtra.isNullOrEmpty()) {
            prefs.edit().putString("enrollment_token", tokenExtra).apply()
        }
        val enrolled = prefs.getBoolean("enrolled", false)
        if (!enrolled) {
            val token = prefs.getString("enrollment_token", "") ?: ""
            if (token.isNotEmpty()) {
                enrollWithServer(token)
            } else {
                checkRegistration()
            }
        }
        updateUI()
    }

    override fun onDestroy() {
        super.onDestroy()
        scope.cancel()
    }
}
