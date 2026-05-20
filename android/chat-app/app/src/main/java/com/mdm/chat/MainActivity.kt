package com.mdm.chat

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.mdm.chat.data.models.AuthSession
import com.mdm.chat.ui.auth.AuthScreen
import com.mdm.chat.ui.chat.ChatScreen
import com.mdm.chat.ui.conversations.ConversationListScreen
import com.mdm.chat.ui.theme.ChatTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            ChatTheme {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    ChatApp()
                }
            }
        }
    }
}

@Composable
fun ChatApp() {
    val navController = rememberNavController()
    var session by remember { mutableStateOf<AuthSession?>(null) }

    NavHost(navController = navController, startDestination = "auth") {
        composable("auth") {
            AuthScreen(
                onAuthenticated = { s ->
                    session = s
                    navController.navigate("conversations") { popUpTo("auth") { inclusive = true } }
                }
            )
        }
        composable("conversations") {
            session?.let { s ->
                ConversationListScreen(
                    session = s,
                    onConversationClick = { convId ->
                        navController.navigate("chat/$convId")
                    }
                )
            }
        }
        composable("chat/{convId}") { backStack ->
            val convId = backStack.arguments?.getString("convId")?.toIntOrNull() ?: return@composable
            session?.let { s ->
                ChatScreen(
                    session = s,
                    conversationId = convId,
                    onBack = { navController.popBackStack() }
                )
            }
        }
    }
}
