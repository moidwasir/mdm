# Android Chat App — Implementation Guide

## Overview
The Chat App is a **Jetpack Compose** Android app. It is the **only user-facing app** on the locked device. Users cannot exit it (Lock Task Mode). It connects to the admin server WebSocket for real-time messaging.

## Package Name
`com.mdm.chat`

## Project Structure
```
chat-app/
├── app/src/main/
│   ├── AndroidManifest.xml
│   └── java/com/mdm/chat/
│       ├── MainActivity.kt
│       ├── data/
│       │   ├── api/
│       │   │   ├── ChatApiService.kt      ← Retrofit REST client
│       │   │   └── RetrofitClient.kt
│       │   ├── websocket/
│       │   │   └── ChatWebSocket.kt       ← OkHttp WebSocket client
│       │   ├── db/
│       │   │   ├── AppDatabase.kt         ← Room local cache
│       │   │   ├── MessageDao.kt
│       │   │   └── ConversationDao.kt
│       │   └── models/
│       │       ├── Message.kt
│       │       └── Conversation.kt
│       └── ui/
│           ├── auth/
│           │   └── AuthScreen.kt          ← Auto-login by IMEI
│           ├── conversations/
│           │   ├── ConversationListScreen.kt
│           │   └── ConversationListViewModel.kt
│           └── chat/
│               ├── ChatScreen.kt
│               └── ChatViewModel.kt
```

## 1. AndroidManifest.xml
```xml
<manifest package="com.mdm.chat">
    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.READ_PHONE_STATE" /> <!-- for IMEI -->
    <uses-permission android:name="android.permission.READ_MEDIA_IMAGES" />
    <uses-permission android:name="android.permission.CAMERA" />
    <uses-permission android:name="android.permission.RECORD_AUDIO" />

    <application android:label="MDM Chat">
        <activity android:name=".MainActivity" android:exported="true">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
        </activity>
    </application>
</manifest>
```

## 2. ChatWebSocket.kt — Real-time Connection
```kotlin
package com.mdm.chat.data.websocket

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import okhttp3.*
import org.json.JSONObject

class ChatWebSocket(private val serverUrl: String, private val userId: Int, private val token: String) {

    private var ws: WebSocket? = null
    private val client = OkHttpClient()

    private val _messages = MutableSharedFlow<JSONObject>(extraBufferCapacity = 64)
    val messages = _messages.asSharedFlow()

    fun connect() {
        val request = Request.Builder().url(serverUrl).build()
        ws = client.newWebSocket(request, object : WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                // Authenticate immediately
                send(JSONObject().apply {
                    put("type", "auth")
                    put("user_id", userId)
                    put("token", token)
                })
            }

            override fun onMessage(webSocket: WebSocket, text: String) {
                val json = JSONObject(text)
                _messages.tryEmit(json)
            }

            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                // Reconnect after 5 seconds
                Thread.sleep(5000)
                connect()
            }
        })
    }

    fun sendMessage(conversationId: Int, content: String, type: String = "text") {
        send(JSONObject().apply {
            put("type", "message")
            put("conversation_id", conversationId)
            put("content", content)
            put("message_type", type)
        })
    }

    fun sendTyping(conversationId: Int, isTyping: Boolean) {
        send(JSONObject().apply {
            put("type", "typing")
            put("conversation_id", conversationId)
            put("is_typing", isTyping)
        })
    }

    fun markRead(conversationId: Int, messageId: Int) {
        send(JSONObject().apply {
            put("type", "mark_read")
            put("conversation_id", conversationId)
            put("message_id", messageId)
        })
    }

    private fun send(data: JSONObject) {
        ws?.send(data.toString())
    }

    fun disconnect() {
        ws?.close(1000, "App closed")
    }
}
```

## 3. ConversationListScreen.kt — Jetpack Compose UI
```kotlin
@Composable
fun ConversationListScreen(
    conversations: List<Conversation>,
    onConversationClick: (Conversation) -> Unit
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Messages", style = MaterialTheme.typography.headlineMedium) },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color(0xFF0A0E1A)
                )
            )
        },
        containerColor = Color(0xFF0A0E1A)
    ) { padding ->
        LazyColumn(contentPadding = padding) {
            items(conversations) { conv ->
                ConversationItem(conv, onClick = { onConversationClick(conv) })
            }
        }
    }
}

@Composable
fun ConversationItem(conv: Conversation, onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        // Avatar
        Box(
            Modifier
                .size(52.dp)
                .clip(CircleShape)
                .background(Color(0xFF6366F1)),
            contentAlignment = Alignment.Center
        ) {
            Text(conv.name.first().toString(), color = Color.White, fontSize = 20.sp)
        }
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(conv.name, fontWeight = FontWeight.SemiBold, color = Color.White)
            Text(conv.lastMessage ?: "No messages", color = Color(0xFF94A3B8), fontSize = 13.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        if (conv.unreadCount > 0) {
            Box(
                Modifier
                    .size(22.dp)
                    .clip(CircleShape)
                    .background(Color(0xFF6366F1)),
                contentAlignment = Alignment.Center
            ) {
                Text(conv.unreadCount.toString(), color = Color.White, fontSize = 11.sp)
            }
        }
    }
}
```

