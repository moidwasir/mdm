package com.mdm.agent

import android.app.ActivityManager
import android.app.KeyguardManager
import android.app.admin.DevicePolicyManager
import android.content.BroadcastReceiver
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Build
import android.os.Bundle
import android.util.Log
import android.view.KeyEvent
import android.view.View
import android.view.WindowManager
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity

class LockdownActivity : AppCompatActivity() {

    companion object {
        const val TAG = "MDMLockdown"
        const val ACTION_UNLOCK = "com.mdm.agent.ACTION_UNLOCK"
    }

    private val dpm by lazy { getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager }
    private val adminComponent by lazy { ComponentName(this, DeviceAdminReceiver::class.java) }
    private val prefs by lazy { getSharedPreferences("mdm", Context.MODE_PRIVATE) }

    private var tapCount = 0
    private var lastTapTime: Long = 0

    private val unlockReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context, intent: Intent) {
            if (intent.action == ACTION_UNLOCK) {
                Log.i(TAG, "Unlock action received via broadcast")
                releaseLockAndFinish()
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_lockdown)

        // Make activity full screen and wake device
        wakeAndFullscreen()

        // Regiser local unlock broadcast receiver
        val filter = IntentFilter(ACTION_UNLOCK)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(unlockReceiver, filter, Context.RECEIVER_EXPORTED)
        } else {
            @Suppress("UnspecifiedRegisterReceiverFlag")
            registerReceiver(unlockReceiver, filter)
        }

        // Set device locked state in database/heartbeat
        prefs.edit().putBoolean("is_locked_state", true).apply()

        // Enter LockTask kiosk mode if Device Owner
        startKiosk()

        // 5-tap click handler on root layout for local PIN entry bypass
        val rootLayout = findViewById<View>(R.id.layout_lockdown_root)
        rootLayout.setOnClickListener {
            handleRootClick()
        }

        val tvDeviceInfo = findViewById<TextView>(R.id.tv_device_info)
        val imei = prefs.getString("imei", "") ?: ""
        tvDeviceInfo.text = "IMEI: $imei | Status: Locked"
    }

    private fun wakeAndFullscreen() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
            val keyguardManager = getSystemService(Context.KEYGUARD_SERVICE) as KeyguardManager
            keyguardManager.requestDismissKeyguard(this, null)
        } else {
            @Suppress("DEPRECATION")
            window.addFlags(
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or
                WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON or
                WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD or
                WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON
            )
        }

        // Hide navigation and status bar
        window.decorView.systemUiVisibility = (
                View.SYSTEM_UI_FLAG_LAYOUT_STABLE
                or View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
                or View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
                or View.SYSTEM_UI_FLAG_FULLSCREEN
                or View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
        )
    }

    private fun startKiosk() {
        try {
            if (dpm.isDeviceOwnerApp(packageName)) {
                // Ensure com.mdm.agent is in the allowed LockTask packages
                val activeAdmin = ComponentName(this, DeviceAdminReceiver::class.java)
                dpm.setLockTaskPackages(activeAdmin, arrayOf(packageName))
                startLockTask()
                Log.i(TAG, "LockTask started successfully")
            } else {
                Log.w(TAG, "Cannot start LockTask: Not Device Owner")
                Toast.makeText(this, "Security Warning: App is not Device Owner", Toast.LENGTH_LONG).show()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error starting LockTask: ${e.message}")
        }
    }

    private fun handleRootClick() {
        val currentTime = System.currentTimeMillis()
        if (currentTime - lastTapTime > 2000) {
            tapCount = 0
        }
        lastTapTime = currentTime
        tapCount++

        if (tapCount >= 5) {
            tapCount = 0
            showPinDialog()
        }
    }

    private fun showPinDialog() {
        val dialogView = layoutInflater.inflate(R.layout.dialog_pin, null)
        val dialog = android.app.AlertDialog.Builder(this)
            .setView(dialogView)
            .create()

        dialog.window?.setBackgroundDrawableResource(android.R.color.transparent)

        val tvDesc = dialogView.findViewById<TextView>(R.id.tv_dialog_desc)
        val etPin = dialogView.findViewById<EditText>(R.id.et_pin)
        val btnCancel = dialogView.findViewById<Button>(R.id.btn_cancel)
        val btnConfirm = dialogView.findViewById<Button>(R.id.btn_confirm)

        tvDesc.text = "Enter emergency bypass PIN to unlock."

        btnCancel.setOnClickListener { dialog.dismiss() }
        btnConfirm.setOnClickListener {
            val pin = etPin.text.toString().trim()
            if (pin == "8888") {
                dialog.dismiss()
                releaseLockAndFinish()
            } else {
                Toast.makeText(this, "Incorrect PIN.", Toast.LENGTH_SHORT).show()
                etPin.setText("")
            }
        }

        dialog.show()
    }

    private fun releaseLockAndFinish() {
        try {
            stopLockTask()
        } catch (e: Exception) {
            Log.e(TAG, "Error stopping LockTask: ${e.message}")
        }
        prefs.edit().putBoolean("is_locked_state", false).apply()
        finish()
    }

    override fun onBackPressed() {
        // Block back button press
        Log.d(TAG, "Back button press ignored during lockdown")
    }

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        // Block volume and power keys if possible
        if (keyCode == KeyEvent.KEYCODE_VOLUME_UP || keyCode == KeyEvent.KEYCODE_VOLUME_DOWN) {
            return true
        }
        return super.onKeyDown(keyCode, event)
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (!hasFocus) {
            // Keep the status bar collapsed
            val closeRecents = Intent(Intent.ACTION_CLOSE_SYSTEM_DIALOGS)
            sendBroadcast(closeRecents)
        }
    }

    override fun onDestroy() {
        try {
            unregisterReceiver(unlockReceiver)
        } catch (e: Exception) {}
        super.onDestroy()
    }
}
