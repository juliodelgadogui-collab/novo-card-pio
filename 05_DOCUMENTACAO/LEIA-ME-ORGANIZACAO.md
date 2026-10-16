# CARDÁPIO / EVENTMENU — PROJETOS ORGANIZADOS (16/10/2026)

## Estrutura principal

- `01_APK_ANDROID/GO/`: código-fonte Android EventMenu GO, da branch `refactor-login-design-11416550800703835738` (`branch_145`).
- `01_APK_ANDROID/DELIVERY/`: código-fonte Android Delivery / Delyvre, da branch `dev/delyvre` (`branch_013`), que tem etapas adicionais de carrinho, resiliência, favoritos, push e testes.
- `01_APK_ANDROID/CONNECT/`: código-fonte Android EventMenu Connect, da `branch_145` (com arquivos para runtime Node/Baileys).
- `02_WINDOWS/desktop/`: Windows EventMenu Desktop e EventMenu WhatsApp Connect (.NET 8 / WPF), da `branch_145`.
- `02_WINDOWS/integrations/whatsapp-worker/`: worker Node usado pelo pacote Windows, da `branch_145`.
- `03_SERVIDOR/PHP_SQLITE_V9/`: pacote do servidor PHP com painel Super ADM SaaS V9 e dependências Composer, criado anteriormente a partir do Cardápio 16/10. Preserva o instalador, APIs, migrações e proteções.
- `03_SERVIDOR/PORTAL_APKS/`: código do portal de distribuição APK separado da instalação PHP; da `branch_145`.
- `03_SERVIDOR/SCRIPTS_FONTE/`: scripts de manutenção, teste e empacotamento; da `branch_145`.
- `04_REFERENCIAS_DE_BRANCHES/`: revisões alternativas mantidas intactas para revisão antes de qualquer merge. NÃO são projetos para instalar junto.
- `05_DOCUMENTACAO/`: este documento e CSV com as branches originais, commits e componentes.

## Seleção e cautelas

A `branch_145` é uma base INTEGRADA, contendo GO + Connect + Windows e uma edição do Delivery. 
Por outro lado, `branch_013` (dev/delyvre) tem 49 arquivos do Delivery, contra 32 na branch_145, incluindo etapas de resiliência e testes. A escolha da branch_013 é por amplitude de implementação, NÃO por prova de compilação ou de compatibilidade com V9.
O Delivery integrado da branch_145 foi preservado em REFERENCIAS para comparação. Não misture arquivos entre as duas revisões sem resolver conflitos de tipos, APIs e fluxos de pagamento.
GO branch_154 e Desktop branch_015 contêm arquivos extras ausentes da linha integrada, mas também alterações divergentes; por isso não foram mesclados automaticamente.
A presença de código não garante produto pronto nem compilação homologada.

## Compilação Android

Abra individualmente no Android Studio as pastas `GO`, `DELIVERY` e `CONNECT`. Requisitos gerais: JDK 17, Android SDK 36 e Gradle 9.3.1, com dependências pela Internet. Os arquivos de wrapper binários não acompanham todos os projetos.

GO: contém `build-debug.sh` e `build-debug.ps1`, com instruções em `BUILDING.md`. Usa API HTTPS definida em `EVENTMENU_API_BASE_URL` no build. Release exige configuração FCM e assinatura adequada.
DELIVERY: base `dev/delyvre`, com validações de pagamento, rastreamento e testes adicionais; compilação e integração com a API do V9 ainda não homologadas. Usa `EVENTMENU_DELIVERY_API_BASE_URL` e variáveis de FCM/assinatura para Release.
CONNECT: requer preparação do runtime Node.js nativo ARM64, dependências Baileys, biblioteca `libnode.so` e recursos empacotados conforme `.github/workflows/eventmenu-connect-android.yml`, além de keystore para release. SOMENTE o código-fonte não é suficiente para gerar diretamente o APK funcional sem essa etapa.

## Windows

Executar `desktop/build-windows.ps1` em Windows com SDK .NET 8 e acesso à Internet. O script depende do caminho relativo `integrations/whatsapp-worker` (fornecido). Gera aplicativos Desktop e WhatsApp Connect e prepara Node/Baileys. Validação com impressoras, leitor de códigos e recursos fiscais precisa ser feita em hardware real.

## Servidor

Se for uma instalação nova, **extraia SOMENTE o conteúdo de `03_SERVIDOR/PHP_SQLITE_V9/`** para a pasta destinada ao servidor, mantendo `.htaccess` e os caminhos. Não copie o ZIP organizado inteiro para `public_html`.
Siga o instalador `install.php` e o LEIA-ME do servidor. Confirmar extensões do PHP, permissões, HTTPS, backup, configuração de cron e banco antes de abrir ao público. `PORTAL_APKS` é uma aplicação separada: planeje um diretório/domínio de distribuição próprio.
Em instalação existente, não sobrescrever `.env`, `storage/` nem dados SQLite. Faça backup e teste em staging antes da atualização.

## Branches e Git

O arquivo `cardapio-todas-branches.zip` contém snapshots do código, NÃO o histórico Git completo: não cria branches Git automaticamente. O CSV identifica branch, commit original e presença de código por plataforma. Para organizar as branches no GitHub é necessário criar/atualizar um repositório Git e publicar essas pastas.

## Limitações desta preparação

Verificamos estrutura dos projetos, integridade dos ZIPs e sintaxe PHP onde indicado. Android/Windows NÃO foram compilados neste ambiente; nenhuma release APK, EXE ou instalador foi gerado. O banco e os serviços externos também não foram homologados ponta a ponta nesta etapa.
