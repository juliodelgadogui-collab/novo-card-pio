<?php

declare(strict_types=1);

use EventMenu\Core\Auth;
use EventMenu\Core\Security;
use EventMenu\Services\DeliveryCustomerAuthService;
use EventMenu\Services\SmtpMailerService;

// As contas do marketplace pertencem à plataforma. Nada neste painel altera
// cadastro, confirma e-mail manualmente ou movimenta pedidos e pagamentos.
Auth::requirePermission('platform.manage');
if (!Auth::isSuperAdmin() || Auth::tenantId() !== null) {
    http_response_code(403);
    exit('Acesso restrito ao Super Administrador da plataforma.');
}

$q = mb_substr(trim((string)($_GET['q'] ?? $_POST['q'] ?? '')), 0, 100);
$status = (string)($_GET['status'] ?? $_POST['status'] ?? 'all');
if (!in_array($status, ['all','verified','pending','blocked'], true)) $status = 'all';
$page = max(1, min(100000, (int)($_GET['page'] ?? $_POST['page'] ?? 1)));
$customerId = max(0, (int)($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0));
$orderPage = max(1, min(100000, (int)($_GET['opage'] ?? 1)));
$orderId = max(0, (int)($_GET['order_id'] ?? 0));
$orderItems = [];
$viewOrder = null;
$perPage = 25;
$error = null;
$accounts = [];
$customer = null;
$orders = [];
$coupons = [];
$stats = ['total'=>0,'verified'=>0,'pending'=>0,'blocked'=>0];
$filteredTotal = 0;
$pages = 1;
$orderTotal = 0;
$orderPages = 1;

// Somente cupons gerais do marketplace já materializados em pelo menos uma
// empresa são enviados. Estes códigos NÃO são exclusivos do destinatário.
$availableCouponSql = <<<'SQL'
SELECT mc.id,mc.code,mc.name,mc.type,mc.value,mc.min_order_cents,mc.starts_at,mc.ends_at,
       mc.max_discount_cents,
       (SELECT COUNT(*) FROM marketplace_delivery_coupon_targets mt
          JOIN coupons c ON c.id=mt.coupon_id AND c.tenant_id=mt.tenant_id AND c.active=1
         WHERE mt.marketplace_coupon_id=mc.id) AS active_tenants
FROM marketplace_delivery_coupons mc
WHERE mc.active=1 AND mc.scope_type='default'
  AND (mc.starts_at IS NULL OR mc.starts_at<=CURRENT_TIMESTAMP)
  AND (mc.ends_at IS NULL OR mc.ends_at>=CURRENT_TIMESTAMP)
