package com.mdm.agent

import android.app.KeyguardManager
import android.content.Context
import android.hardware.camera2.CameraManager
import android.media.AudioAttributes
import android.media.AudioManager
import android.media.MediaPlayer
import android.media.RingtoneManager
import android.os.Build
import android.os.Bundle
import android.util.Log
import android.view.WindowManager
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import kotlinx.coroutines.*

class RingActivity : AppCompatActivity() {

    companion object {
        const val TAG = "MDMRingActivity"
    }

    private var mediaPlayer: MediaPlayer? = null
    private var isRinging = true
    private val scope = CoroutineScope(Dispatchers.Main + SupervisorJob())
    private var originalVolume: Int = 0

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_ring)

        // Wake screen and show over keyguard
        wakeDevice()

        // Maximize ALARM volume stream
        setMaxVolume()

        // Start loud alarm tone loop
        startAlarmSound()

        // Start camera torch blinking loop
        startTorchBlinking()

        // Setup UI hooks
        val btnDismiss = findViewById<Button>(R.id.btn_dismiss_alarm)
        btnDismiss.setOnClickListener {
            showDismissPinDialog()
        }
    }

    private fun wakeDevice() {
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
    }

    private fun setMaxVolume() {
        try {
            val audioManager = getSystemService(Context.AUDIO_SERVICE) as AudioManager
            originalVolume = audioManager.getStreamVolume(AudioManager.STREAM_ALARM)
            val maxVol = audioManager.getStreamMaxVolume(AudioManager.STREAM_ALARM)
            audioManager.setStreamVolume(AudioManager.STREAM_ALARM, maxVol, 0)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to adjust audio stream settings: ${e.message}")
        }
    }

    private fun startAlarmSound() {
        try {
            val alarmUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM)
                ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
            mediaPlayer = MediaPlayer().apply {
                setDataSource(this@RingActivity, alarmUri)
                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_ALARM)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build()
                )
                isLooping = true
                prepare()
                start()
            }
        } catch (e: Exception) {
            Log.e(TAG, "MediaPlayer preparation failed: ${e.message}")
        }
    }

    private fun startTorchBlinking() {
        val cameraManager = getSystemService(Context.CAMERA_SERVICE) as CameraManager
        scope.launch(Dispatchers.IO) {
            try {
                val cameraId = cameraManager.cameraIdList.firstOrNull() ?: return@launch
                var flashState = false
                while (isRinging) {
                    try {
                        cameraManager.setTorchMode(cameraId, flashState)
                        flashState = !flashState
                    } catch (e: Exception) {
                        Log.e(TAG, "Failed setting torch state: ${e.message}")
                    }
                    delay(250) // Blink every 250ms
                }
                // Ensure torch is off when finished
                cameraManager.setTorchMode(cameraId, false)
            } catch (e: Exception) {
                Log.e(TAG, "Failed torch access: ${e.message}")
            }
        }
    }

    private fun showDismissPinDialog() {
        val dialogView = layoutInflater.inflate(R.layout.dialog_pin, null)
        val dialog = android.app.AlertDialog.Builder(this)
            .setView(dialogView)
            .create()

        dialog.window?.setBackgroundDrawableResource(android.R.color.transparent)

        val tvDesc = dialogView.findViewById<TextView>(R.id.tv_dialog_desc)
        val etPin = dialogView.findViewById<EditText>(R.id.et_pin)
        val btnCancel = dialogView.findViewById<Button>(R.id.btn_cancel)
        val btnConfirm = dialogView.findViewById<Button>(R.id.btn_confirm)

        tvDesc.text = "Enter authorization code to stop alarm."

        btnCancel.setOnClickListener { dialog.dismiss() }
        btnConfirm.setOnClickListener {
            val pin = etPin.text.toString().trim()
            if (pin == "8888") {
                dialog.dismiss()
                stopAlarmAndFinish()
            } else {
                Toast.makeText(this, "Incorrect PIN. Access Denied.", Toast.LENGTH_SHORT).show()
                etPin.setText("")
            }
        }

        dialog.show()
    }

    private fun stopAlarmAndFinish() {
        isRinging = false
        try {
            mediaPlayer?.stop()
            mediaPlayer?.release()
            mediaPlayer = null
        } catch (e: Exception) {
            Log.e(TAG, "MediaPlayer stop failed: ${e.message}")
        }

        // Restore volume
        try {
            val audioManager = getSystemService(Context.AUDIO_SERVICE) as AudioManager
            audioManager.setStreamVolume(AudioManager.STREAM_ALARM, originalVolume, 0)
        } catch (e: Exception) {}

        finish()
    }

    override fun onDestroy() {
        isRinging = false
        stopAlarmAndFinish()
        scope.cancel()
        super.onDestroy()
    }
}
