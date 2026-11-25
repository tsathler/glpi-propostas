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

- **Acesso:** os perfis que podem alterar a Configuração recebem acesso total na instalação. Os outros perfis são liberados em **Administração > Perfis > Propostas**, com dois direitos: *Propostas* e *Gerenciar modelos e blocos de texto*. O direito *Propostas* tem três níveis de leitura: **Ler as próprias** (o usuário é o consultor), **Ler da equipe** (também as dos consultores das equipes em que é Responsável) e **Ler todas**. Criar, atualizar e excluir valem só sobre as propostas que o usuário vê.
- **Menu:** saia e entre de novo no GLPI (ou troque de perfil) para os menus aparecerem em **Ferramentas**.
- **Atualizar:** troque a pasta e rode os mesmos comandos, com `plugin:install propostas --force`. O `cache:clear` faz o GLPI carregar as rotas novas.

## Funcionalidades

- **Propostas:** menu **Ferramentas > Propostas**, com lista e busca.
- **Cadastro:** título (opcional: em Configuração > Plug-ins > Propostas, "Pedir o nome da proposta" desligado tira o campo e gera o nome como "{modelo} – {razão social}"), empresa cliente (CNPJ, razão social e nome fantasia), contato na empresa, consultor, chamado vinculado, emissão, validade e condições comerciais (vêm do modelo; com "Permitir editar as condições comerciais na proposta" desligado, ficam só para leitura na proposta).
- **Modelos de proposta:** menu **Ferramentas > Modelos de proposta**. O modelo guarda validade, condições, itens (com valores de exemplo) e os blocos de texto que usa. Na criação da proposta, tudo isso é **copiado** para ela; depois a proposta é independente e o modelo não pode ser trocado. Ao escolher o modelo na proposta nova, aparece o quadro "Valores desta proposta": os itens do modelo com o valor e o desconto (%) editáveis item a item e o valor final de cada um. O **desconto sugerido** do modelo aparece como dica, com o botão "Usar X% em todos", que só preenche os descontos quando clicado. O modelo também define como os valores aparecem ao cliente: com quantidade, subtotal e totais (o padrão) ou **por unidade**, para preços cobrados por pessoa ou unidade (ex.: por participante ativo por mês), com "de R$ X por R$ Y" quando há desconto e sem somar totais. A proposta guarda essa escolha, que pode ser mudada enquanto ela é rascunho. Um bloco de texto marcado "Mostrar depois dos valores" aparece no fim da página do cliente, depois do investimento e das condições (bom para agradecimento, assinatura e contato).
- **Blocos de texto:** menu **Ferramentas > Blocos de texto**, uma biblioteca de textos formatados (título e texto). Cada modelo escolhe quais blocos usa e em que ordem. Na proposta, a aba **Blocos de texto** permite editar, incluir da biblioteca e remover enquanto ela é rascunho.
- **Busca de CNPJ:** o botão **Buscar CNPJ** preenche razão social e nome fantasia. A consulta sai do servidor do GLPI (com o proxy do GLPI, se houver), envia só o CNPJ e espera no máximo 5 segundos. Provedor padrão: BrasilAPI; a URL e o liga/desliga ficam em **Configuração > Plug-ins > Propostas**. Aceita CNPJ numérico e alfanumérico.
- **Itens:** aba **Itens** com linhas recorrentes (mensais) ou pontuais (pagamento único), cada uma com quantidade, valor unitário, desconto, subtotal e totais.
- **Link para o cliente:** gerar (exige razão social e CNPJ válido), copiar, reabrir como nova versão ou cancelar. A página pública não pede login e não usa CSS nem JavaScript do GLPI; mostra a empresa na capa e os blocos de texto antes dos itens.
- **Trava de edição:** a proposta enviada fica travada. Para mudar valores, é preciso reabrir, o que cria uma nova versão e invalida o link anterior.
- **Resposta do cliente:** o cliente **aceita** em nome da empresa (nome, CPF, cargo e declaração) ou envia **dúvidas**. O aceite grava um código de verificação dos valores aceitos.
- **Chamado da proposta:** ao gerar o link de uma proposta sem chamado vinculado, o plugin abre um chamado (requisição) na entidade da proposta, com o consultor como requerente e técnico; categoria e grupo técnico são opcionais, em **Configuração > Plug-ins > Propostas** (recomenda-se a categoria: se o GLPI exigir categoria em chamado atribuído, um chamado sem ela não pode ser editado pela tela; a página de configuração avisa quando ela está em branco). Envio, aceite, dúvida, cancelamento e expiração viram acompanhamentos privados no chamado. No aceite e na expiração, o plugin registra a solução e fecha o chamado; na proposta cancelada, ele continua aberto. Se uma proposta reaberta for reenviada com o chamado já fechado, o plugin abre um chamado novo. Quem preferir pode vincular um chamado existente antes de gerar o link, depois de criar a proposta (na criação o campo fica bloqueado).
- **Abas da proposta:** **Respostas do cliente** e **Acessos ao link**.
- **Identidade visual (opcional):** em **Configuração > Plug-ins > Propostas**, logotipo e cores da página do cliente. Use a versão clara do logotipo (o topo é escuro), de preferência PNG com fundo transparente; o plugin corta as margens transparentes e reduz a altura para 160 px. Cor principal (topo e quadro de decisão) e cor de destaque (botão de aceite, etiquetas e títulos) no formato `#rrggbb`; em branco, ficam as cores padrão. Também dá para definir um texto de rodapé (ex.: direitos autorais), que aparece no fim da página do cliente.
- **Equipes de consultores (opcional):** em **Configuração > Plug-ins > Propostas**, escolha um grupo pai. Cada grupo filho direto é uma equipe; o membro marcado como **Responsável** é o gestor. Com as equipes ligadas: quem não está em nenhuma equipe não cria propostas (exceto o nível *Ler todas*); o consultor só pode ser alguém de uma equipe; o gestor pode pôr como consultor só os membros das equipes que gere (o campo *Consultor* já lista só quem o usuário pode escolher); e o gestor entra como **observador** no chamado das propostas da equipe. A visão segue a equipe **atual**: quando alguém muda de equipe ou o Responsável muda, o plugin troca o observador nos chamados ainda abertos (os solucionados e fechados não mudam, e um observador incluído à mão nunca é removido). A página de configuração avisa sobre consultor em mais de uma equipe e equipe sem ou com mais de um Responsável.
- **Entidade padrão (opcional):** as propostas nascem sempre na entidade escolhida, quando o usuário tem acesso a ela.
- **Notificações:** proposta aberta, aceita, com dúvida e expirada. Destinatários próprios: *Consultor da proposta* e *Gestor da equipe do consultor* (calculado na hora do envio). Para avisar um nível acima do gestor, use o destinatário nativo por **perfil**. Exigem as notificações por e-mail ativas no GLPI.
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
