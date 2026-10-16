package br.com.eventmenu.go.ui.screens

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import br.com.eventmenu.go.data.KitchenTicket
import kotlinx.coroutines.delay
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun KitchenScreen(tickets: List<KitchenTicket>, onRefresh: () -> Unit, onStatus: (Int, String) -> Unit) {
    LaunchedEffect(Unit) { while (true) { delay(15_000); onRefresh() } }
    val fresh = tickets.count { it.status == "confirmed" }
    val cooking = tickets.count { it.status == "preparing" }
    Column(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background).padding(16.dp), verticalArrangement = Arrangement.spacedBy(15.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text("PRODUÇÃO", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant, fontWeight = FontWeight.Bold)
                Text("Cozinha", style = MaterialTheme.typography.headlineMedium)
            }
            TextButton(onClick = onRefresh) {
                Icon(Icons.Default.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                Text("Atualizar")
            }
        }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            KitchenSummaryChip("Novos", fresh, Color(0xFFFFF2E0), Color(0xFFAE661E), Modifier.weight(1f))
            KitchenSummaryChip("Em preparo", cooking, Color(0xFFE8EEFF), Color(0xFF3D58B6), Modifier.weight(1f))
        }
        if (tickets.isEmpty()) {
            Surface(shape = RoundedCornerShape(20.dp), color = Color.White, border = BorderStroke(1.dp, Color(0xFFE4EAF3))) {
                Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(5.dp)) {
                    Text("Tudo em dia", style = MaterialTheme.typography.titleLarge)
                    Text("Não há pedidos aguardando preparo.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
        LazyVerticalGrid(
            columns = GridCells.Adaptive(290.dp),
            modifier = Modifier.fillMaxSize(),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            items(tickets, key = { it.id }) { ticket ->
                val isNew = ticket.status == "confirmed"
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(20.dp),
                    border = BorderStroke(1.dp, Color(0xFFE4EAF3)),
                    colors = CardDefaults.cardColors(containerColor = Color.White),
                ) {
                    Column(Modifier.padding(17.dp), verticalArrangement = Arrangement.spacedBy(11.dp)) {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Text("Pedido #${ticket.id}", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                            Text(elapsed(ticket.createdAt), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        Surface(shape = RoundedCornerShape(10.dp), color = if (isNew) Color(0xFFFFF1DF) else Color(0xFFEAF0FF)) {
                            Text(if (isNew) "AGUARDANDO PREPARO" else "EM PREPARO", Modifier.padding(horizontal = 10.dp, vertical = 7.dp), color = if (isNew) Color(0xFFAB5F1B) else Color(0xFF3759BA), style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold)
                        }
                        val origin = ticket.tableName.ifBlank { ticket.customerName.ifBlank { ticket.channel.uppercase() } }
                        Text(origin, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        ticket.items.forEach { item ->
                            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                                Text("${formatQty(item.quantity)}× ${item.name}", style = MaterialTheme.typography.titleMedium)
                                if (item.notes.isNotBlank()) Text("Obs.: ${item.notes}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                        if (ticket.notes.isNotBlank()) Text("PEDIDO: ${ticket.notes}", color = MaterialTheme.colorScheme.error, fontWeight = FontWeight.SemiBold)
                        Button(onClick = { onStatus(ticket.id, if (isNew) "preparing" else "ready") }, modifier = Modifier.fillMaxWidth()) {
                            Text(if (isNew) "Iniciar preparo" else "Marcar como pronto")
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun KitchenSummaryChip(label: String, count: Int, background: Color, foreground: Color, modifier: Modifier) {
    Surface(modifier = modifier, color = background, shape = RoundedCornerShape(16.dp)) {
        Row(Modifier.padding(13.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(count.toString(), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold, color = foreground)
            Text(label, style = MaterialTheme.typography.bodyMedium, color = foreground)
        }
    }
}

private fun elapsed(value: String): String {
    val created = runCatching { SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).parse(value)?.time }.getOrNull() ?: return "—"
    val seconds = ((Date().time - created) / 1000).coerceAtLeast(0)
    return "%02d:%02d".format(seconds / 60, seconds % 60)
}
private fun formatQty(q: Double) = if (q % 1.0 == 0.0) q.toInt().toString() else q.toString()
