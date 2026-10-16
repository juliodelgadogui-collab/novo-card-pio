package br.com.eventmenu.go.ui.screens

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.Assessment
import androidx.compose.material.icons.filled.ConfirmationNumber
import androidx.compose.material.icons.filled.DeliveryDining
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material.icons.filled.MoreHoriz
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Restaurant
import androidx.compose.material.icons.filled.TableRestaurant
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import br.com.eventmenu.go.AppScreen
import br.com.eventmenu.go.GoState
import br.com.eventmenu.go.data.AppMode
import br.com.eventmenu.go.ui.theme.LocalTenantBrand

private val GoNavy = Color(0xFF15243D)
private val GoMuted = Color(0xFF64758A)
private val GoOutline = Color(0xFFE4EAF3)
private val GoMint = Color(0xFFB7F5E6)

private data class DashboardShortcutItem(val icon: ImageVector, val label: String, val screen: AppScreen)

@Composable
fun RoleDashboardScreen(
    state: GoState,
    onNavigate: (AppScreen) -> Unit,
    onScan: () -> Unit,
    onRefresh: () -> Unit,
) {
    val session = state.session ?: return
    val permissions = session.permissions
    val mode = state.mode ?: return
    val shift = state.workShift
    val brand = LocalTenantBrand.current
    val tenant = brand?.displayName?.takeIf { it.isNotBlank() } ?: session.user.tenantName.ifBlank { "Sua empresa" }
    val unit = dashboardUnitName(shift?.unitName)
    val shortcuts = buildList {
        if ("orders_create" in permissions) add(DashboardShortcutItem(Icons.Default.PointOfSale, "Nova venda", AppScreen.POS))
        if ("orders_kitchen" in permissions) add(DashboardShortcutItem(Icons.Default.Restaurant, "Cozinha", AppScreen.KITCHEN))
        if ("orders_view" in permissions || "orders_manage" in permissions) add(DashboardShortcutItem(Icons.Default.ReceiptLong, "Pedidos", AppScreen.ORDERS))
        if ("tables" in permissions) add(DashboardShortcutItem(Icons.Default.TableRestaurant, "Mesas", AppScreen.TABLES))
        if ("cash" in permissions) add(DashboardShortcutItem(Icons.Default.AccountBalanceWallet, "Caixa", AppScreen.CASH))
        if ("cancellation_approve" in permissions || "discount_approve" in permissions || "reports" in permissions) add(DashboardShortcutItem(Icons.Default.Assessment, "Gestão", AppScreen.MANAGER))
        if ("orders_dispatch" in permissions || "delivery_assign" in permissions) add(DashboardShortcutItem(Icons.Default.LocalShipping, "Saída", AppScreen.DISPATCH))
        if (mode == AppMode.DELIVERY || "orders_delivery" in permissions) add(DashboardShortcutItem(Icons.Default.DeliveryDining, "Entregas", AppScreen.DELIVERY))
        if (mode == AppMode.EVENTS || "events" in permissions || "tickets" in permissions) add(DashboardShortcutItem(Icons.Default.ConfirmationNumber, "Eventos", AppScreen.EVENTS))
        add(DashboardShortcutItem(Icons.Default.MoreHoriz, "Mais", AppScreen.PROFILE))
    }.distinctBy { it.label }.take(8)

    LazyColumn(
        modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background).padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        item { Spacer(Modifier.height(4.dp)) }
        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                    Text("PAINEL OPERACIONAL", color = GoMuted, style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold)
                    Text("Olá, ${firstName(session.user.name)} 👋", style = MaterialTheme.typography.headlineMedium)
                }
                Surface(shape = RoundedCornerShape(14.dp), color = Color.White, border = BorderStroke(1.dp, GoOutline)) {
                    Text("GO", Modifier.padding(horizontal = 14.dp, vertical = 11.dp), color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.ExtraBold)
                }
            }
        }
        item { OperationHero(tenant, unit, shift?.startedAt, mode.label, onScan, onRefresh) }
        item { DashboardSection("Acesso rápido", "Sua operação em um toque") }
        shortcuts.chunked(2).forEach { row ->
            item {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    row.forEach { shortcut -> DashboardShortcut(shortcut, Modifier.weight(1f)) { onNavigate(shortcut.screen) } }
                    if (row.size == 1) Spacer(Modifier.weight(1f))
                }
            }
        }
        item { DashboardSection("Visão geral", "Indicadores da operação") }

        when (mode) {
            AppMode.DELIVERY -> {
                val deliveries = state.orders.filter { it.channel == "delivery" && it.status !in setOf("completed", "cancelled") }
                val ready = deliveries.count { it.status == "ready" }
                val route = deliveries.count { it.status == "out_for_delivery" }
                val paid = deliveries.filter { it.paymentStatus == "paid" }.sumOf { it.totalCents }
                item { MetricRow("Para retirar", ready.toString(), "Em rota", route.toString()) }
                item { MetricRow("Recebido", dashboardMoney(paid), "A repassar", dashboardMoney(state.deliveryCash?.outstandingCents ?: 0), emphasizeB = true) }
                item { Button(onClick = { onNavigate(AppScreen.DELIVERY) }, modifier = Modifier.fillMaxWidth()) { Text("Abrir minhas entregas") } }
            }
            AppMode.EVENTS -> {
                val event = state.events.firstOrNull { it.id == state.selectedEventId } ?: state.events.firstOrNull()
                if (event == null) item { DashboardCard("Nenhum evento disponível", "Atualize a operação ou confirme seu acesso.") }
                else {
                    item { DashboardCard(event.name, event.venue.ifBlank { "Evento selecionado" }) }
                    item { MetricRow("Ingressos", event.ticketsPaid.toString(), "Check-ins", event.ticketsCheckedIn.toString()) }
                    item { MetricRow("Convidados", event.guestsPending.toString(), "Entraram", event.guestsCheckedIn.toString()) }
                    item { MetricRow("Ingressos", dashboardMoney(event.ticketRevenueCents), "Bar", dashboardMoney(event.barRevenueCents), emphasizeA = true, emphasizeB = true) }
                }
                if ("tickets" in permissions || "guests" in permissions) item { Button(onClick = onScan, modifier = Modifier.fillMaxWidth()) { Text("Ler ingresso ou convidado") } }
            }
            AppMode.PAY -> {
                val cash = state.cashSummary
                item { DashboardStatusCard("Caixa", if (state.cashOpen) "Aberto" else "Fechado", state.cashOpen) }
                item { MetricRow("Saldo esperado", dashboardMoney(cash?.expectedCashCents ?: 0), "Movimentos", (cash?.movements?.size ?: 0).toString(), emphasizeA = true) }
                val pix = cash?.digital?.filter { it.provider.contains("pag", true) || it.provider.contains("pix", true) }?.sumOf { it.totalCents } ?: 0
                val cards = cash?.digital?.filterNot { it.provider.contains("pix", true) }?.sumOf { it.totalCents } ?: 0
                item { MetricRow("PIX / digital", dashboardMoney(pix), "Cartões", dashboardMoney(cards)) }
            }
            AppMode.OPERATION -> {
                val manager = state.managerOverview
                val todayRevenue = manager?.revenueTodayCents ?: state.orders.filter { it.paymentStatus == "paid" }.sumOf { it.totalCents }
                val activeOrders = manager?.ordersNow ?: state.orders.count { it.status !in setOf("completed", "cancelled") }
                val pendingPayments = manager?.pendingPayments ?: state.orders.count { it.paymentStatus != "paid" && it.status !in setOf("completed", "cancelled") }
                val avgTicket = if (state.orders.isNotEmpty()) todayRevenue / state.orders.size else 0
                item { MetricRow("Vendas", dashboardMoney(todayRevenue), "Pedidos ativos", activeOrders.toString(), emphasizeA = true) }
                item { MetricRow("Ticket médio", dashboardMoney(avgTicket), "A receber", pendingPayments.toString()) }
                if ("reports" in permissions && manager != null) item { MetricRow("Cozinha atrasada", manager.kitchenDelayed.toString(), "Entregadores", manager.deliveryOnline.toString()) }
            }
        }
        item { Spacer(Modifier.height(18.dp)) }
    }
}

