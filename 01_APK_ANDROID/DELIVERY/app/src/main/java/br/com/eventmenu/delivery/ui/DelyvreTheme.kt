package br.com.eventmenu.delivery.ui

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight

val DelyvreBrand = Color(0xFF34C3FF)
val DelyvreBrandStrong = Color(0xFF109DDA)
val DelyvreAccent = Color(0xFF65D2B8)
val DelyvreInk = Color(0xFF0B1524)
val DelyvreMuted = Color(0xFF6B7E9A)
val DelyvreCanvas = Color(0xFFF5F9FF)
val DelyvreSurfaceMuted = Color(0xFFEAF2FB)
val DelyvreLine = Color(0xFFD8E3F1)
val DelyvreSuccess = Color(0xFF1FA971)
val DelyvreWarning = Color(0xFFF5A524)

private val DelyvreLightColors = lightColorScheme(
    primary = DelyvreBrand,
    onPrimary = DelyvreInk,
    primaryContainer = Color(0xFFD5F3FF),
    onPrimaryContainer = DelyvreInk,
    secondary = Color(0xFF173252),
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFDCEBFF),
    onSecondaryContainer = DelyvreInk,
    tertiary = DelyvreAccent,
    onTertiary = DelyvreInk,
    background = DelyvreCanvas,
    onBackground = DelyvreInk,
    surface = Color.White,
    onSurface = DelyvreInk,
    surfaceVariant = DelyvreSurfaceMuted,
    onSurfaceVariant = DelyvreMuted,
    outline = Color(0xFFB7C6D9),
    outlineVariant = DelyvreLine,
    error = Color(0xFFD64563),
    onError = Color.White,
    errorContainer = Color(0xFFFFD9DF),
    onErrorContainer = Color(0xFF450914),
    scrim = Color(0xFF07111F),
)

private val DelyvreDarkColors = darkColorScheme(
    primary = Color(0xFF7FDBFF),
    onPrimary = Color(0xFF00283A),
    primaryContainer = Color(0xFF004863),
    onPrimaryContainer = Color(0xFFC6EDFF),
    secondary = Color(0xFFBED3F4),
    onSecondary = Color(0xFF253147),
    secondaryContainer = Color(0xFF3B4860),
    onSecondaryContainer = Color(0xFFDCE8FF),
    tertiary = Color(0xFF92F0D9),
    onTertiary = Color(0xFF00382B),
    background = Color(0xFF09131F),
    onBackground = Color(0xFFE8F1FF),
    surface = Color(0xFF101B2B),
    onSurface = Color(0xFFE8F1FF),
    surfaceVariant = Color(0xFF152438),
    onSurfaceVariant = Color(0xFFB8C7DB),
    outline = Color(0xFF8092AB),
    outlineVariant = Color(0xFF28384F),
    error = Color(0xFFFFB3C0),
    onError = Color(0xFF670D22),
    errorContainer = Color(0xFF8A233A),
    onErrorContainer = Color(0xFFFFD9DF),
    scrim = Color.Black,
)

private val DelyvreShapes = Shapes(
    extraSmall = RoundedCornerShape(DelyvreSpacing.xs),
    small = RoundedCornerShape(DelyvreSpacing.sm),
    medium = RoundedCornerShape(DelyvreSize.cardRadius),
    large = RoundedCornerShape(DelyvreSize.largeCardRadius),
    extraLarge = RoundedCornerShape(DelyvreSize.sheetRadius),
)

private val DelyvreTypography = Typography().let { base ->
    base.copy(
        headlineLarge = base.headlineLarge.copy(fontWeight = FontWeight.Black),
        headlineMedium = base.headlineMedium.copy(fontWeight = FontWeight.Black),
        headlineSmall = base.headlineSmall.copy(fontWeight = FontWeight.ExtraBold),
        titleLarge = base.titleLarge.copy(fontWeight = FontWeight.Bold),
        titleMedium = base.titleMedium.copy(fontWeight = FontWeight.Bold),
        titleSmall = base.titleSmall.copy(fontWeight = FontWeight.SemiBold),
        labelLarge = base.labelLarge.copy(fontWeight = FontWeight.Bold),
        labelMedium = base.labelMedium.copy(fontWeight = FontWeight.SemiBold),
    )
}

@Composable
fun DelyvreTheme(
    darkTheme: Boolean = false,
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DelyvreDarkColors else DelyvreLightColors,
        shapes = DelyvreShapes,
        typography = DelyvreTypography,
        content = content,
    )
}
