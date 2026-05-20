package com.mdm.chat.data.api

import com.mdm.chat.BuildConfig
import com.mdm.chat.data.models.Conversation
import com.mdm.chat.data.models.Message
import com.mdm.chat.data.models.User
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.*
import java.util.concurrent.TimeUnit

// ── Response wrappers ─────────────────────────────────────────────────────────

data class AuthResponse(val success: Boolean, val token: String?, val user_id: Int?, val username: String?,
                        val display_name: String?, val avatar: String?, val device_id: Int?, val ws_url: String?)

data class ConversationsResponse(val success: Boolean, val conversations: List<Conversation>?)
data class ConversationResponse(val success: Boolean, val conversation: Conversation?)
data class CreateConvResponse(val success: Boolean, val conversation_id: Int?, val created: Boolean?)
data class MessagesResponse(val success: Boolean, val messages: List<Message>?, val has_more: Boolean?)
data class SendMessageResponse(val success: Boolean, val message: Message?)
data class MediaUploadResponse(val success: Boolean, val url: String?, val filename: String?)
data class UsersResponse(val success: Boolean, val users: List<User>?)

// ── API Service ───────────────────────────────────────────────────────────────

interface ChatApiService {
    @POST("api/chat/auth.php")
    suspend fun authenticate(@Body body: Map<String, String>): AuthResponse

    @GET("api/chat/conversations.php")
    suspend fun listConversations(
        @Header("X-User-Id") userId: Int,
        @Query("action") action: String = "list"
    ): ConversationsResponse

    @GET("api/chat/conversations.php")
    suspend fun getConversation(
        @Header("X-User-Id") userId: Int,
        @Query("action") action: String = "get",
        @Query("id") id: Int
    ): ConversationResponse

    @POST("api/chat/conversations.php")
    suspend fun createConversation(
        @Header("X-User-Id") userId: Int,
        @Body body: Map<String, Any>
    ): CreateConvResponse

    @GET("api/chat/messages.php")
    suspend fun getMessages(
        @Header("X-User-Id") userId: Int,
        @Query("conversation_id") convId: Int,
        @Query("before_id") beforeId: Int = Int.MAX_VALUE,
        @Query("limit") limit: Int = 50
    ): MessagesResponse

    @POST("api/chat/messages.php")
    suspend fun sendMessage(
        @Header("X-User-Id") userId: Int,
        @Body body: Map<String, Any>
    ): SendMessageResponse

    @Multipart
    @POST("api/chat/media-upload.php")
    suspend fun uploadMedia(
        @Header("X-User-Id") userId: Int,
        @Part file: MultipartBody.Part
    ): MediaUploadResponse

    @POST("api/notifications/register-token.php")
    suspend fun registerFcmToken(
        @Header("X-User-Id") userId: Int,
        @Body body: Map<String, String>
    ): Map<String, Any>
}

// ── Singleton ─────────────────────────────────────────────────────────────────

object ApiClient {
    val service: ChatApiService by lazy {
        val logger = HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BASIC }
        val client = OkHttpClient.Builder()
            .addInterceptor(logger)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(60, TimeUnit.SECONDS)
            .build()

        Retrofit.Builder()
            .baseUrl(BuildConfig.SERVER_URL + "/")
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ChatApiService::class.java)
    }
}
