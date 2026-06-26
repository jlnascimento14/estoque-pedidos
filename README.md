# Sistema de pedidos para estoque

Sistema simples em PHP e MySQL/MariaDB para rodar no XAMPP.

## Como instalar

1. Copie a pasta `estoque-pedidos` para `C:\xampp2\htdocs\`.
2. Abra o XAMPP em `C:\xampp2` e ligue `Apache` e `MySQL`.
3. Acesse `http://localhost/estoque-pedidos/install.php`.
4. Clique em `Instalar agora`.
5. Depois use `http://localhost/estoque-pedidos/`.

Se preferir, tambem pode importar `database.sql` pelo phpMyAdmin.

## Telas

- `index.php`: pedido da vendedora, sem acesso visual ao dashboard.
- `login.php`: acesso da vendedora, estoque e admin.
- `dashboard.php`: painel do estoque.
- `produtos.php`: cadastro e ativacao de produtos.
- `vendedoras.php`: cadastro de vendedoras.
- `pedido.php`: detalhe do pedido e troca de status.
- `print.php`: impressao do pedido em largura de 80 mm.
- `confirmar_impressao.php`: registra confirmacao manual de impressao.
- `relatorios.php`: relatorios por periodo.
- `exportar_relatorio.php`: exporta CSV que abre no Excel.
- `manifest.json` e `sw.js`: arquivos para instalar como app no Android.

## Senhas padrao

- Vendedora: `0000`
- Estoque: `1234`
- Admin: `admin123`

Altere essas senhas em `config.php` antes de usar em definitivo.

## Impressao automatica na COM3

O sistema esta configurado para enviar novos pedidos direto para a porta `COM3` pelo XAMPP, sem abrir a janela de impressao do navegador.

No `dashboard.php`, a tela confere novos pedidos a cada 30 segundos. Quando aparecer pedido novo, o PHP envia o texto direto para a porta virtual `COM3`.

O botao `Ativar som de aviso` serve apenas para liberar o som no navegador. A impressao direta nao depende desse botao.

Para mudar a porta, edite `PRINTER_PORT` em `config.php`.

O comprovante automatico pela COM3 segue o mesmo padrao visual basico da tela `print.php`: cabecalho centralizado, linhas tracejadas, produto e quantidade em colunas. Ele envia avanco de papel e corte Bematech/Epson ao final do pedido.

## Fluxo do estoque

Na aba `Em aberto`, cada pedido tem botoes para marcar:

- `Conferindo`: muda o pedido para separacao/conferencia.
- `Concluido`: tira o pedido da lista principal e envia para a aba `Concluidos`.

O dashboard inicia o monitoramento assim que abre e continua conferindo novos pedidos a cada 30 segundos.
Quando chegam varios pedidos juntos, a impressao automatica envia um por vez para a COM3.
Quando a impressao automatica e enviada com sucesso para a COM3, o pedido fica marcado como `Impresso` no dashboard e no historico.

Na tela manual `print.php`, use `Confirmar impressao` para registrar a impressao manual ou reimpressao no historico.

Quando a vendedora envia um pedido, o sistema ja imprime imediatamente pela COM3. O dashboard nao reimprime esse mesmo pedido porque a impressao fica registrada.

## Fluxo da vendedora

A vendedora entra pelo `login.php`, escolhe o proprio nome no campo `Perfil` e usa apenas `index.php`. Depois de enviar o pedido, ela recebe confirmacao e continua na tela de novo pedido.

O nome da vendedora vem do cadastro em `vendedoras.php`.

Na tela da vendedora aparece a lista dos pedidos dela. A lista atualiza sozinha.

A vendedora pode cancelar o pedido enquanto ele ainda estiver como `Novo`.

Pedidos podem ser marcados como `Normal` ou `Urgente`. No dashboard, urgentes aparecem destacados e no topo.

## App no Android

Depois de configurar HTTPS, abra o sistema no Chrome do Android e toque em `Instalar app` na tela da vendedora. Se o botao nao aparecer, use o menu do Chrome e toque em `Adicionar a tela inicial`.

Os arquivos de apoio para HTTPS local ficam em `https-local/`.

## Impressora Bematech MP-4200

Instale a MP-4200 no Windows como impressora normal. Na tela de impressao, selecione essa impressora e use papel de 80 mm. O arquivo `print.php` ja esta formatado para cupom.

Para impressao direta sem abrir a janela do navegador, normalmente e necessario instalar um servico local de impressao/ESC-POS ou configurar quiosque no navegador. Esta primeira versao usa a impressao padrao do Windows, que e a forma mais simples e estavel para comecar.

## Ajustes do banco

As credenciais ficam em `config.php`.

Padrao do XAMPP:

- usuario: `root`
- senha: vazia
- banco: `estoque_pedidos`
