package com.mdm.chat.ui.conversations

import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.*
import androidx.compose.foundation.shape.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.*
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.*
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.*
import com.mdm.chat.data.api.ApiClient
import com.mdm.chat.data.models.AuthSession
import com.mdm.chat.data.models.Conversation
import kotlinx.coroutines.*

private val BgDark   = Color(0xFF0A0E1A)
private val Surface1 = Color(0xFF141929)
private val Purple   = Color(0xFF6366F1)
private val TextMuted = Color(0xFF64748B)
private val TextSec   = Color(0xFF94A3B8)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ConversationListScreen(session: AuthSession, onConversationClick: (Int) -> Unit) {
    val scope = rememberCoroutineScope()
    var conversations by remember { mutableStateOf<List<Conversation>>(emptyList()) }
    var isLoading by remember { mutableStateOf(true) }
    var showNewChatDialog by remember { mutableStateOf(false) }

    fun refresh() {
        scope.launch {
            isLoading = true
            try {
                val resp = withContext(Dispatchers.IO) { ApiClient.service.listConversations(session.userId) }
                conversations = resp.conversations ?: emptyList()
            } finally { isLoading = false }
        }
    }

    LaunchedEffect(Unit) { refresh() }

    if (showNewChatDialog) {
        NewConversationDialog(
            session = session,
            onDismiss = { showNewChatDialog = false },
            onCreated = { convId ->
                showNewChatDialog = false
                refresh()
                onConversationClick(convId)
            }
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Messages", fontWeight = FontWeight.Bold, color = Color.White)
                        Text(session.displayName, fontSize = 12.sp, color = TextSec)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = Surface1),
                actions = {
                    IconButton(onClick = { showNewChatDialog = true }) {
                        Icon(Icons.Default.EditNote, contentDescription = "New Chat", tint = Purple)
                    }
                }
            )
        },
        floatingActionButton = {
            FloatingActionButton(onClick = { showNewChatDialog = true }, containerColor = Purple, shape = CircleShape) {
                Icon(Icons.Default.Add, contentDescription = "New conversation", tint = Color.White)
            }
        },
        containerColor = BgDark
    ) { padding ->
        if (isLoading) {
            Box(Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = Purple)
            }
        } else if (conversations.isEmpty()) {
            Box(Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Default.ChatBubbleOutline, contentDescription = null, tint = TextMuted, modifier = Modifier.size(64.dp))
                    Spacer(Modifier.height(16.dp))
                    Text("No conversations yet", color = Color.White, fontWeight = FontWeight.SemiBold, fontSize = 18.sp)
                    Text("Start a new chat using the + button", color = TextSec, fontSize = 14.sp)
                }
            }
        } else {
            LazyColumn(Modifier.fillMaxSize().padding(padding)) {
                items(conversations, key = { it.id }) { conv ->
                    ConversationItem(conv, onClick = { onConversationClick(conv.id) })
                    HorizontalDivider(color = Color(0xFF1E2540), thickness = 0.5.dp)
                }
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
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        // Avatar
        val initial = (conv.name ?: "?").first().uppercaseChar()
        Box(
            Modifier
                .size(52.dp)
                .clip(CircleShape)
                .background(Brush.linearGradient(listOf(Purple, Color(0xFF8B5CF6)))),
            contentAlignment = Alignment.Center
        ) {
            if (conv.type == "group") {
                Icon(Icons.Default.Group, contentDescription = null, tint = Color.White, modifier = Modifier.size(26.dp))
            } else {
                Text(initial.toString(), color = Color.White, fontSize = 20.sp, fontWeight = FontWeight.Bold)
            }
        }

        Spacer(Modifier.width(12.dp))

        // Text content
        Column(Modifier.weight(1f)) {
            Text(conv.name ?: "Direct Message", fontWeight = FontWeight.SemiBold, color = Color.White, fontSize = 15.sp)
            Spacer(Modifier.height(2.dp))
            Text(
                conv.lastMessage ?: "No messages",
                color = TextSec, fontSize = 13.sp,
                maxLines = 1, overflow = TextOverflow.Ellipsis
            )
        }

        Spacer(Modifier.width(8.dp))

        // Badge + time
        Column(horizontalAlignment = Alignment.End) {
            Text(formatTime(conv.lastMessageAt), color = TextMuted, fontSize = 11.sp)
            if ((conv.unreadCount) > 0) {
                Spacer(Modifier.height(4.dp))
                Box(
                    Modifier
                        .size(20.dp)
                        .clip(CircleShape)
                        .background(Purple),
                    contentAlignment = Alignment.Center
                ) {
                    Text(conv.unreadCount.toString(), color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

private fun formatTime(ts: String?): String {
    if (ts == null) return ""
    return try {
        val sdf = java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.getDefault())
        val date = sdf.parse(ts) ?: return ""
        val now = java.util.Date()
        val diff = (now.time - date.time) / 1000
        when {
            diff < 3600     -> "${diff / 60}m"
            diff < 86400    -> "${diff / 3600}h"
            else            -> java.text.SimpleDateFormat("MMM d", java.util.Locale.getDefault()).format(date)
        }
    } catch (e: Exception) { "" }
}