## 4. ChatScreen.kt — Message Thread
```kotlin
@Composable
fun ChatScreen(
    conversationId: Int,
    currentUserId: Int,
    messages: List<Message>,
    onSendMessage: (String) -> Unit,
    onTyping: (Boolean) -> Unit
) {
    var inputText by remember { mutableStateOf("") }
    val listState = rememberLazyListState()

    LaunchedEffect(messages.size) {
        if (messages.isNotEmpty()) listState.animateScrollToItem(messages.size - 1)
    }

    Column(Modifier.fillMaxSize().background(Color(0xFF0A0E1A))) {
        LazyColumn(Modifier.weight(1f), state = listState) {
            items(messages, key = { it.id }) { msg ->
                MessageBubble(msg, isOwn = msg.senderId == currentUserId)
            }
        }
        MessageInput(
            text = inputText,
            onTextChange = { inputText = it; onTyping(it.isNotEmpty()) },
            onSend = {
                if (inputText.isNotBlank()) {
                    onSendMessage(inputText)
                    inputText = ""
                    onTyping(false)
                }
            }
        )
    }
}

@Composable
fun MessageBubble(message: Message, isOwn: Boolean) {
    val bgColor = if (isOwn) Color(0xFF6366F1) else Color(0xFF1A1F35)
    val align   = if (isOwn) Alignment.End else Alignment.Start

    Column(Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 4.dp), horizontalAlignment = align) {
        Box(
            Modifier
                .clip(RoundedCornerShape(16.dp, 16.dp, if (isOwn) 4.dp else 16.dp, if (isOwn) 16.dp else 4.dp))
                .background(bgColor)
                .padding(10.dp, 8.dp)
        ) {
            Text(message.content ?: "", color = Color.White)
        }
        Text(
            timeAgo(message.createdAt),
            color = Color(0xFF64748B), fontSize = 11.sp,
            modifier = Modifier.padding(top = 2.dp, start = 4.dp, end = 4.dp)
        )
    }
}
```

## 5. build.gradle Dependencies
```gradle
dependencies {
    // Jetpack Compose BOM
    implementation platform('androidx.compose:compose-bom:2024.02.00')
    implementation 'androidx.compose.ui:ui'
    implementation 'androidx.compose.material3:material3'
    implementation 'androidx.activity:activity-compose:1.8.2'

    // Navigation
    implementation 'androidx.navigation:navigation-compose:2.7.6'

    // Room (local message cache)
    implementation 'androidx.room:room-runtime:2.6.1'
    implementation 'androidx.room:room-ktx:2.6.1'
    kapt 'androidx.room:room-compiler:2.6.1'

    // Network
    implementation 'com.squareup.retrofit2:retrofit:2.9.0'
    implementation 'com.squareup.retrofit2:converter-gson:2.9.0'
    implementation 'com.squareup.okhttp3:okhttp:4.12.0'

    // Coroutines
    implementation 'org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3'

    // Image loading
    implementation 'io.coil-kt:coil-compose:2.5.0'
}
```

## WebSocket Message Protocol (Client ↔ Server)

### Client → Server
| Type | Payload | Description |
|------|---------|-------------|
| `auth` | `{user_id, token}` | Authenticate after connect |
| `message` | `{conversation_id, content, message_type}` | Send a message |
| `typing` | `{conversation_id, is_typing}` | Typing indicator |
| `mark_read` | `{conversation_id, message_id}` | Mark messages as read |
| `ping` | — | Keepalive |

### Server → Client
| Type | Payload | Description |
|------|---------|-------------|
| `connected` | `{message}` | Initial connection ack |
| `auth_ok` | `{user_id}` | Auth success |
| `auth_error` | `{message}` | Auth failure |
| `new_message` | `{message}` | Incoming message |
| `typing` | `{user_id, conversation_id, is_typing}` | Someone typing |
| `pong` | `{ts}` | Ping response |
| `error` | `{message}` | General error |
