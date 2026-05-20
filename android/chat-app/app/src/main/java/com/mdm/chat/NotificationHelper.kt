package com.mdm.chat

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import androidx.core.app.NotificationCompat

class NotificationHelper(private val context: Context) {

    companion object {
        const val CHANNEL_MESSAGES = "mdm_messages"
        const val CHANNEL_SYSTEM   = "mdm_system"
    }

    init { createChannels() }

    fun showChatNotification(title: String, body: String, convId: Int) {
        val intent = Intent(context, MainActivity::class.java).apply {
            putExtra("conv_id", convId)
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
        }
        val pi = PendingIntent.getActivity(context, convId, intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

        val notification = NotificationCompat.Builder(context, CHANNEL_MESSAGES)
            .setContentTitle(title)
            .setContentText(body)
            .setSmallIcon(android.R.drawable.ic_dialog_email)
            .setContentIntent(pi)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setDefaults(NotificationCompat.DEFAULT_ALL)
            .build()

        val nm = context.getSystemService(NotificationManager::class.java)
        nm.notify(System.currentTimeMillis().toInt(), notification)
    }

    private fun createChannels() {
        val nm = context.getSystemService(NotificationManager::class.java)
        nm.createNotificationChannel(
            NotificationChannel(CHANNEL_MESSAGES, "Messages", NotificationManager.IMPORTANCE_HIGH)
                .apply { description = "New chat message notifications" }
        )
        nm.createNotificationChannel(
            NotificationChannel(CHANNEL_SYSTEM, "System", NotificationManager.IMPORTANCE_DEFAULT)
                .apply { description = "MDM system notifications" }
        )
    }
}
