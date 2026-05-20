package com.mdm.chat

import android.content.Context
import android.util.Log
import com.mdm.chat.data.api.ApiClient
import kotlinx.coroutines.*

object FcmTokenManager {

    private const val TAG = "FcmToken"
    private const val PREFS_KEY = "fcm_token"

    fun sendTokenToServer(context: Context, token: String) {
        val prefs = context.getSharedPreferences("mdm", Context.MODE_PRIVATE)
        val userId = prefs.getInt("user_id", 0)
        val savedToken = prefs.getString(PREFS_KEY, "")

        if (userId == 0 || token == savedToken) return

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val response = ApiClient.service.registerFcmToken(
                    userId,
                    mapOf("fcm_token" to token)
                )
                if (response["success"] == true) {
                    prefs.edit().putString(PREFS_KEY, token).apply()
                    Log.i(TAG, "FCM token registered with server")
                }
            } catch (e: Exception) {
                Log.w(TAG, "FCM token registration failed: ${e.message}")
            }
        }
    }
}
