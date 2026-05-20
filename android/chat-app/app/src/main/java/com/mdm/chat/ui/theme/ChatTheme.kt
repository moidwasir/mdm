package com.mdm.chat.ui.theme

import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val Purple80    = Color(0xFF6366F1)
private val PurpleGrey80 = Color(0xFF4C4F6B)
private val Pink80      = Color(0xFF8B5CF6)

private val DarkColorScheme = darkColorScheme(
    primary          = Color(0xFF6366F1),
    onPrimary        = Color.White,
    primaryContainer = Color(0xFF1A1F3D),
    secondary        = Color(0xFF8B5CF6),
    background       = Color(0xFF0A0E1A),
    surface          = Color(0xFF141929),
    surfaceVariant   = Color(0xFF1A1F35),
    onBackground     = Color.White,
    onSurface        = Color.White,
    outline          = Color(0xFF2D3555)
)

@Composable
fun ChatTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = DarkColorScheme,
        typography  = Typography(),
        content     = content
    )
}
