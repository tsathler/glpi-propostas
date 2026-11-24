# Propostas (plugin para GLPI 11)

Propostas comerciais dentro do GLPI. O consultor monta a proposta com itens recorrentes e pontuais e gera um link. O cliente abre a proposta nesse link, sem login no GLPI, e pode aceitá-la ou enviar dúvidas.

## Requisitos

- GLPI 11.x (testado no 11.0.4; o GLPI 12 ainda não é aceito)
- PHP 8.2 ou superior, com `mbstring`

## Instalação

Baixe o zip da [última release](https://github.com/tsathler/glpi-propostas/releases/latest) e extraia dentro da pasta `plugins` do GLPI. A pasta precisa se chamar exatamente `propostas`.

```bash
cd /caminho/do/glpi
sudo -u www-data php bin/console plugin:install propostas
sudo -u www-data php bin/console plugin:activate propostas
sudo -u www-data php bin/console cache:clear
```

- **Acesso:** os perfis que podem alterar a Configuração recebem acesso total na instalação. Os outros perfis são liberados em **Administração > Perfis > Propostas**, com dois direitos: *Propostas* e *Gerenciar modelos e blocos de texto*.
- **Menu:** saia e entre de novo no GLPI (ou troque de perfil) para os menus aparecerem em **Ferramentas**.
- **Atualizar:** troque a pasta e rode os mesmos comandos, com `plugin:install propostas --force`. O `cache:clear` faz o GLPI carregar as rotas novas.

## Funcionalidades

- **Propostas:** menu **Ferramentas > Propostas**, com lista e busca.
- **Cadastro:** título, empresa cliente (CNPJ, razão social e nome fantasia), contato na empresa, consultor, chamado vinculado, emissão, validade, aviso prévio e condições comerciais.
- **Modelos de proposta:** menu **Ferramentas > Modelos de proposta**. O modelo guarda validade, aviso prévio, condições, itens (com valores de exemplo) e os blocos de texto que usa. Na criação da proposta, tudo isso é **copiado** para ela; depois a proposta é independente e o modelo não pode ser trocado.
- **Blocos de texto:** menu **Ferramentas > Blocos de texto**, uma biblioteca de textos formatados (título e texto). Cada modelo escolhe quais blocos usa e em que ordem. Na proposta, a aba **Blocos de texto** permite editar, incluir da biblioteca e remover enquanto ela é rascunho.
- **Busca de CNPJ:** o botão **Buscar CNPJ** preenche razão social e nome fantasia. A consulta sai do servidor do GLPI (com o proxy do GLPI, se houver), envia só o CNPJ e espera no máximo 5 segundos. Provedor padrão: BrasilAPI; a URL e o liga/desliga ficam em **Configuração > Plug-ins > Propostas**. Aceita CNPJ numérico e alfanumérico.
- **Itens:** aba **Itens** com linhas recorrentes (mensais) ou pontuais (pagamento único), cada uma com quantidade, valor unitário, desconto, subtotal e totais.
- **Link para o cliente:** gerar (exige razão social e CNPJ válido), copiar, reabrir como nova versão ou cancelar. A página pública não pede login e não usa CSS nem JavaScript do GLPI; mostra a empresa na capa e os blocos de texto antes dos itens.
- **Trava de edição:** a proposta enviada fica travada. Para mudar valores, é preciso reabrir, o que cria uma nova versão e invalida o link anterior.
- **Resposta do cliente:** o cliente **aceita** em nome da empresa (nome, CPF, cargo e declaração) ou envia **dúvidas**. O aceite grava um código de verificação dos valores aceitos.
- **Chamado da proposta:** ao gerar o link de uma proposta sem chamado vinculado, o plugin abre um chamado (requisição) na entidade da proposta, com o consultor como requerente e técnico; categoria e grupo técnico são opcionais, em **Configuração > Plug-ins > Propostas**. Envio, aceite, dúvida, cancelamento e expiração viram acompanhamentos privados no chamado, que é fechado manualmente. Quem preferir pode vincular um chamado existente antes de gerar o link.
- **Abas da proposta:** **Respostas do cliente** e **Acessos ao link**.
- **Notificações ao consultor:** proposta aberta, aceita e com dúvida. Exigem as notificações por e-mail ativas no GLPI.
- **Duplicar:** cria um rascunho novo a partir de uma proposta.
- **Expiração automática:** a ação automática diária **expirar** marca como *Expirada* a proposta cujo link venceu. Ela roda pelo cron do sistema (modo CLI do GLPI); sem o cron configurado, o link vence no prazo, mas o status só muda quando a ação rodar.
- **Exclusão:** proposta com aceite registrado não pode ser excluída (use *Cancelar*), para não perder a prova do aceite. **Desinstalar o plugin apaga todas as propostas, aceites e acessos**: faça backup antes.

## Segurança do link público

- **Token:** 32 bytes aleatórios (base64url, 43 caracteres). O banco guarda o SHA-256 do token, usado na busca, e uma cópia cifrada com a chave do GLPI, que permite ao consultor copiar o link de novo.
- **Validade:** o link vale até as 23:59:59 do último dia de validade. Link vencido responde 410; token inexistente ou revogado responde 404.
- **Proteção dos envios:** o GLPI confere o token anti-falsificação (CSRF). O limite é de 10 envios por IP (ou rede /64 do IPv6) por hora, sem contar envios recusados pela validação, e de 5 dúvidas por proposta a cada 24 horas. O banco guarda só o hash do IP.
- **Respostas simultâneas:** aceite, dúvida e as ações do consultor travam a proposta no banco, então duas respostas ao mesmo tempo não geram aceite duplo nem aceite numa versão reaberta.
- **Quem vê o link:** só quem pode editar a proposta vê e copia o link; quem só lê vê que ele foi enviado e a validade.
- **CPF:** guardado cifrado, mais uma versão mascarada (`***.456.789-**`). O CPF completo só aparece para quem pode editar a proposta.
- **Código de verificação:** é o SHA-256 de número, versão, título, datas, condições, itens e totais e, desde a versão 1.1.0 (formato 2), também da empresa (razão social e CNPJ), dos blocos de texto e do cargo de quem aceitou. Cada aceite guarda o formato em que foi calculado, e a aba Respostas confere cada um no próprio formato.
- **Cabeçalhos:** a página envia `X-Robots-Tag: noindex`, `Cache-Control: no-store`, `Referrer-Policy: no-referrer` e `X-Frame-Options: DENY`.

## Expor só o link público

O GLPI pode ficar só na rede interna. Para o cliente abrir o link de fora, crie no Nginx um `server` para o endereço externo que libere **apenas** as rotas públicas do plugin. Depois informe esse endereço em **Configuração > Plug-ins > Propostas**. Exemplo:

```nginx
server {
    listen 443 ssl;
    server_name propostas.exemplo.com.br;
    # ssl_certificate / ssl_certificate_key do seu certificado

    root /caminho/do/glpi/public;

    location ~ ^/plugins/propostas/p/[A-Za-z0-9_-]{43}(/(aceitar|duvida))?$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_pass unix:/run/php/php-fpm.sock;   # ajuste para o seu PHP-FPM
    }

    location / { return 404; }
}
```

Se o endereço externo ficar em branco, os links usam o "URL da aplicação" do GLPI. Em ambientes clonados, confira se esse endereço aponta para o servidor certo.

## Notas para quem mantém o plugin

- **Rotas POST no GLPI 11.0.4:** o `PluginsRouterListener` não passa o método HTTP ao roteador de plugins, então rotas com `methods: ['POST']` nunca casam. As rotas `/aceitar` e `/duvida` não restringem o método, e o controller confere o POST.
- **Testes:** cálculos e CPF ficam em `src/Calculo.php` e `src/Cpf.php`, sem dependência do GLPI. Para rodar os testes: `composer install && composer test`.
- **Hash do aceite:** o teste do hash fixa o resultado de um exemplo. Mudar o formato de `Calculo::hashValores()` faz os aceites antigos deixarem de conferir.

## Licença

GPL-3.0-or-later. Veja [LICENSE](LICENSE).
