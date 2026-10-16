# Cardápio — versão unificada (16/10/2026)

Este arquivo reúne os **projetos de código-fonte organizados**. Não contém aplicativos APKs/EXEs compilados.

## Organização

- `01_APK_ANDROID/GO/` — base Android GO V10 com o redesign do GO, substitui a edição inicial da branch 145. Kotlin/Compose.
- `01_APK_ANDROID/DELIVERY/` — base Delyvre/Delivery da `dev/delyvre`, acrescida de **tema e tokens V11**. Atenção: a prévia V11 é conceitual, não corresponde integralmente ao código de todas as telas.
- `01_APK_ANDROID/CONNECT/` — base Android Connect da branch integrada 145; requer Node/Baileys embutido e configuração nativa para build funcional.
- `02_WINDOWS/desktop/` — EventMenu Desktop e WhatsApp Connect Windows (.NET 8/WPF).
- `02_WINDOWS/integrations/` — dependências de integração, incluindo whatsapp-worker.
- `03_SERVIDOR/PHP_SQLITE_V9/` — servidor PHP SQLite SaaS V9 + **correção CSS V9.1** incorporada em ambos `assets/platform-saas-v9.css` e `public/assets/platform-saas-v9.css`.
- `03_SERVIDOR/PORTAL_APKS/` — portal de publicação/download de APKs.
- `03_SERVIDOR/SCRIPTS_FONTE/` — scripts de empacotamento, verificação e manutenção.
- `04_REFERENCIAS_DE_BRANCHES/` — versões alternativas para revisão. Não sobrepor automaticamente.
- `05_DOCUMENTACAO/` — documentação, mapa das branches, workflows originais, prévias HTML e manifestos de arquivos.

## Como utilizar

Abra cada aplicativo **na sua própria pasta** no Android Studio. O GO contém `build-debug.sh` e `build-debug.ps1`. O Delivery contém scripts do Gradle, mas não foi compilado aqui. O Connect necessita dos assets Node e Baileys descritos nos workflows. Para Windows, use Windows com .NET 8 e `02_WINDOWS/desktop/build-windows.ps1`.

Para instalar o servidor, NÃO envie esse ZIP todo ao `public_html`. Extraia **somente** `03_SERVIDOR/PHP_SQLITE_V9/` no diretório de instalação, mantendo as proteções de pastas. Faça backup da instalação existente e preserve `.env`, `storage/` e o banco de produção.

O portal `PORTAL_APKS` deve ser publicado separadamente, com autenticação e HTTPS configurados.

## Validação e limitações

Empacotamento e integridade conferidos. **Não houve compilação Gradle, Android SDK nem Windows; não há APK/EXE de produção.** O servidor e os pagamentos precisam ser testados em ambiente de homologação. Regras de negócio, API e banco não foram modificados durante esta união, exceto arquivos do pacote escolhido e atualização visual V9.1.

### Histórico de atualização

- Android GO: V10 (telas operacionais redesenhadas conforme pacote fornecido).
- Android Delivery: V11 parcial (paleta, tema, dimensões); não trata prévia HTML como tela Compose implementada.
- Android Connect: branch 145.
- Windows: branch 145.
- Servidor: SaaS V9 + CSS corrigido V9.1.
