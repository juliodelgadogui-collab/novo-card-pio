<?php

declare(strict_types=1);

use EventMenu\Core\Auth;
use EventMenu\Core\Security;
use EventMenu\Services\OperatingUnitService;

$canChooseUnit = false;
foreach (['dashboard','orders.create','orders.view','orders.kitchen','tables.manage','cash.manage','production.manage','orders.delivery','settings.manage'] as $permission) {
    if (Auth::can($permission)) { $canChooseUnit = true; break; }
}
if (!$canChooseUnit) { http_response_code(403); exit('Seu perfil não pode alterar a unidade de operação.'); }

$tenantId = em_require_tenant();
$service = new OperatingUnitService();
$allowedRoutes = ['dashboard','pos','orders','restaurant','cash','kitchen','production','delivery','pickup','reports','units','events','event-admin','tickets','guests','promoters','payments','products','inventory','purchases','customers','communications','coupons','gateways','billing','users','audit','settings','settings-general','media-settings','email-settings'];
$back = (string)($_POST['back_route'] ?? $_GET['back_route'] ?? 'dashboard');
if (!in_array($back, $allowedRoutes, true)) $back = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    em_post_csrf();
    try {
        $unit = $service->selectCurrent((int)($_POST['unit_id'] ?? 0));
        em_flash('ok', 'Unidade de operação alterada para ' . (string)$unit['name'] . '.');
    } catch (Throwable $e) {
        em_flash('error', $e->getMessage());
        em_go('unit-context', ['back_route' => $back]);
    }
    em_go($back);
}

$units = $service->availableForCurrentUser();
$current = $service->current();
em_header('Escolher unidade', $back);
?>
<style>
.unit-selection-card{max-width:760px;margin:0 auto 24px;min-width:0}
.unit-selection-card form{display:grid;gap:16px}
.unit-selection-card label{display:grid;gap:8px;font-weight:700}
.unit-selection-card select{width:100%;min-width:0;min-height:48px}
.unit-selection-card .unit-help{line-height:1.6;color:var(--em-muted,#718096)}
</style>
<section class="page-hero"><div><span class="eyebrow">CONTEXTO OPERACIONAL</span><h2>Escolha a unidade</h2><p>Selecione a loja ou unidade em que você vai trabalhar. O PDV, as mesas, os pedidos e a cozinha usarão essa unidade.</p></div></section>
<section class="card unit-selection-card">
<?php if (!$units): ?>
    <h2>Nenhuma unidade disponível</h2>
    <p>Seu usuário não possui acesso a uma unidade ativa. Peça ao administrador para liberar uma unidade em Equipe e permissões.</p>
    <?php if (Auth::can('settings.manage')): ?><a class="button primary" href="<?= Security::e(app_url('?route=units')) ?>">Gerenciar unidades</a><?php endif; ?>
<?php else: ?>
    <form method="post" action="<?= Security::e(app_url('?route=unit-context')) ?>">
        <input type="hidden" name="_csrf" value="<?= em_csrf() ?>">
        <input type="hidden" name="back_route" value="<?= Security::e($back) ?>">
        <label>Unidade de operação
            <select name="unit_id" required>
                <?php if (!$current): ?><option value="" selected disabled>Selecione uma unidade</option><?php endif; ?>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int)$unit['id'] ?>"<?= $current && (int)$current['id'] === (int)$unit['id'] ? ' selected' : '' ?>><?= Security::e((string)$unit['name']) ?><?= !empty($unit['address']) ? ' · ' . Security::e((string)$unit['address']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <p class="unit-help">A unidade escolhida ficará ativa nesta sessão. Se houver caixa ou turno aberto em outra unidade, encerre-o antes de trocar.</p>
        <button class="primary" type="submit">Confirmar unidade e continuar</button>
    </form>
<?php endif; ?>
</section>
<?php em_footer();
