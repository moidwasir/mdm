package com.mdm.chat.ui.auth

import android.content.Context
import android.telephony.TelephonyManager
import android.util.Log
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.*
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.*
import com.mdm.chat.data.api.ApiClient
import com.mdm.chat.data.models.AuthSession
import kotlinx.coroutines.*

private val Purple = Color(0xFF6366F1)
private val BgDark = Color(0xFF0A0E1A)
private val Surface1 = Color(0xFF141929)

@Composable
fun AuthScreen(onAuthenticated: (AuthSession) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var isLoading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var statusText by remember { mutableStateOf("Connecting to MDM server...") }
    var showBypassDialog by remember { mutableStateOf(false) }

    // Auto-login on launch using IMEI
    LaunchedEffect(Unit) {
        scope.launch {
            try {
                val imei = getImei(context)
                statusText = "Authenticating device..."

                val response = withContext(Dispatchers.IO) {
                    ApiClient.service.authenticate(mapOf("imei" to imei))
                }

                if (response.success && response.token != null) {
                    onAuthenticated(AuthSession(
                        token = response.token,
                        userId = response.user_id ?: 0,
                        username = response.username ?: "",
                        displayName = response.display_name ?: "User",
                        avatar = response.avatar,
                        deviceId = response.device_id ?: 0,
                        wsUrl = response.ws_url ?: "ws://192.168.18.59:8080"
                    ))
                } else {
                    error = "Device not enrolled. Contact your administrator."
                    isLoading = false
                }
            } catch (e: Exception) {
                Log.e("Auth", "Auth failed: ${e.message}")
                error = "Cannot connect to server. Check network connection."
                isLoading = false
            }
        }
    }

    if (showBypassDialog) {
        AlertDialog(
            onDismissRequest = { showBypassDialog = false },
            title = { Text("Emulator Bypass Mode", color = Color.White) },
            text = {
                Column {
                    Text("Select a user to log in as without IMEI verification:", color = Color(0xFF94A3B8))
                    Spacer(Modifier.height(16.dp))
                    Button(
                        onClick = {
                            showBypassDialog = false
                            onAuthenticated(AuthSession(
                                token = "debug_token_1",
                                userId = 1,
                                username = "user1",
                                displayName = "User One",
                                avatar = null,
                                deviceId = 1,
                                wsUrl = "ws://192.168.18.59:8080"
                            ))
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Purple),
                        modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)
                    ) { Text("Log in as User 1") }
                    Button(
                        onClick = {
                            showBypassDialog = false
                            onAuthenticated(AuthSession(
                                token = "debug_token_2",
                                userId = 2,
                                username = "user2",
                                displayName = "User Two",
                                avatar = null,
                                deviceId = 2,
                                wsUrl = "ws://192.168.18.59:8080"
                            ))
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981)),
                        modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)
                    ) { Text("Log in as User 2") }
                }
            },
            confirmButton = {},
            dismissButton = {
                TextButton(onClick = { showBypassDialog = false }) { Text("Cancel") }
            },
            containerColor = Surface1
        )
    }

    Box(
        Modifier
            .fillMaxSize()
            .background(BgDark),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
            modifier = Modifier.padding(32.dp)
        ) {
            // App Icon with clickable hidden debug bypass
            Box(
                Modifier
                    .size(96.dp)
                    .clip(RoundedCornerShape(28.dp))
                    .background(Brush.linearGradient(listOf(Purple, Color(0xFF8B5CF6))))
                    .clickable { showBypassDialog = true }, // Tap 3 times or just click to bypass
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Default.ChatBubble, contentDescription = null, tint = Color.White, modifier = Modifier.size(48.dp))
            }

            Spacer(Modifier.height(32.dp))

            Text("MDM Chat", fontSize = 28.sp, fontWeight = FontWeight.Bold, color = Color.White)
            Text("Secure Enterprise Messaging", fontSize = 14.sp, color = Color(0xFF64748B), modifier = Modifier.padding(top = 4.dp))

            Spacer(Modifier.height(48.dp))

            AnimatedVisibility(visible = isLoading) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    CircularProgressIndicator(color = Purple, modifier = Modifier.size(40.dp), strokeWidth = 3.dp)
                    Spacer(Modifier.height(16.dp))
                    Text(statusText, color = Color(0xFF94A3B8), fontSize = 14.sp, textAlign = TextAlign.Center)
                }
            }

            AnimatedVisibility(visible = error != null) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Box(
                        Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(Color(0x1AEF4444))
                            .padding(16.dp)
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Error, contentDescription = null, tint = Color(0xFFEF4444), modifier = Modifier.size(20.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(error ?: "", color = Color(0xFFEF4444), fontSize = 14.sp)
                        }
                    }
                    Spacer(Modifier.height(16.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        Button(
                            onClick = { isLoading = true; error = null },
                            colors = ButtonDefaults.buttonColors(containerColor = Purple),
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.weight(1f).height(50.dp)
                        ) { Text("Retry", fontWeight = FontWeight.SemiBold) }
                        Button(
                            onClick = { showBypassDialog = true },
                            colors = ButtonDefaults.buttonColors(containerColor = Surface1),
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.weight(1f).height(50.dp)
                        ) { Text("Emulator Bypass", fontWeight = FontWeight.SemiBold, color = Purple) }
                    }
                }
            }
        }
    }
}

@Suppress("MissingPermission")
private fun getImei(context: Context): String {
    val raw = try {
        val tm = context.getSystemService(Context.TELEPHONY_SERVICE) as TelephonyManager
        @Suppress("DEPRECATION")
        val id = tm.deviceId
        if (!id.isNullOrEmpty() && id != "unknown") {
            id
        } else {
            @Suppress("DEPRECATION")
            val serial = android.os.Build.SERIAL
            if (!serial.isNullOrEmpty() && serial != "unknown") serial else ""
        }
    } catch (e: Exception) {
        @Suppress("DEPRECATION")
        val serial = try { android.os.Build.SERIAL } catch (se: Exception) { "" }
        if (!serial.isNullOrEmpty() && serial != "unknown") serial else ""
    }

    // If raw is a valid 15-digit numeric string, return it
    if (raw.matches(Regex("^\\d{15}$"))) {
        return raw
    }

    // Otherwise, construct a stable 15-digit dummy IMEI starting with 12345
    val hash = raw.hashCode().toString().replace("-", "")
    val paddedHash = hash.padEnd(10, '0').take(10)
    return "12345$paddedHash"
}
