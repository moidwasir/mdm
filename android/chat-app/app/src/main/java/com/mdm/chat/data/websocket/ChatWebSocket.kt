package com.mdm.chat.data.websocket

import android.util.Log
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.asSharedFlow
import okhttp3.*
import org.json.JSONObject
import java.util.concurrent.TimeUnit

sealed class WsEvent {
    data class AuthOk(val userId: Int) : WsEvent()
    data class NewMessage(val raw: JSONObject) : WsEvent()
    data class Typing(val userId: Int, val convId: Int, val isTyping: Boolean) : WsEvent()
    data class Error(val message: String) : WsEvent()
    object Disconnected : WsEvent()
    object Connected : WsEvent()
}

class ChatWebSocket(
    private val wsUrl: String,
    private val userId: Int,
    private val token: String
) {
    companion object { const val TAG = "ChatWS" }

    private var ws: WebSocket? = null
    private val client = OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .pingInterval(30, TimeUnit.SECONDS)
        .build()

    private val _events = MutableSharedFlow<WsEvent>(extraBufferCapacity = 128)
    val events = _events.asSharedFlow()

    var isConnected = false
        private set

    fun connect() {
        if (isConnected) return
        val request = Request.Builder().url(wsUrl).build()
        ws = client.newWebSocket(request, object : WebSocketListener() {

            override fun onOpen(webSocket: WebSocket, response: Response) {
                Log.i(TAG, "WebSocket connected")
                isConnected = true
                _events.tryEmit(WsEvent.Connected)
                // Authenticate immediately
                send(mapOf("type" to "auth", "user_id" to userId, "token" to token))
            }

            override fun onMessage(webSocket: WebSocket, text: String) {
                try {
                    val json = JSONObject(text)
                    when (json.getString("type")) {
                        "auth_ok"     -> _events.tryEmit(WsEvent.AuthOk(json.getInt("user_id")))
                        "new_message" -> _events.tryEmit(WsEvent.NewMessage(json.getJSONObject("message")))
                        "typing"      -> _events.tryEmit(WsEvent.Typing(
                            json.getInt("user_id"),
                            json.getInt("conversation_id"),
                            json.getBoolean("is_typing")
                        ))
                        "auth_error"  -> _events.tryEmit(WsEvent.Error(json.getString("message")))
                        "error"       -> _events.tryEmit(WsEvent.Error(json.getString("message")))
                    }
                } catch (e: Exception) {
                    Log.w(TAG, "Parse error: ${e.message}")
                }
            }

            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                Log.w(TAG, "WebSocket failed: ${t.message}")
                isConnected = false
                _events.tryEmit(WsEvent.Disconnected)
                // Reconnect after 5s
                android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({ connect() }, 5_000)
            }

            override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                isConnected = false
                _events.tryEmit(WsEvent.Disconnected)
            }
        })
    }

    fun sendMessage(convId: Int, content: String, type: String = "text", mediaUrl: String? = null) {
        send(buildMap {
            put("type", "message")
            put("conversation_id", convId)
            put("content", content)
            put("message_type", type)
            if (mediaUrl != null) put("media_url", mediaUrl)
        })
    }

    fun sendTyping(convId: Int, isTyping: Boolean) {
        send(mapOf("type" to "typing", "conversation_id" to convId, "is_typing" to isTyping))
    }

    fun markRead(convId: Int, messageId: Int) {
        send(mapOf("type" to "mark_read", "conversation_id" to convId, "message_id" to messageId))
    }

    fun ping() { send(mapOf("type" to "ping")) }

    private fun send(data: Map<String, Any?>) {
        val json = JSONObject(data).toString()
        ws?.send(json) ?: Log.w(TAG, "WebSocket not connected")
    }

    fun disconnect() {
        ws?.close(1000, "App closed")
        isConnected = false
    }
}
