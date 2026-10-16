# Cardápio GO · Visual SaaS V10

Projeto **Android nativo Kotlin + Jetpack Compose**. Esta entrega altera somente a interface, sem modificar modelos, repositórios, endpoints, notificações, pagamentos ou permissões.

## Alterações realizadas
- Tela inicial de operação: cabeçalho e quadro do turno, QR/atualização, atalhos condicionados às permissões e métricas reais
- Tema Material 3: nova paleta azul SaaS, fundo suave, superfícies claras, bordas/raios e tipografia legível
- PDV: cards de produtos mais organizados, imagens recortadas e categorias preservadas
- Cozinha/KDS: resumos de novos/em preparo e cartões de pedidos com status destacados; comandos de preparo originais preservados
- Barra de navegação: contraste e indicador de seleção; labels visíveis
- Login e seleção de modo: identidade visual alinhada ao painel

## Como aplicar ao repositório
O ZIP de atualização usa os caminhos originais de `mobile/eventmenu-go/`. Extraia na RAIZ do repositório Git, sobrescrevendo somente os seis arquivos Kotlin listados. Faça commit/backup antes.

## Como compilar
Abra `mobile/eventmenu-go` no Android Studio, instale SDK 36 e JDK 17. Consulte BUILDING.md. No Linux/macOS, dentro da pasta do GO, execute `bash ./build-debug.sh`; no Windows use `build-debug.ps1`. O build requer Gradle 9.3.1 e dependências via internet.
O endereço HTTPS da API continua configurado em `app/build.gradle.kts`, com default `https://go.gestao2.store/1/` e override `EVENTMENU_API_BASE_URL`.

**Esta entrega é código-fonte, não um APK compilado.** A compilação Android e testes em aparelho ainda são necessários; não houve alteração de versão, assinatura ou configuração Firebase.

## O que não muda
- Autenticação, PIN, biometria, perfis e permissões
- Fluxo de pedidos, caixa, pagamentos PIX/NFC e impressão
- Integração com API e banco no servidor
- Aplicativos Connect/Delivery e programa Windows
- Navegação para módulos não redesenhados, que herdam o novo tema global

## Prévia HTML
A prévia independente é apenas uma simulação com valores fictícios e permite ver os estilos antes de instalar. Não se conecta ao servidor nem substitui o aplicativo real. Imagens/ícones nela são ilustrativos, não recursos empacotados no Android.
