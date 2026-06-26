# HTTPS local no XAMPP

O Android/Chrome so libera notificacao na barra e instalacao PWA completa quando o site abre em HTTPS confiavel.

## Resumo

1. Gere um certificado para o IP ou nome do computador.
2. Configure o Apache do XAMPP para usar esse certificado.
3. Instale o certificado raiz no Android como confiavel.
4. Acesse pelo celular usando `https://IP-DO-PC/estoque-pedidos/login.php`.

## Caminho recomendado

Use uma ferramenta como `mkcert`, porque ela cria certificado com SAN correto para IP local.

Exemplo:

```bat
mkcert -install
mkcert 192.168.0.10 localhost 127.0.0.1
```

Troque `192.168.0.10` pelo IP do computador do XAMPP.

Depois coloque os arquivos gerados em:

```text
C:\xampp2\apache\conf\ssl.crt\estoque-pedidos.pem
C:\xampp2\apache\conf\ssl.key\estoque-pedidos-key.pem
```

Inclua o arquivo `apache-estoque-ssl.conf` no Apache ou copie o bloco dele para o SSL virtual host do XAMPP.

## Android

Para o Chrome confiar no HTTPS local, instale no Android o certificado raiz criado pelo `mkcert`.

No Windows, o arquivo costuma ficar em:

```text
%LOCALAPPDATA%\mkcert\rootCA.pem
```

Envie esse arquivo para o celular e instale em:

```text
Configurações > Segurança > Criptografia e credenciais > Instalar certificado
```

O caminho muda um pouco conforme a marca do Android.

## Observacao

Sem HTTPS confiavel, o sistema ainda mostra aviso dentro da pagina e vibra, mas a notificacao da barra pode ser bloqueada pelo Chrome.

