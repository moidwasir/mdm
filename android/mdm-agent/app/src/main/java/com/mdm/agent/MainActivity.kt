package com.mdm.agent

import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.os.UserManager
import android.util.Log
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.mdm.agent.api.ApiClient
import com.mdm.agent.api.EnrollRequest
import kotlinx.coroutines.*

class MainActivity : AppCompatActivity() {

    companion object {
        const val TAG = "MDMMain"
        const val CHAT_PKG = "com.mdm.chat"
    }

    private val dpm by lazy { getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager }
    private val adminComponent by lazy { ComponentName(this, DeviceAdminReceiver::class.java) }
    private val prefs by lazy { getSharedPreferences("mdm", Context.MODE_PRIVATE) }
    private val scope = CoroutineScope(Dispatchers.Main + SupervisorJob())

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val enrolled = prefs.getBoolean("enrolled", false)

        if (enrolled) {
            // Already enrolled — apply policy and start heartbeat
            applyCurrentPolicy()
            startHeartbeatService()
            launchChatApp()
        } else {
            // First run after QR provisioning — enroll with server
            val token = prefs.getString("enrollment_token", "") ?: ""
            if (token.isNotEmpty()) {
                enrollWithServer(token)
            } else {
                Log.w(TAG, "No enrollment token found — waiting for provisioning")
                Toast.makeText(this, "MDM Agent: Waiting for enrollment...", Toast.LENGTH_LONG).show()
            }
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

                    response.policy?.let { PolicyManager(this@MainActivity).applyPolicy(it) }
                    startHeartbeatService()
                    installChatApp()
                    Log.i(TAG, "Enrollment successful!")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Enrollment failed: ${e.message}")
            }
        }
    }

    private fun applyCurrentPolicy() {
        // PolicyManager reads saved policy from prefs
        PolicyManager(this).applyCurrentPolicy()
    }

    private fun startHeartbeatService() {
        startForegroundService(Intent(this, HeartbeatService::class.java))
    }

    private fun launchChatApp() {
        val intent = packageManager.getLaunchIntentForPackage(CHAT_PKG) ?: return
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        startActivity(intent)
        finish()
    }

    private fun installChatApp() {
        // Trigger chat app download & install via PolicyManager
        PolicyManager(this).scheduleChatAppInstall()
    }

    @Suppress("HardwareIds", "MissingPermission")
    private fun getImei(): String {
        return try {
            val tm = getSystemService(Context.TELEPHONY_SERVICE) as android.telephony.TelephonyManager
            @Suppress("DEPRECATION")
            tm.deviceId ?: android.os.Build.SERIAL
        } catch (e: Exception) {
            android.os.Build.SERIAL
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        scope.cancel()
    }
}