@Composable
private fun OperationHero(tenant: String, unit: String, startedAt: String?, mode: String, onScan: () -> Unit, onRefresh: () -> Unit) {
    Column(
        Modifier.fillMaxWidth()
            .background(Brush.linearGradient(listOf(GoNavy, Color(0xFF24466E))), RoundedCornerShape(26.dp))
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Box(Modifier.size(8.dp).background(GoMint, CircleShape))
            Text("TURNO EM OPERAÇÃO", color = GoMint, style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold)
        }
        Text(tenant, color = Color.White, style = MaterialTheme.typography.headlineSmall)
        Text("$unit  ·  $mode", color = Color(0xFFCEDCEB), style = MaterialTheme.typography.bodyMedium)
        if (!startedAt.isNullOrBlank()) Text("Iniciado às ${dashboardTime(startedAt)}", color = Color(0xFFCEDCEB), style = MaterialTheme.typography.bodyMedium)
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
            Button(
                onClick = onScan,
                modifier = Modifier.weight(1f),
                colors = ButtonDefaults.buttonColors(containerColor = Color.White, contentColor = GoNavy),
            ) { Icon(Icons.Default.QrCodeScanner, contentDescription = null, modifier = Modifier.size(18.dp)); Spacer(Modifier.size(7.dp)); Text("Ler QR") }
            TextButton(onClick = onRefresh, colors = ButtonDefaults.textButtonColors(contentColor = Color.White)) {
                Icon(Icons.Default.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.size(5.dp))
                Text("Atualizar")
            }
        }
    }
}

