package br.com.eventmenu.go.ui.theme

import android.graphics.Color as AndroidColor
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import br.com.eventmenu.go.data.TenantBrand

val LocalTenantBrand = staticCompositionLocalOf<TenantBrand?> { null }

private val DefaultPurple = Color(0xFF4565E9)
private val PurpleDark = Color(0xFF233C9C)
private val PurpleSoft = Color(0xFFE9EEFF)
private val DefaultInk = Color(0xFF16243A)
private val Muted = Color(0xFF66758B)
private val DefaultCanvas = Color(0xFFF4F7FB)
private val Border = Color(0xFFE0E7F0)
private val DefaultGreen = Color(0xFF0D9F88)
private val Gold = Color(0xFFF2AD45)
private val Red = Color(0xFFDA4B62)

private val EventMenuShapes = Shapes(
    small = RoundedCornerShape(10.dp),
    medium = RoundedCornerShape(18.dp),
    large = RoundedCornerShape(24.dp),
    extraLarge = RoundedCornerShape(30.dp),
)

private fun typography(ink: Color) = Typography(
    headlineLarge = TextStyle(fontSize = 30.sp, lineHeight = 36.sp, fontWeight = FontWeight.Bold, color = ink),
    headlineMedium = TextStyle(fontSize = 25.sp, lineHeight = 31.sp, fontWeight = FontWeight.Bold, color = ink),
    headlineSmall = TextStyle(fontSize = 20.sp, lineHeight = 25.sp, fontWeight = FontWeight.Bold, color = ink),
    titleLarge = TextStyle(fontSize = 18.sp, lineHeight = 23.sp, fontWeight = FontWeight.Bold, color = ink),
    titleMedium = TextStyle(fontSize = 15.sp, lineHeight = 20.sp, fontWeight = FontWeight.SemiBold, color = ink),
    bodyLarge = TextStyle(fontSize = 15.sp, lineHeight = 22.sp, color = ink),
    bodyMedium = TextStyle(fontSize = 14.sp, lineHeight = 20.sp, color = ink),
    labelLarge = TextStyle(fontSize = 14.sp, lineHeight = 18.sp, fontWeight = FontWeight.SemiBold, color = ink),
)

private fun parsed(hex: String, fallback: Color): Color = runCatching {
    Color(AndroidColor.parseColor(hex))
}.getOrDefault(fallback)

@Composable
fun EventMenuTheme(brand: TenantBrand? = null, content: @Composable () -> Unit) {
    val active = brand?.takeIf { it.applyApp }
    val primary = active?.let { parsed(it.primaryColor, DefaultPurple) } ?: DefaultPurple
    val secondary = active?.let { parsed(it.secondaryColor, DefaultGreen) } ?: DefaultGreen
    val background = active?.let { parsed(it.backgroundColor, DefaultCanvas) } ?: DefaultCanvas
    val surface = active?.let { parsed(it.surfaceColor, Color.White) } ?: Color.White
    val ink = active?.let { parsed(it.textColor, DefaultInk) } ?: DefaultInk

    val scheme = lightColorScheme(
        primary = primary,
        onPrimary = Color.White,
        primaryContainer = PurpleSoft,
        onPrimaryContainer = PurpleDark,
        secondary = secondary,
        onSecondary = Color.White,
        secondaryContainer = Color(0xFFE8F8F0),
        onSecondaryContainer = Color(0xFF0A603B),
        tertiary = Gold,
        onTertiary = Color(0xFF2A1D00),
        background = background,
        onBackground = ink,
        surface = surface,
        onSurface = ink,
        surfaceVariant = Color(0xFFF0F4FA),
        surfaceContainer = surface,
        surfaceContainerLow = background,
        onSurfaceVariant = Muted,
        outline = Border,
        error = Red,
        surfaceTint = primary,
        onError = Color.White,
    )

    CompositionLocalProvider(LocalTenantBrand provides active) {
        MaterialTheme(
            colorScheme = scheme,
            typography = typography(ink),
            shapes = EventMenuShapes,
            content = content,
        )
    }
}