ORDER BY mc.name,mc.id
SQL;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    em_post_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($customerId < 1) throw new RuntimeException('Selecione um cliente válido.');
        $query = $pdo->prepare('SELECT id,name,email,status,email_verified_at,marketing_opt_in FROM delivery_customer_accounts WHERE id=? LIMIT 1');
        $query->execute([$customerId]);
        $target = $query->fetch(PDO::FETCH_ASSOC);
        if (!$target) throw new RuntimeException('Cliente não encontrado.');

        if ($action === 'resend-verification') {
            $sent = (new DeliveryCustomerAuthService())->resendVerificationFromAdmin($pdo, $customerId);
            Auth::audit($sent ? 'delivery.verification_resend_accepted' : 'delivery.verification_resend_failed', 'delivery_customer_account', (string)$customerId);
            if (!$sent) throw new RuntimeException('O SMTP não aceitou o e-mail. Confira as configurações e o log do servidor.');
            em_flash('ok','Solicitação aceita pelo servidor SMTP. A entrega na caixa postal depende do provedor do destinatário.');
        } elseif ($action === 'email-coupon') {
            if ((string)$target['status'] !== 'active' || empty($target['email_verified_at'])) {
                throw new RuntimeException('Confirme o e-mail do cliente antes de enviar cupons.');
            }
            if ((int)($target['marketing_opt_in'] ?? 0) !== 1) {
                throw new RuntimeException('O cliente não autorizou comunicações promocionais. O cupom não foi enviado.');
            }
            $couponId = max(0, (int)($_POST['coupon_id'] ?? 0));
            if ($couponId < 1) throw new RuntimeException('Selecione um cupom.');
            $cp = $pdo->prepare('SELECT * FROM ('.$availableCouponSql.') available WHERE id=? AND active_tenants>0');
            $cp->execute([$couponId]);
            $coupon = $cp->fetch(PDO::FETCH_ASSOC);
            if (!$coupon) throw new RuntimeException('Cupom inativo, fora da validade ou ainda não sincronizado com as lojas.');

            // Usa o histórico auditável existente para impedir reenvios acidentais.
            $deliveryKey = $customerId . ':' . $couponId;
            $throttle = $pdo->prepare('SELECT id FROM audit_logs WHERE action=? AND entity_type=? AND entity_id=? AND created_at>=? ORDER BY id DESC LIMIT 1');
            $throttle->execute(['delivery.coupon_email_accepted', 'delivery_coupon_recipient', $deliveryKey, gmdate('Y-m-d H:i:s', time()-86400)]);
            if ($throttle->fetchColumn()) throw new RuntimeException('Este cupom já foi enviado a este cliente nas últimas 24 horas.');

            $safeName = Security::e((string)$target['name']);
            $safeCode = Security::e((string)$coupon['code']);
            $discount = (string)$coupon['type'] === 'percent'
                ? (int)$coupon['value'].'%'
                : em_money((int)$coupon['value']);
            $minOrder = (int)$coupon['min_order_cents'] > 0 ? ' em pedidos acima de '.em_money((int)$coupon['min_order_cents']) : '';
            $validity = !empty($coupon['ends_at']) ? ' Válido até '.(string)$coupon['ends_at'].'.' : '';
            $subject = 'Seu cupom Delivery: '.(string)$coupon['code'];
            $html = '<div style="font-family:Arial,sans-serif;max-width:540px;margin:auto">'
                .'<h2>Um cupom para seu próximo pedido</h2><p>Olá, '.$safeName.'.</p>'
                .'<p>Use o código <strong style="font-size:24px">'.$safeCode.'</strong> no carrinho do Cardápio Delivery.</p>'
                .'<p>Desconto: <strong>'.Security::e($discount).'</strong>'.Security::e($minOrder).'.</p>'
                .'<p>Válido nos restaurantes participantes, conforme as regras da campanha.'.Security::e($validity).'</p>'
                .'<p>O cupom não é individual; está sujeito à disponibilidade e às regras de cada loja.</p>'
                .'</div>';
            $plain = 'Olá, '.(string)$target['name'].'. Seu cupom: '.(string)$coupon['code'].'. Desconto: '.$discount.$minOrder.'. Válido nos restaurantes participantes.'.$validity.' O código não é exclusivo deste cliente.';
            (new SmtpMailerService())->send(null, (string)$target['email'], (string)$target['name'], $subject, $html, $plain);
            Auth::audit('delivery.coupon_email_accepted', 'delivery_coupon_recipient', $deliveryKey, ['coupon_id'=>$couponId,'customer_id'=>$customerId]);
            em_flash('ok','Cupom enviado ao servidor SMTP. Confira a entrega efetiva na caixa do cliente.');
        } else {
            throw new RuntimeException('Operação não reconhecida.');
        }
    } catch (Throwable $e) {
        error_log('[cardapio-delivery-admin] '.get_class($e).': '.$e->getMessage());
        em_flash('error',$e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a ação. Consulte o log do servidor.');
    }
    em_go('delivery-customers', ['customer_id'=>$customerId,'q'=>$q,'status'=>$status,'page'=>$page]);
}