@Composable
private fun DashboardSection(title: String, subtitle: String) {
    Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
        Text(title, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
        Text(subtitle, style = MaterialTheme.typography.bodyMedium, color = GoMuted)
    }
}

@Composable
private fun DashboardShortcut(item: DashboardShortcutItem, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Card(
        onClick = onClick,
        modifier = modifier.heightIn(min = 106.dp),
        shape = RoundedCornerShape(20.dp),
        border = BorderStroke(1.dp, GoOutline),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Surface(color = MaterialTheme.colorScheme.primaryContainer, shape = RoundedCornerShape(12.dp)) {
                Icon(item.icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(9.dp).size(22.dp))
            }
            Text(item.label, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
        }
    }
}

@Composable
private fun MetricRow(labelA: String, valueA: String, labelB: String, valueB: String, emphasizeA: Boolean = false, emphasizeB: Boolean = false) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        DashboardMetric(labelA, valueA, Modifier.weight(1f), emphasizeA)
        DashboardMetric(labelB, valueB, Modifier.weight(1f), emphasizeB)
    }
}

@Composable
private fun DashboardMetric(label: String, value: String, modifier: Modifier = Modifier, emphasized: Boolean = false) {
    Card(modifier.heightIn(min = 105.dp), shape = RoundedCornerShape(20.dp), border = BorderStroke(1.dp, GoOutline), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(Modifier.padding(15.dp), verticalArrangement = Arrangement.spacedBy(9.dp)) {
            Text(label, color = GoMuted, style = MaterialTheme.typography.bodyMedium, maxLines = 2)
            Text(value, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold, color = if (emphasized) MaterialTheme.colorScheme.primary else GoNavy)
        }
    }
}

@Composable
private fun DashboardCard(title: String, text: String) {
    Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(20.dp), border = BorderStroke(1.dp, GoOutline), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(Modifier.padding(17.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(title, style = MaterialTheme.typography.titleLarge)
            Text(text, color = GoMuted)
        }
    }
}

@Composable
private fun DashboardStatusCard(title: String, status: String, active: Boolean) {
    Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(20.dp), border = BorderStroke(1.dp, GoOutline), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Row(Modifier.fillMaxWidth().padding(17.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text(title, style = MaterialTheme.typography.titleMedium)
            Surface(color = if (active) Color(0xFFE5F8EE) else Color(0xFFFFF2E8), shape = RoundedCornerShape(12.dp)) {
                Text(status, Modifier.padding(horizontal = 12.dp, vertical = 7.dp), color = if (active) Color(0xFF137958) else Color(0xFFA25420), fontWeight = FontWeight.Bold)
            }
        }
    }
}

private fun dashboardUnitName(raw: String?): String { val clean = raw?.trim().orEmpty(); return if (clean.isBlank() || clean.equals("null", true)) "Unidade principal" else clean }
private fun firstName(name: String): String = name.trim().substringBefore(' ').ifBlank { "equipe" }
private fun dashboardTime(value: String): String = value.trim().replace('T', ' ').substringAfter(' ', value).take(5).ifBlank { value }
private fun dashboardMoney(cents: Int) = "R$ %.2f".format(cents / 100.0).replace('.', ',')
