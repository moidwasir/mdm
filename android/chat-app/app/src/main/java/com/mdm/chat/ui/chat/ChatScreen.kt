package com.mdm.chat.ui.chat

import android.Manifest
import android.content.ContentValues
import android.content.Context
import android.net.Uri
import android.os.Build
import android.provider.MediaStore
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.*
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.*
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.*
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.*
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.*
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.mdm.chat.BuildConfig
import com.mdm.chat.data.api.ApiClient
import com.mdm.chat.data.models.AuthSession
import com.mdm.chat.data.models.Message
import com.mdm.chat.data.websocket.ChatWebSocket
import com.mdm.chat.data.websocket.WsEvent
import kotlinx.coroutines.*
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import java.io.File
import java.io.FileOutputStream

private val BgDark   = Color(0xFF0A0E1A)
private val Surface1 = Color(0xFF141929)
private val Surface2 = Color(0xFF1A1F35)
private val Purple   = Color(0xFF6366F1)
private val TextSec  = Color(0xFF94A3B8)
private val TextMuted = Color(0xFF64748B)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatScreen(session: AuthSession, conversationId: Int, onBack: () -> Unit) {
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    val listState = rememberLazyListState()
    val keyboardController = LocalSoftwareKeyboardController.current

    var messages by remember { mutableStateOf<List<Message>>(emptyList()) }
    var inputText by remember { mutableStateOf("") }
    var convName by remember { mutableStateOf("Chat") }
    var isLoading by remember { mutableStateOf(true) }
    var typingUser by remember { mutableStateOf<String?>(null) }
    var ws by remember { mutableStateOf<ChatWebSocket?>(null) }
    var isUploadingImage by remember { mutableStateOf(false) }
    var showAttachMenu by remember { mutableStateOf(false) }
    var cameraUri by remember { mutableStateOf<Uri?>(null) }

    // Camera launcher
    val cameraLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { success ->
        if (success && cameraUri != null) {
            scope.launch { uploadAndSendImage(context, cameraUri!!, session, conversationId, ws) { isUploadingImage = it } }
        }
    }

    // Gallery picker launcher
    val galleryLauncher = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let {
            scope.launch { uploadAndSendImage(context, it, session, conversationId, ws) { isUploadingImage = it } }
        }
    }

    // Permission launcher
    val permLauncher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) galleryLauncher.launch("image/*")
    }

    // Setup WebSocket
    LaunchedEffect(Unit) {
        val socket = ChatWebSocket(session.wsUrl, session.userId, session.token)
        ws = socket
        socket.connect()

        scope.launch {
            socket.events.collect { event ->
                when (event) {
                    is WsEvent.NewMessage -> {
                        val raw = event.raw
                        val msg = Message(
                            id = raw.getInt("id"),
                            conversationId = raw.getInt("conversation_id"),
                            senderId = raw.getInt("sender_id"),
                            senderName = raw.optString("sender_name"),
                            senderAvatar = raw.optString("sender_avatar"),
                            content = raw.optString("content"),
                            messageType = raw.optString("message_type", "text"),
                            mediaUrl = raw.optString("media_url").takeIf { it.isNotBlank() },
                            replyToId = null, replyContent = null,
                            status = raw.optString("status", "sent"),
                            createdAt = raw.optString("created_at")
                        )
                        if (msg.conversationId == conversationId) {
                            messages = messages + msg
                            scope.launch { listState.animateScrollToItem(messages.size - 1) }
                            socket.markRead(conversationId, msg.id)
                        }
                    }
                    is WsEvent.Typing -> {
                        if (event.convId == conversationId && event.userId != session.userId) {
                            typingUser = if (event.isTyping) "typing..." else null
                        }
                    }
                    else -> {}
                }
            }
        }
    }

    // Load messages
    LaunchedEffect(conversationId) {
        try {
            val resp = withContext(Dispatchers.IO) { ApiClient.service.getMessages(session.userId, conversationId) }
            messages = resp.messages ?: emptyList()
            if (messages.isNotEmpty()) {
                scope.launch { listState.animateScrollToItem(messages.size - 1) }
                ws?.markRead(conversationId, messages.last().id)
            }
            val convResp = withContext(Dispatchers.IO) { ApiClient.service.getConversation(session.userId, id = conversationId) }
            convName = convResp.conversation?.name ?: "Chat"
        } catch (_: Exception) {} finally { isLoading = false }
    }

    DisposableEffect(Unit) { onDispose { ws?.disconnect() } }

    fun sendMessage() {
        val text = inputText.trim()
        if (text.isEmpty()) return
        inputText = ""
        keyboardController?.hide()
        ws?.sendMessage(conversationId, text)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            Modifier.size(36.dp).clip(CircleShape)
                                .background(Brush.linearGradient(listOf(Purple, Color(0xFF8B5CF6)))),
                            contentAlignment = Alignment.Center
                        ) { Text(convName.first().uppercaseChar().toString(), color = Color.White, fontWeight = FontWeight.Bold) }
                        Spacer(Modifier.width(10.dp))
                        Column {
                            Text(convName, fontWeight = FontWeight.SemiBold, color = Color.White, fontSize = 15.sp)
                            AnimatedVisibility(visible = typingUser != null) {
                                Text(typingUser ?: "", color = Purple, fontSize = 11.sp)
                            }
                        }
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = Color.White)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Surface1)
            )
        },
        containerColor = BgDark
    ) { padding ->
        Column(Modifier.fillMaxSize().padding(padding)) {

            // Messages list
            LazyColumn(
                state = listState,
                modifier = Modifier.weight(1f).padding(horizontal = 8.dp),
                contentPadding = PaddingValues(vertical = 8.dp)
            ) {
                if (isLoading) {
                    item { Box(Modifier.fillParentMaxWidth(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Purple, modifier = Modifier.size(32.dp)) } }
                } else {
                    items(messages, key = { it.id }) { msg ->
                        MessageBubble(msg, isOwn = msg.senderId == session.userId)
                    }
                }
            }

            // Attachment picker sheet
            AnimatedVisibility(visible = showAttachMenu, enter = slideInVertically { it }, exit = slideOutVertically { it }) {
                Surface(color = Surface1, tonalElevation = 8.dp) {
                    Row(
                        Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp),
                        horizontalArrangement = Arrangement.spacedBy(20.dp)
                    ) {
                        AttachButton(icon = Icons.Default.CameraAlt, label = "Camera", color = Color(0xFF10B981)) {
                            showAttachMenu = false
                            val uri = createCameraUri(context)
                            cameraUri = uri
                            cameraLauncher.launch(uri)
                        }
                        AttachButton(icon = Icons.Default.Image, label = "Gallery", color = Purple) {
                            showAttachMenu = false
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                galleryLauncher.launch("image/*")
                            } else {
                                permLauncher.launch(Manifest.permission.READ_EXTERNAL_STORAGE)
                            }
                        }
                    }
                }
            }

            // Input bar
            Surface(color = Surface1, tonalElevation = 4.dp) {
                Row(Modifier.fillMaxWidth().padding(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    // Attach button
                    IconButton(onClick = { showAttachMenu = !showAttachMenu }) {
                        if (isUploadingImage) {
                            CircularProgressIndicator(color = Purple, modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                        } else {
                            Icon(Icons.Default.AttachFile, contentDescription = "Attach", tint = if (showAttachMenu) Purple else TextMuted)
                        }
                    }

                    OutlinedTextField(
                        value = inputText,
                        onValueChange = {
                            inputText = it
                            ws?.sendTyping(conversationId, it.isNotEmpty())
                        },
                        modifier = Modifier.weight(1f),
                        placeholder = { Text("Message...", color = TextMuted) },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Purple,
                            unfocusedBorderColor = Color(0xFF2D3555),
                            focusedTextColor = Color.White,
                            unfocusedTextColor = Color.White,
                            cursorColor = Purple,
                        ),
                        shape = RoundedCornerShape(24.dp),
                        maxLines = 4,
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Send),
                        keyboardActions = KeyboardActions(onSend = { sendMessage() })
                    )
                    Spacer(Modifier.width(8.dp))
                    IconButton(
                        onClick = { sendMessage() },
                        modifier = Modifier.size(48.dp).clip(CircleShape)
                            .background(if (inputText.isNotBlank()) Purple else Color(0xFF2D3555))
                    ) {
                        Icon(Icons.AutoMirrored.Filled.Send, contentDescription = "Send", tint = Color.White)
                    }
                }
            }
        }
    }
}