try {
    $summary = $pdo->query("SELECT COUNT(*) AS total,
      SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) AS verified,
      SUM(CASE WHEN email_verified_at IS NULL THEN 1 ELSE 0 END) AS pending,
      SUM(CASE WHEN status='blocked' THEN 1 ELSE 0 END) AS blocked
      FROM delivery_customer_accounts")->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($stats as $key => $_) $stats[$key] = (int)($summary[$key] ?? 0);

    $conditions = [];
    $params = [];
    if ($q !== '') {
        $conditions[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $pattern = '%'.$q.'%';
        $params = [$pattern,$pattern,$pattern];
    }
    if ($status === 'verified') $conditions[] = "email_verified_at IS NOT NULL AND status='active'";
    elseif ($status === 'pending') $conditions[] = 'email_verified_at IS NULL';
    elseif ($status === 'blocked') $conditions[] = "status='blocked'";
    $where = $conditions ? ' WHERE '.implode(' AND ',$conditions) : '';
    $count = $pdo->prepare('SELECT COUNT(*) FROM delivery_customer_accounts'.$where);
    $count->execute($params);
    $filteredTotal = (int)$count->fetchColumn();
    $pages = max(1,(int)ceil($filteredTotal/$perPage));
    $page = min($page,$pages);
    $offset = ($page-1)*$perPage;

    $stmt = $pdo->prepare('SELECT a.id,a.name,a.email,a.phone,a.status,a.email_verified_at,a.marketing_opt_in,a.created_at,
      (SELECT COUNT(*) FROM delivery_customer_order_links ol WHERE ol.account_id=a.id) AS orders_count
      FROM delivery_customer_accounts a'.$where.' ORDER BY a.created_at DESC,a.id DESC LIMIT ? OFFSET ?');
    $index = 1;
    foreach ($params as $value) $stmt->bindValue($index++,$value,PDO::PARAM_STR);
    $stmt->bindValue($index++,$perPage,PDO::PARAM_INT);
    $stmt->bindValue($index,$offset,PDO::PARAM_INT);
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($customerId > 0) {
        $c = $pdo->prepare('SELECT id,name,email,phone,status,email_verified_at,marketing_opt_in,created_at FROM delivery_customer_accounts WHERE id=?');
        $c->execute([$customerId]);
        $customer = $c->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($customer) {
            $co = $pdo->prepare('SELECT COUNT(*) FROM delivery_customer_order_links WHERE account_id=?');
            $co->execute([$customerId]);
            $orderTotal = (int)$co->fetchColumn();
            $orderPages = max(1,(int)ceil($orderTotal/20));
            $orderPage = min($orderPage,$orderPages);
            $query = $pdo->prepare('SELECT o.id,o.status,o.payment_status,o.channel,o.total_cents,o.created_at,
               t.name AS store_name,ou.name AS unit_name
               FROM delivery_customer_order_links ol
               JOIN orders o ON o.id=ol.order_id AND o.tenant_id=ol.tenant_id
               JOIN tenants t ON t.id=o.tenant_id
               LEFT JOIN operating_units ou ON ou.id=o.unit_id
               WHERE ol.account_id=? ORDER BY o.id DESC LIMIT ? OFFSET ?');
            $query->bindValue(1,$customerId,PDO::PARAM_INT);
            $query->bindValue(2,20,PDO::PARAM_INT);
            $query->bindValue(3,($orderPage-1)*20,PDO::PARAM_INT);
            $query->execute();
            $orders = $query->fetchAll(PDO::FETCH_ASSOC);
            if ($orderId > 0) {
                $orderQuery = $pdo->prepare('SELECT o.id,o.tenant_id,o.status,o.payment_status,o.total_cents,o.created_at FROM delivery_customer_order_links ol JOIN orders o ON o.id=ol.order_id AND o.tenant_id=ol.tenant_id WHERE ol.account_id=? AND ol.order_id=? LIMIT 1');
                $orderQuery->execute([$customerId,$orderId]);
                $viewOrder = $orderQuery->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($viewOrder) {
                    $itemQuery = $pdo->prepare('SELECT name_snapshot,quantity,unit_price_cents,total_cents FROM order_items WHERE order_id=? ORDER BY id');
                    $itemQuery->execute([$orderId]);
                    $orderItems = $itemQuery->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            if ((string)$customer['status'] === 'active' && !empty($customer['email_verified_at']) && (int)($customer['marketing_opt_in'] ?? 0) === 1) {
                $couponStmt = $pdo->query($availableCouponSql);
                $coupons = array_values(array_filter($couponStmt->fetchAll(PDO::FETCH_ASSOC), static fn(array $row): bool => (int)$row['active_tenants']>0));
            }
        }
    }
} catch (Throwable $e) {
    error_log('[cardapio-delivery-customers] '.get_class($e).': '.$e->getMessage());
    $error = 'Não foi possível consultar os dados. Confirme se as migrações do Delivery foram aplicadas.';
}

$url = static function (array $extra = []) use ($q,$status,$page): string {
    return app_url('?'.http_build_query(array_merge(['route'=>'delivery-customers','q'=>$q,'status'=>$status,'page'=>$page],$extra)));
};
$flash = em_flash();
em_header('Clientes cadastrados — Delivery','delivery-customers');
?>
<style>
.dc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.dc-card{border:1px solid var(--line,#dee3ee);border-radius:16px;padding:16px;background:var(--surface,#fff);min-width:0}
.dc-card p{margin:5px 0;color:var(--muted,#66758b)}
.dc-card .actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.dc-detail{margin-top:18px}.dc-inline{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.dc-card a,.dc-card p{overflow-wrap:anywhere}
.dc-table{overflow-x:auto}.dc-table table{min-width:580px}
@media(max-width:600px){.dc-card{padding:14px}.dc-detail .form-grid{display:grid;grid-template-columns:minmax(0,1fr)}}
</style>
<section class="page-hero">
  <div><span class="eyebrow">CARDÁPIO DELIVERY · SUPER ADM</span><h2>Clientes cadastrados</h2>
    <p>Gerencie a confirmação por e-mail, envie cupons ativos com consentimento e acompanhe pedidos realizados no aplicativo.</p></div>
  <div class="hero-actions">
    <a class="button secondary" href="<?= Security::e(app_url('?route=email-settings')) ?>">Configurar SMTP</a>
    <a class="button secondary" href="<?= Security::e(app_url('?route=marketplace-coupons')) ?>">Cupons Delivery</a>
  </div>
</section>
<?php if ($flash): ?>
  <section class="card" role="status"><strong><?= Security::e($flash[0]==='ok'?'Operação registrada':'Atenção') ?></strong><p><?= Security::e((string)$flash[1]) ?></p></section>
<?php endif; ?>
<?php if ($error): ?>
  <section class="card"><h3>Consulta indisponível</h3><p><?= Security::e($error) ?></p></section>
<?php else: ?>
  <div class="metric-grid" style="grid-template-columns:repeat(auto-fit,minmax(145px,1fr));margin-bottom:16px">
    <div class="metric-card"><span>Cadastros</span><strong><?= $stats['total'] ?></strong></div>
    <div class="metric-card"><span>Confirmados</span><strong><?= $stats['verified'] ?></strong></div>
    <div class="metric-card"><span>Pendentes</span><strong><?= $stats['pending'] ?></strong></div>
    <div class="metric-card"><span>Bloqueados</span><strong><?= $stats['blocked'] ?></strong></div>
  </div>
  <section class="card" style="margin-bottom:16px">
    <form method="get" class="form-grid" style="align-items:end">
      <input type="hidden" name="route" value="delivery-customers">
      <label class="span-2">Buscar cliente
        <input type="search" name="q" maxlength="100" value="<?= Security::e($q) ?>" placeholder="Nome, e-mail ou telefone"></label>
      <label>Situação<select name="status">
        <?php foreach (['all'=>'Todos','verified'=>'Confirmados','pending'=>'Pendentes','blocked'=>'Bloqueados'] as $key=>$label): ?>
          <option value="<?= Security::e($key) ?>"<?= em_selected($key,$status) ?>><?= Security::e($label) ?></option>
        <?php endforeach; ?></select></label>
      <button class="primary" type="submit">Pesquisar</button>
    </form>
    <p class="muted" style="margin-top:10px"><?= $filteredTotal ?> <?= $filteredTotal===1?'cliente encontrado':'clientes encontrados' ?></p>
  </section>
  <section class="dc-grid">
    <?php foreach ($accounts as $account):
      $verified = !empty($account['email_verified_at']);
      $blocked = (string)$account['status'] === 'blocked';
      $isCurrent = (int)$account['id'] === $customerId;
    ?>
    <article class="dc-card"<?= $isCurrent?' style="border-color:#678bfa"':'' ?>>
      <strong><?= Security::e((string)$account['name']) ?></strong>
      <p><?= Security::e((string)$account['email']) ?></p>
      <p><?= Security::e((string)($account['phone']?:'Sem telefone')) ?></p>
      <div class="dc-inline">
        <span class="badge status-<?= $blocked?'danger':($verified?'success':'warning') ?>"><?= $blocked?'Bloqueado':($verified?'Confirmado':'Pendente') ?></span>
        <small class="muted"><?= (int)$account['orders_count'] ?> pedido(s)</small>
      </div>
      <div class="actions"><a class="button secondary compact" href="<?= Security::e($url(['customer_id'=>(int)$account['id']])) ?>">Ver ficha e pedidos</a></div>
    </article>
    <?php endforeach; ?>
    <?php if (!$accounts): ?><p>Nenhum cadastro encontrado.</p><?php endif; ?>
  </section>
  <?php if ($pages>1): ?>
    <div class="actions" style="margin-top:16px">
      <?php if ($page>1): ?><a class="button secondary" href="<?= Security::e($url(['page'=>$page-1])) ?>">Anterior</a><?php endif; ?>
      <span class="muted">Página <?= $page ?> de <?= $pages ?></span>
      <?php if ($page<$pages): ?><a class="button secondary" href="<?= Security::e($url(['page'=>$page+1])) ?>">Próxima</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($customer):
      $confirmed = !empty($customer['email_verified_at']);
      $active = (string)$customer['status'] === 'active';
      $marketing = (int)($customer['marketing_opt_in'] ?? 0) === 1;
  ?>
  <section class="card dc-detail" id="ficha-cliente">
    <span class="eyebrow">FICHA DO CLIENTE #<?= (int)$customer['id'] ?></span>
    <h2><?= Security::e((string)$customer['name']) ?></h2>
    <p><?= Security::e((string)$customer['email']) ?> · <?= Security::e((string)($customer['phone']?:'Sem telefone')) ?></p>
    <div class="dc-inline" style="margin:12px 0">
      <span class="badge status-<?= $confirmed?'success':'warning' ?>"><?= $confirmed?'E-mail confirmado':'Aguardando confirmação' ?></span>
      <span class="badge status-<?= $marketing?'success':'muted' ?>"><?= $marketing?'Aceita promoções':'Sem autorização promocional' ?></span>
      <a href="<?= Security::e($url(['customer_id'=>0])) ?>" class="button secondary compact">Fechar ficha</a>
    </div>
    <div class="grid two" style="align-items:start">
      <div class="card" style="margin:0">
        <h3>Reenviar confirmação</h3>
        <p class="muted">Novo link válido por 24 horas. O link anterior é invalidado; mínimo de 1 minuto entre tentativas.</p>
        <?php if (!$confirmed && (string)$customer['status']!=='blocked'): ?>
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= em_csrf() ?>">
          <input type="hidden" name="action" value="resend-verification">
          <input type="hidden" name="customer_id" value="<?= (int)$customerId ?>">
          <input type="hidden" name="q" value="<?= Security::e($q) ?>">
          <input type="hidden" name="status" value="<?= Security::e($status) ?>">
          <input type="hidden" name="page" value="<?= $page ?>">
          <button class="primary" type="submit" onclick="return confirm('Reenviar confirmação para este cliente? O link anterior deixará de funcionar.')">Reenviar e-mail</button>
        </form>
        <?php else: ?><p>E-mail já confirmado ou conta bloqueada. Nenhuma ação necessária.</p><?php endif; ?>
      </div>
      <div class="card" style="margin:0">
        <h3>Enviar cupom por e-mail</h3>
        <p class="muted">Envie um código de desconto geral, já ativo no marketplace. O cupom não será exclusivo deste cliente.</p>
        <?php if (!$confirmed || !$active): ?>
          <p>Disponível apenas para clientes com conta ativa e e-mail confirmado.</p>
        <?php elseif (!$marketing): ?>
          <p>O cliente não autorizou promoções. Esta ação está desativada.</p>
        <?php elseif (!$coupons): ?>
          <p>Não há cupons gerais ativos sincronizados com as lojas. <a href="<?= Security::e(app_url('?route=marketplace-coupons')) ?>">Configure os cupons.</a></p>
        <?php else: ?>
        <form method="post" class="form-grid">
          <input type="hidden" name="_csrf" value="<?= em_csrf() ?>">
          <input type="hidden" name="action" value="email-coupon">
          <input type="hidden" name="customer_id" value="<?= (int)$customerId ?>">
          <input type="hidden" name="q" value="<?= Security::e($q) ?>">
          <input type="hidden" name="status" value="<?= Security::e($status) ?>">
          <input type="hidden" name="page" value="<?= $page ?>">
          <label class="span-2">Cupom disponível
            <select name="coupon_id" required>
            <option value="">Selecione o código</option>
            <?php foreach ($coupons as $cp): ?>
              <option value="<?= (int)$cp['id'] ?>"><?= Security::e((string)$cp['code'].' — '.(string)$cp['name']) ?></option>
            <?php endforeach; ?>
            </select>
          </label>
          <button class="primary span-2" type="submit" onclick="return confirm('Enviar o cupom selecionado por e-mail para este cliente?')">Enviar cupom</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <section class="card dc-detail" id="pedidos-do-cliente">
    <div class="section-head"><div><span class="eyebrow">HISTÓRICO DELIVERY</span><h2>Pedidos realizados · <?= $orderTotal ?></h2></div></div>
    <?php if (!$orders): ?><p class="muted">Este cliente ainda não possui pedidos vinculados à conta do aplicativo Delivery.</p>
    <?php else: ?>
    <div class="dc-table"><table class="table">
      <thead><tr><th>Pedido</th><th>Restaurante</th><th>Situação</th><th>Pagamento</th><th>Total</th><th>Data</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $order): ?>
      <tr>
        <td><strong>#<?= (int)$order['id'] ?></strong><br><a class="button secondary compact" href="<?= Security::e($url(['customer_id'=>$customerId,'order_id'=>(int)$order['id'],'opage'=>$orderPage])) ?>#detalhe-pedido">Itens</a></td>
        <td><?= Security::e((string)$order['store_name']) ?><?php if (!empty($order['unit_name'])): ?><br><small><?= Security::e((string)$order['unit_name']) ?></small><?php endif; ?></td>
        <td><?= Security::e((string)$order['status']) ?> · <?= (string)$order['channel']==='pickup'?'Retirada':'Entrega' ?></td>
        <td><?= Security::e((string)$order['payment_status']) ?></td>
        <td><?= em_money((int)$order['total_cents']) ?></td>
        <td><?= Security::e((string)$order['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
    <?php if ($orderId>0): ?>
    <div class="dc-card" id="detalhe-pedido" style="margin-top:16px">
      <?php if (!$viewOrder): ?>
        <h3>Pedido indisponível</h3><p>O pedido não pertence a este cliente ou não foi encontrado.</p>
      <?php else: ?>
        <h3>Itens do pedido #<?= (int)$viewOrder['id'] ?></h3>
        <?php if (!$orderItems): ?><p>Não há itens registrados nesse pedido.</p>
        <?php else: ?>
          <div class="dc-table"><table class="table"><thead><tr><th>Produto</th><th>Quantidade</th><th>Unitário</th><th>Total</th></tr></thead><tbody>
          <?php foreach ($orderItems as $item): ?>
            <tr><td><?= Security::e((string)$item['name_snapshot']) ?></td><td><?= Security::e((string)$item['quantity']) ?></td><td><?= em_money((int)$item['unit_price_cents']) ?></td><td><?= em_money((int)$item['total_cents']) ?></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($orderPages>1): ?>
      <div class="actions" style="margin-top:14px">
        <?php if ($orderPage>1): ?><a class="button secondary compact" href="<?= Security::e($url(['customer_id'=>$customerId,'opage'=>$orderPage-1])) ?>#pedidos-do-cliente">Anterior</a><?php endif; ?>
        <span class="muted">Pedidos: página <?= $orderPage ?> de <?= $orderPages ?></span>
        <?php if ($orderPage<$orderPages): ?><a class="button secondary compact" href="<?= Security::e($url(['customer_id'=>$customerId,'opage'=>$orderPage+1])) ?>#pedidos-do-cliente">Próxima</a><?php endif; ?>
      </div>
    <?php endif; ?>
    <p class="muted">São exibidos exclusivamente pedidos vinculados à conta Delivery. Nenhum pagamento ou status pode ser alterado nesta tela.</p>
  </section>
  <?php elseif ($customerId>0): ?>
    <section class="card dc-detail"><p>O cadastro solicitado não foi encontrado.</p></section>
  <?php endif; ?>
<?php endif; ?>
<?php em_footer(); ?>
