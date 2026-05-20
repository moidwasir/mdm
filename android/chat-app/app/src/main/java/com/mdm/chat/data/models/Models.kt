package com.mdm.chat.data.models

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "conversations")
data class Conversation(
    @PrimaryKey val id: Int,
    val type: String,           // "direct" | "group"
    val name: String?,
    val avatar: String?,
    val lastMessage: String?,
    val lastMessageAt: String?,
    val unreadCount: Int = 0,
    val myRole: String = "member",
    val updatedAt: String?
)

@Entity(tableName = "messages")
data class Message(
    @PrimaryKey val id: Int,
    val conversationId: Int,
    val senderId: Int,
    val senderName: String?,
    val senderAvatar: String?,
    val content: String?,
    val messageType: String = "text",  // text | image | file | voice
    val mediaUrl: String?,
    val replyToId: Int?,
    val replyContent: String?,
    val status: String = "sent",       // sent | delivered | read
    val createdAt: String
)

data class User(
    val id: Int,
    val username: String,
    val displayName: String,
    val avatar: String?,
    val lastSeen: String?
)

data class AuthSession(
    val token: String,
    val userId: Int,
    val username: String,
    val displayName: String,
    val avatar: String?,
    val deviceId: Int,
    val wsUrl: String
)
