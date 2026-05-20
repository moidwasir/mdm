package com.mdm.chat

import android.util.Log
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class FcmService : FirebaseMessagingService() {

    companion object { const val TAG = "MDMFcm" }

    /** Called when a new FCM token is generated or refreshed */
    override fun onNewToken(token: String) {
        Log.i(TAG, "FCM token refreshed: ${token.take(20)}...")
        // Register the new token with the MDM server
        FcmTokenManager.sendTokenToServer(applicationContext, token)
    }

    /** Called when a push message arrives while app is in foreground/background */
    override fun onMessageReceived(message: RemoteMessage) {
        Log.i(TAG, "FCM message from: ${message.from}")

        val type = message.data["type"] ?: "notification"

        when (type) {
            "new_message" -> handleNewMessage(message)
            "command"     -> handleCommand(message)
            else          -> showDefaultNotification(message)
        }
    }

    private fun handleNewMessage(message: RemoteMessage) {
        val senderName   = message.data["sender_name"] ?: "Someone"
        val content      = message.data["content"] ?: "Sent a message"
        val convId       = message.data["conversation_id"] ?: "0"

        NotificationHelper(this).showChatNotification(
            title   = senderName,
            body    = content,
            convId  = convId.toIntOrNull() ?: 0
        )
    }

    private fun handleCommand(message: RemoteMessage) {
        val cmd = message.data["command"] ?: return
        Log.i(TAG, "Admin command via FCM: $cmd")
        // Commands are also handled via heartbeat — FCM is just a fast wake-up trigger
    }

    private fun showDefaultNotification(message: RemoteMessage) {
        val title = message.notification?.title ?: message.data["title"] ?: "MDM Chat"
        val body  = message.notification?.body  ?: message.data["body"]  ?: ""
        NotificationHelper(this).showChatNotification(title, body, 0)
    }
}