@Composable
private fun AttachButton(icon: androidx.compose.ui.graphics.vector.ImageVector, label: String, color: Color, onClick: () -> Unit) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Box(
            Modifier.size(52.dp).clip(CircleShape).background(color.copy(alpha = 0.15f)).clickable(onClick = onClick),
            contentAlignment = Alignment.Center
        ) { Icon(icon, contentDescription = label, tint = color, modifier = Modifier.size(24.dp)) }
        Spacer(Modifier.height(4.dp))
        Text(label, color = Color(0xFF94A3B8), fontSize = 11.sp)
    }
}

@Composable
fun MessageBubble(message: Message, isOwn: Boolean) {
    val bubbleColor  = if (isOwn) Purple else Surface2
    val align        = if (isOwn) Alignment.End else Alignment.Start
    val cornerOwn    = RoundedCornerShape(18.dp, 18.dp, 4.dp, 18.dp)
    val cornerOther  = RoundedCornerShape(18.dp, 18.dp, 18.dp, 4.dp)
    val cornerShape  = if (isOwn) cornerOwn else cornerOther

    Column(Modifier.fillMaxWidth().padding(vertical = 3.dp, horizontal = 4.dp), horizontalAlignment = align) {
        if (!isOwn && message.senderName != null) {
            Text(message.senderName, color = Purple, fontSize = 11.sp,
                fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(start = 12.dp, bottom = 2.dp))
        }

        when (message.messageType) {
            "image" -> {
                // Image message bubble
                Box(
                    Modifier.clip(cornerShape).widthIn(min = 80.dp, max = 240.dp)
                        .background(bubbleColor.copy(alpha = 0.3f))
                ) {
                    val imageUrl = if (message.mediaUrl?.startsWith("http") == true)
                        message.mediaUrl
                    else
                        "${BuildConfig.SERVER_URL}/${message.mediaUrl?.trimStart('/')}"

                    AsyncImage(
                        model = ImageRequest.Builder(LocalContext.current)
                            .data(imageUrl)
                            .crossfade(true)
                            .build(),
                        contentDescription = "Image",
                        modifier = Modifier.fillMaxWidth().heightIn(max = 200.dp).clip(cornerShape),
                        contentScale = ContentScale.Crop
                    )
                }
            }
            else -> {
                // Text message bubble
                Box(
                    Modifier.clip(cornerShape).background(bubbleColor)
                        .widthIn(min = 60.dp, max = 280.dp).padding(horizontal = 14.dp, vertical = 9.dp)
                ) {
                    Text(message.content ?: "", color = Color.White, fontSize = 14.sp, lineHeight = 20.sp)
                }
            }
        }

        // Timestamp + read status
        Row(Modifier.padding(horizontal = 4.dp, vertical = 2.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(formatMessageTime(message.createdAt), color = TextMuted, fontSize = 10.sp)
            if (isOwn) {
                Spacer(Modifier.width(4.dp))
                Icon(
                    if (message.status == "read") Icons.Default.DoneAll else Icons.Default.Done,
                    contentDescription = null,
                    tint = if (message.status == "read") Purple else TextMuted,
                    modifier = Modifier.size(12.dp)
                )
            }
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

private fun createCameraUri(context: Context): Uri {
    val values = ContentValues().apply {
        put(MediaStore.Images.Media.DISPLAY_NAME, "mdm_chat_${System.currentTimeMillis()}.jpg")
        put(MediaStore.Images.Media.MIME_TYPE, "image/jpeg")
    }
    return context.contentResolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values)!!
}

private suspend fun uploadAndSendImage(
    context: Context,
    uri: Uri,
    session: AuthSession,
    conversationId: Int,
    ws: ChatWebSocket?,
    setUploading: (Boolean) -> Unit
) {
    setUploading(true)
    try {
        // Copy URI content to a temp file for Retrofit multipart upload
        val tmpFile = File(context.cacheDir, "upload_${System.currentTimeMillis()}.jpg")
        context.contentResolver.openInputStream(uri)?.use { input ->
            FileOutputStream(tmpFile).use { output -> input.copyTo(output) }
        }

        val requestBody = tmpFile.asRequestBody("image/jpeg".toMediaTypeOrNull())
        val part = MultipartBody.Part.createFormData("file", tmpFile.name, requestBody)

        val response = withContext(Dispatchers.IO) {
            ApiClient.service.uploadMedia(session.userId, part)
        }

        if (response.success && response.url != null) {
            ws?.sendMessage(conversationId, "[Image]", type = "image", mediaUrl = response.url)
        }

        tmpFile.delete()
    } catch (e: Exception) {
        android.util.Log.e("ImageUpload", "Failed: ${e.message}")
    } finally {
        setUploading(false)
    }
}

private fun formatMessageTime(ts: String): String {
    return try {
        val sdf = java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.getDefault())
        val date = sdf.parse(ts) ?: return ""
        java.text.SimpleDateFormat("HH:mm", java.util.Locale.getDefault()).format(date)
    } catch (_: Exception) { "" }
}
