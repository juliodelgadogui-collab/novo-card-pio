# Cardápio / EventMenu — Correções integradas V9.13

## Correções incluídas

- WhatsApp: opção `5 - Comprar ingresso para evento`, seleção de evento/lote/quantidade, reserva real no sistema e link para o checkout oficial.
- WhatsApp: após a confirmação do pagamento, o fluxo existente de fila envia o ingresso individual; URLs de mídia legadas relativas/HTTP são normalizadas para URLs públicas HTTPS quando possível.
- WhatsApp: tela de diagnóstico da fila com status, tentativas e erro retornado pelo Connect; mensagens que falharam definitivamente podem ser reenfileiradas manualmente.
- WhatsApp / cardápio: produtos ativos sem categoria ativa aparecem na categoria `Outros`, evitando a mensagem incorreta de que não há categorias quando existem produtos sem classificação.
- Ingressos: o botão do site para salvar/compartilhar a imagem usa o mesmo endpoint PNG utilizado pelo WhatsApp, para manter arte, dados, código e QR individual consistentes.
- Capas: URLs de mídia são absolutas; imagens locais antigas armazenadas por URL relativa continuam sendo carregadas na imagem do ingresso. O formulário de eventos informa formatos e dimensões recomendadas.
- Estações de produção: quando uma unidade ainda não tem nenhuma estação cadastrada, o sistema prepara estações padrão (Cozinha, Bar, Chapa e Fritadeira) para que apareçam na configuração dos produtos.
- Endereço público: o cardápio prioriza o endereço físico da unidade e usa os campos de endereço disponíveis como fallback; o cardápio do evento também exibe o endereço cadastrado do evento.
- Publicidade: adiciona o acesso `Publicidade nos cupons` ao menu do ADM Geral, apontando para a tela administrativa existente.
- CSS: reforça a responsividade de dashboards, cards, tabelas, formulários, menus, cardápio público e pedido no bar em telas pequenas.
- URLs públicas: `app_absolute_url()` passa a gerar URLs absolutas quando `APP_URL` não estiver configurado e prioriza HTTPS em domínios públicos.
- Mantidas as correções anteriores incluídas nos patches V9.1–V9.12, incluindo as correções da Cozinha/KDS.

## Banco de dados

Não foi adicionada migração de banco nesta versão. O fluxo de ingressos usa as tabelas existentes; o estado da conversa é guardado em `whatsapp_conversations.context_json`.

## Validação feita

- `php -l` passou nos 269 arquivos PHP de `app`, `public`, `src` e `scripts`.
- Consultas SQL de teste foram executadas em SQLite de teste para verificar listagem de eventos/lotes e o fallback `Outros`.
- A entrega real de WhatsApp, pagamentos e upload no servidor ainda precisa ser validada após a implantação, pois depende do banco, das extensões PHP e da sessão real do EventMenu Connect.

## Implantação segura

1. Faça backup da pasta atual do site e do banco de dados.
2. Extraia o pacote na raiz da aplicação (`~/go.gestao2.store/1`), mantendo os caminhos `app/`, `public/`, `src/`, `assets/` e `vendor/`.
3. Preserve o `.env` real, o banco SQLite de produção e os arquivos existentes em `storage/media/`. O pacote não inclui banco de produção nem `.env` real.
4. Confira se o domínio usa HTTPS e se `APP_URL` está definido com a URL pública correta, preferencialmente `https://...`.
5. Abra o módulo WhatsApp e confira a seção **Fila e diagnóstico** antes de reenviar mensagens com falha.
