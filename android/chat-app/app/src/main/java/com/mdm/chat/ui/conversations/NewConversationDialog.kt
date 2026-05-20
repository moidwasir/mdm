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
import androidx.compose.ui.unit.*
import com.mdm.chat.data.api.ApiClient
import com.mdm.chat.data.models.AuthSession
import kotlinx.coroutines.*

private val BgDark  = Color(0xFF0A0E1A)
private val Surface1 = Color(0xFF141929)
private val Surface2 = Color(0xFF1A1F35)
private val Purple  = Color(0xFF6366F1)
private val TextSec = Color(0xFF94A3B8)
private val TextMuted = Color(0xFF64748B)

@Composable
fun NewConversationDialog(
    session: AuthSession,
    onDismiss: () -> Unit,
    onCreated: (Int) -> Unit
) {
    val scope = rememberCoroutineScope()

    // Fetch users
    var users by remember { mutableStateOf<List<com.mdm.chat.data.models.User>>(emptyList()) }
    var isLoading by remember { mutableStateOf(true) }
    var selectedIds by remember { mutableStateOf(setOf<Int>()) }
    var groupName by remember { mutableStateOf("") }
    var isGroup by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var isCreating by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        try {
            // We'll fetch from conversations endpoint with users action
            val response = withContext(Dispatchers.IO) {
                ApiClient.service.listConversations(session.userId, "users")
            }
            // Parse user list from conversations response
        } catch (e: Exception) { /* show all users via direct API */ }
        finally { isLoading = false }
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = Surface1,
        title = {
            Text("New Conversation", color = Color.White, fontWeight = FontWeight.Bold)
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                // Group toggle
                Row(
                    Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(8.dp))
                        .background(Surface2)
                        .padding(12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text("Group Chat", color = Color.White, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
                        Text("Add multiple members", color = TextMuted, fontSize = 12.sp)
                    }
                    Switch(
                        checked = isGroup,
                        onCheckedChange = { isGroup = it },
                        colors = SwitchDefaults.colors(checkedTrackColor = Purple)
                    )
                }

                // Group name (only when group mode)
                if (isGroup) {
                    OutlinedTextField(
                        value = groupName,
                        onValueChange = { groupName = it },
                        label = { Text("Group Name", color = TextMuted) },
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Purple,
                            unfocusedBorderColor = Color(0xFF2D3555),
                            focusedTextColor = Color.White,
                            unfocusedTextColor = Color.White
                        ),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true
                    )
                }

                // User selection hint
                Text(
                    if (isGroup) "Select members (${selectedIds.size} selected):" else "Select a user to message:",
                    color = TextSec, fontSize = 13.sp
                )

                if (isLoading) {
                    Box(Modifier.fillMaxWidth().height(80.dp), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = Purple, modifier = Modifier.size(24.dp))
                    }
                } else if (users.isEmpty()) {
                    // Show placeholder to fetch users from server
                    Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(8.dp)).background(Surface2).padding(16.dp)) {
                        Text("💡 Users will appear once devices enroll and users are created in Admin → Users", color = TextSec, fontSize = 12.sp)
                    }
                } else {
                    LazyColumn(Modifier.heightIn(max = 200.dp)) {
                        items(users) { user ->
                            val selected = user.id in selectedIds
                            Row(
                                Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(if (selected) Color(0x266366F1) else Color.Transparent)
                                    .clickable {
                                        selectedIds = if (!isGroup) {
                                            setOf(user.id) // single select for direct
                                        } else if (selected) {
                                            selectedIds - user.id
                                        } else {
                                            selectedIds + user.id
                                        }
                                    }
                                    .padding(10.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Box(
                                    Modifier.size(32.dp).clip(CircleShape)
                                        .background(Brush.linearGradient(listOf(Purple, Color(0xFF8B5CF6)))),
                                    contentAlignment = Alignment.Center
                                ) { Text(user.displayName.first().uppercaseChar().toString(), color = Color.White, fontWeight = FontWeight.Bold, fontSize = 14.sp) }
                                Spacer(Modifier.width(10.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(user.displayName, color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                                    Text("@${user.username}", color = TextMuted, fontSize = 11.sp)
                                }
                                if (selected) Icon(Icons.Default.CheckCircle, null, tint = Purple, modifier = Modifier.size(18.dp))
                            }
                        }
                    }
                }

                error?.let { Text(it, color = Color(0xFFEF4444), fontSize = 12.sp) }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    if (selectedIds.isEmpty()) { error = "Select at least one user"; return@Button }
                    if (isGroup && groupName.isBlank()) { error = "Enter a group name"; return@Button }
                    isCreating = true
                    scope.launch {
                        try {
                            val body = buildMap<String, Any> {
                                put("type", if (isGroup) "group" else "direct")
                                put("member_ids", selectedIds.toList())
                                if (isGroup) put("name", groupName)
                            }
                            val resp = withContext(Dispatchers.IO) {
                                ApiClient.service.createConversation(session.userId, body)
                            }
                            if (resp.success && resp.conversation_id != null) {
                                onCreated(resp.conversation_id)
                            } else {
                                error = "Failed to create conversation"
                                isCreating = false
                            }
                        } catch (e: Exception) {
                            error = "Network error: ${e.message}"
                            isCreating = false
                        }
                    }
                },
                enabled = !isCreating && selectedIds.isNotEmpty(),
                colors = ButtonDefaults.buttonColors(containerColor = Purple)
            ) {
                if (isCreating) CircularProgressIndicator(Modifier.size(16.dp), color = Color.White, strokeWidth = 2.dp)
                else Text(if (isGroup) "Create Group" else "Start Chat")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel", color = TextMuted) }
        }
    )
}
