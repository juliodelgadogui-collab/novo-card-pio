# Auditoria responsiva — 16/10/2026

## Diagnóstico no código-fonte

O servidor apresenta trechos HTML com `grid-template-columns` *inline*. Essas propriedades têm precedência sobre as regras sem `!important` do tema V8/V9.4: o layout exibe a primeira coluna quase inteira e a segunda com dezenas de pixels no celular. A mesma classe `.grid` aparece em vários arquivos de rotas PHP, incluindo `units.php`, `inventory.php`, `production.php`, `purchases.php`, `restaurant.php`, `users.php`, `coupons.php`, `guests.php`, `tickets.php` e `event-admin.php`.

Outros defeitos confirmados por inspeção de código:

- O Dashboard renderiza `<section class="quick-grid">` com 3 links `.quick-card`, mas a V9.4 não define `display:grid` nessa seção.
- O estado sem pedidos utiliza `<td colspan="7">Nenhum pedido nesta unidade.</td>`; as regras mobile anteriores aplicam o layout de células rotuladas a esse único `<td>`, produzindo uma aparência inadequada.
- A V9.4 define `.form-grid input { width:100%!important; }`, incluindo `input[type=checkbox]`, que deveria manter largura e altura próprias.
- Tabelas grandes devem rolar dentro de `.table-wrap`, sem criar a ilusão de outra coluna de 50px.

## Alteração entregue

A V9.5 **acrescenta** CSS ao arquivo já usado na V9.4, evitando mexer em roteamento, PHP, formulários, validações, SMTP e banco. CSS aplicado somente a `body.em-premium.em-tenant`.

## Validação

As quatro páginas usadas para validação são arquivos HTML de teste com trechos representativos de marcação extraídos da estrutura das rotas reais, usando os estilos do projeto. **Não são páginas de produção.** Foram verificados larguras 320, 393, 440, 768 e 1440 (20 cenários) em Chromium headless. Os resultados estão no teste local; nenhum teste de sessão autenticada foi executado.

## Pendências

Após subir o CSS ao cPanel, conferir visualmente os módulos com formulários customizados e tabelas extensas; capturas específicas de falhas remanescentes ajudam a identificar estilos de rota não cobertos. Nenhum ajuste de backend ou alteração de dados foi feito.
