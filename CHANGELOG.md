# Changelog

Todas as mudanças relevantes deste plugin ficam registradas aqui.
O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue o [SemVer](https://semver.org/lang/pt-BR/).

## [Unreleased]

## [1.9.3] - 2026-11-26

### Fixed

- **Envio da página do cliente:** clique duplo em "Enviar dúvida" ou "Confirmar aceite" manda uma vez só; a busca do token novo desiste em 5 segundos e envia com o da página, em vez de travar o envio com a internet ruim.

## [1.9.2] - 2026-11-26

### Fixed

- **Botões "Gerar link", "Reabrir" e "Cancelar"** aparecem só para quem pode editar a proposta (antes, quem só lia via os botões e recebia "acesso negado").
- **Pular a cópia do modelo** deixou de ser um campo da requisição: só a duplicação faz isso, e um envio direto não liga mais a proposta a um modelo inativo ou de outra entidade.
- **Excluir uma proposta com chamado** tira do chamado os observadores que o plugin tinha posto por ela.
- **Quantidade que arredonda para zero** (0,004) é recusada; antes virava 0 na gravação.
- **Lista de modelos ao criar** usa a entidade em que a proposta será criada (com entidade padrão configurada, aparecia um modelo que depois era recusado).

### Changed

- Código: um só laço de cópia de itens e blocos (criação pelo modelo e duplicação); filtro da proposta travada numa função testada; remoção de código morto e de um repasse sem lógica.

## [1.9.1] - 2026-11-26

### Fixed

- **Aceite e dúvida com a página aberta por muito tempo:** antes de enviar, a página pede um token anti-falsificação novo. Antes, depois de 24 minutos parada, o GLPI recusava o envio com "ação não permitida" e o cliente perdia o que tinha digitado. Quem expõe o plugin por Nginx precisa liberar a rota `/csrf` (exemplo atualizado no README).
- **Prévias de link** (WhatsApp, Teams, Slack, Telegram, buscadores) não contam mais como visualização do cliente nem disparam o aviso de proposta aberta.
- **Consultor em branco ao editar** mantém o consultor atual; antes, gravava "ninguém" e a proposta sumia para quem não vê todas.
- **"Gerar link" e "Duplicar"** salvam antes o que está na tela; antes, o link podia sair com a razão social ou o CNPJ antigos.
- **Página do cliente, item com quantidade:** mostra "3 unidades: R$ 20,00" em vez de "3 × R$ 6,67 = R$ 20,00", conta que não fechava por causa do arredondamento por unidade.
- **Número no formato americano** ("1,234.56") é recusado em vez de virar 1,23456.
- **"Diferença" ao criar a proposta** conta a quantidade de cada item do modelo.
- **README:** limite de envios e quem vê o CPF completo, como no código.

## [1.9.0] - 2026-11-26

### Added

- **Prazo de guarda de IP e navegador configurável** em Configuração > Plug-ins > Propostas (de 1 a 3650 dias; padrão 180). A ação automática `limpar` usa o prazo configurado.

## [1.8.0] - 2026-11-25

### Added

- **Privacidade (LGPD):**
  - ação automática diária `limpar` que apaga IP e navegador de acessos ao link e de dúvidas com mais de 180 dias (a linha fica com a data; o aceite mantém os dele, que são a prova do contrato);
  - aviso de privacidade no formulário de aceite, configurável em Configuração > Plug-ins > Propostas (em branco, aparece um texto padrão sobre os dados registrados).

### Changed

- **CPF de quem aceitou** aparece completo só para quem gerencia modelos e blocos de texto (a supervisão); consultores e gestores veem a máscara.
- **Página do cliente depois do aceite** mostra só a data e o código de verificação, sem nome nem cargo de quem aceitou (o link pode ser repassado). Nome e cargo continuam na aba "Respostas do cliente".
- **Limite de tentativas** conta também os envios recusados (CPF inválido, campo vazio), para ninguém testar CPFs à vontade; o limite por hora passa de 10 para 20.

## [1.7.5] - 2026-11-25

### Fixed

- **Preço do modelo com mais de 2 casas** (ex.: R$ 0,0125 por unidade) é copiado inteiro para a proposta. Antes o quadro da criação arredondava para 2 casas e a proposta gravava R$ 0,01. Os campos desse quadro são numéricos: neles o ponto é decimal ("1.125" é 1,125); o ponto de milhar ("1.500") vale nos campos de texto dos itens.
- **Mudança numa equipe** acerta os observadores só dos chamados em andamento (antes carregava todos os chamados de proposta, inclusive solucionados e fechados), e alteração de membro que não mexe em grupo nem em Responsável não dispara a sincronização.
- **Limite de tentativas da página pública:** IPv4 escrito como IPv6 (`::ffff:1.2.3.4`, em servidor que escuta os dois num socket só) volta a contar como o próprio IPv4; antes todos esses clientes dividiam o mesmo limite.

### Changed

- Permissões de itens e blocos de texto num arquivo só (`LinhaDoRascunho`), e checagens de imagem enviada (logotipo e elementos decorativos) numa função só, com as mesmas mensagens para os dois.
- Testes novos: separação por IP, regra de visibilidade igual na lista e no formulário, cópia de preço com 4 casas.

## [1.7.4] - 2026-11-25

### Fixed

Pontos menores da auditoria de 25/11/2026:

- **Cancelar a proposta** soluciona e fecha o chamado, como o aceite e a expiração. **Reabrir** registra um acompanhamento no chamado.
- **Chamado vinculado apagado ou na lixeira** conta como fechado: o próximo link abre um chamado novo (antes os acompanhamentos se perdiam).
- **Modelo usado por propostas** não pode ser excluído (o vínculo ficaria órfão); a mensagem orienta a desativar.
- **Bloco de texto desativado** na biblioteca deixa de ser copiado para propostas novas.
- **Validade** conferida no servidor: de 1 a 365 dias, na proposta e no modelo (antes só o navegador limitava).
- **Entidade da proposta** só muda para uma entidade a que a pessoa tem acesso.
- **Imagens enviadas** (logotipo e elementos decorativos) com mais de 25 milhões de pixels são recusadas antes de abrir, para não esgotar a memória.
- **Notificações na instalação:** o marcador é gravado depois de tudo criado (se falhar, reinstalar tenta de novo) e o destinatário Gestor é conferido também nas notificações que já existiam.
- **Status e Versão** saem da ação em massa (o servidor ignorava a mudança e o GLPI dizia que salvou).
- **Página do cliente:** o botão fixo "Aceitar proposta" escondido não recebe mais o foco do teclado; o aviso do CPF é lido uma vez pelo leitor de tela e o CPF incompleto é avisado ao sair do campo; cor de fundo de reserva para navegadores sem `color-mix()`.
- **Impressão:** blocos longos não pulam mais para a página seguinte; o texto do rodapé (direitos) sai no PDF.
- **Formulário da proposta:** o valor final calculado na tela usa o mesmo arredondamento do servidor; trocar de modelo rápido não mistura mais os itens de dois modelos.
- **Documentação:** README (o que os testes cobrem) e o documento das equipes atualizados.

## [1.7.3] - 2026-11-25

### Fixed

Correções da auditoria de 25/11/2026:

- **Itens e blocos das propostas na busca e na API REST** seguem a visibilidade da proposta (nível de leitura e entidades ativas). Antes, quem lia só as próprias propostas listava itens, preços e textos de todas pela API.
- **Chamado vinculado à mão** precisa ser um chamado que a pessoa pode editar e que não esteja em outra proposta (o aceite e a expiração solucionam e fecham o chamado). Antes bastava ver o chamado.
- **Números com ponto de milhar sem vírgula** ("1.500", "12.000.000") passam a valer mil e quinhentos, doze milhões, nos itens e nos valores da criação. Antes "1.500" virava 1,50. "0.125" e "1.5" continuam decimais.
- **Exibição por unidade com quantidade diferente de 1:** o cartão do item mostra a conta (quantidade × preço = subtotal), que é o valor registrado no aceite.
- **Erro no aceite ou na dúvida** (CPF inválido, campo vazio): a página volta no quadro de decisão, onde está o aviso, e não mais no topo.
- **Contraste:** cor principal ou de destaque clara demais para texto branco é escurecida na página (contraste mínimo 4,5:1) nos botões, no topo e nos textos; a cor original continua nos detalhes.
- **Remover item ou bloco de texto de um rascunho** pede só a permissão de editar a proposta, como adicionar. Antes exigia a de excluir propostas, e o consultor via o botão "Remover" mas recebia "Acesso negado".
- **Textos longos sem espaço** (links, nomes) quebram em vez de alargar a página no celular; tabelas nos blocos rolam sozinhas; logo do rodapé não passa da largura da tela; cartão de item único alinhado à esquerda no celular.

## [1.7.2] - 2026-11-25

### Changed

- Formulário da proposta: "Comentários" sai do topo e vai para o fim, em largura total, como anotação interna (o cliente não vê). Sem o campo Nome, o formulário abre pelo modelo (na criação) ou pelo número (na edição), em vez de uma caixa de comentário com o modelo na outra coluna.

## [1.7.1] - 2026-11-25

### Changed

- Páginas de aviso do link (proposta expirada, link não encontrado, muitas tentativas) com a mesma identidade visual da página da proposta: topo com logotipo e elementos decorativos, cartão com ícone, título e mensagem, e o rodapé configurado. O rodapé e os elementos decorativos passam a vir de um arquivo compartilhado pelas páginas públicas.
- Endereço em `/p/` fora do formato de um link de proposta (token cortado ao copiar, caminho extra) mostra o aviso "Proposta não encontrada" da marca (404), em vez da página de erro do GLPI.

## [1.7.0] - 2026-11-25

### Added

- Elementos decorativos na página do cliente (Configuração > Plug-ins > Propostas > Identidade visual): até 6 imagens em PNG ou WebP com fundo transparente (formas e grafismos da marca), espalhadas, giradas e cortadas nas bordas no topo, no quadro de decisão e, em telas largas, bem claras nas margens. Cada proposta usa uma combinação delas, sempre a mesma para a mesma proposta. O plugin corta as margens transparentes, reduz o lado para 360 px e grava em WebP; as imagens vão embutidas na página. No celular aparecem menos; na impressão, sem as das margens. Sem imagens, a página fica sem decoração.

### Changed

- Página do cliente reorganizada e com novo visual:
  - topo com "Proposta para", a razão social e, abaixo, o título da proposta sem repetir a razão social (o nome automático "{modelo} – {razão social}" aparece só como "{modelo}"); o nome fantasia só aparece quando é outro nome, não uma repetição (ou um corte) da razão social;
  - a versão sai do subtítulo e só aparece a partir da segunda, como "revisão N", ao lado do número da proposta (o texto do aceite continua citando a versão);
  - ficha única com emissão, validade ("válida até"), contato, consultor e o botão de imprimir, logo abaixo do topo; o cartão "Condições comerciais" deixa de repetir emissão e consultor e fica com o prazo de validade e o texto das condições;
  - seções numeradas; listas e subtítulos dos blocos de texto com mais destaque; blocos "depois dos valores" como fecho, mais discretos;
  - "Investimento" numa seção só: por unidade, um cartão de preço por item (tipo, "de/por" e selo de desconto); com totais, o resumo e as listas de itens ficam juntos;
  - formulário de aceite com CPF e cargo lado a lado, campos maiores e foco visível; atalho fixo "Aceitar proposta" enquanto o quadro de decisão está fora da tela;
  - ajustes para celular e impressão.

## [1.6.0] - 2026-11-25

### Added

- Opção "Pedir o nome da proposta" (Configuração > Plug-ins > Propostas, padrão: ligada). Desligada, o campo Nome sai do formulário e o nome é gerado sozinho como "{modelo} – {razão social}", acompanhando a razão social. Ligada, um nome em branco também é gerado assim, em vez de a proposta ficar sem título.
- Opção "Permitir editar as condições comerciais na proposta" (mesma tela, padrão: ligada). Desligada, o campo Condições comerciais sai do formulário da proposta: ela leva só o texto do modelo, mostrado para leitura, e o servidor ignora o campo se ele vier na requisição. O texto continua editável no modelo.

### Changed

- Desconto por item no quadro "Valores desta proposta": cada item tem o seu campo de desconto (%), que vem com o desconto do item no modelo. Sai a opção "Aplicar desconto a todos os itens"; o desconto sugerido do modelo vira uma dica com o botão "Usar X% em todos", que só preenche os campos quando clicado.
- Campo Consultor: para quem só tem o nível *Ler as próprias*, que só pode ser consultor das próprias propostas, o campo vira um texto fixo com o nome da pessoa, sem menu.

## [1.5.1] - 2026-11-25

### Changed

- Rodapé da página do cliente sem a linha fixa "Proposta nº … · versão … O acesso a esta proposta é registrado e informado ao consultor responsável." (o número e a versão já estão no topo). Fica o texto configurado e, quando a proposta não pode mais ser respondida pela página, o aviso para falar com o consultor.

## [1.5.0] - 2026-11-25

### Added

- Identidade visual da página do cliente, em **Configuração > Plug-ins > Propostas**: logotipo (PNG, JPEG ou WebP; as margens transparentes são cortadas e a altura reduzida para 160 px) e duas cores, a principal (topo e quadro de decisão) e a de destaque (botão de aceite, etiquetas e detalhe dos títulos). O logotipo fica na pasta de arquivos do plugin e vai embutido na página. Sem configuração, a página fica com as cores de antes.
- Texto do rodapé da página do cliente (uma linha de texto puro, até 255 caracteres), na mesma configuração. Em branco, não aparece.

### Changed

- Topo da página do cliente: logotipo à esquerda e o número da proposta à direita, acima do nome da empresa; sem a sobra embaixo quando não há os cartões de total (exibição por unidade).
- Títulos das seções com um detalhe na cor de destaque; rodapé com o logotipo.

## [1.4.3] - 2026-11-24

### Changed

- Campo CPF do aceite: os pontos e o traço entram sozinhos enquanto o cliente digita (com ou sem pontuação, colado ou digitado) e, com os 11 dígitos, um CPF com dígitos verificadores errados mostra "CPF inválido — confira os números." no próprio campo e não deixa enviar. O servidor continua conferindo.

## [1.4.2] - 2026-11-24

### Changed

- O chamado da proposta é fechado automaticamente no aceite e na expiração (o plugin registra a solução e fecha). Na proposta cancelada, ele continua aberto. Antes, o chamado era sempre fechado à mão.
- Ao gerar o link de uma proposta reaberta cujo chamado já está fechado, o plugin abre um chamado novo (chamado fechado não recebe acompanhamentos).

## [1.4.1] - 2026-11-24

### Changed

- Quadro de valores da proposta nova alinhado ao formulário: o rótulo "Valores" fica na coluna dos rótulos e a tabela na coluna dos campos; campos com a mesma altura, fonte e cantos dos outros; linhas no ritmo do formulário; o desconto (%) virou a última linha, alinhado com os valores, com a diferença total em "Valor final". Sem cartão, sombra nem cabeçalho escuro. Em telas estreitas, cada item vira um bloco, com o campo na largura toda e o valor final embaixo.
- Na criação da proposta, "Chamado vinculado" fica bloqueado ("Aberto automaticamente ao gerar o link") e um chamado enviado na criação é ignorado. Depois de criada, a proposta pode ser vinculada a um chamado existente, como antes.

## [1.4.0] - 2026-11-24

### Added

- Quadro "Valores desta proposta" ao escolher o modelo na proposta nova: os itens do modelo com o valor editável (o valor digitado vai para a proposta no lugar do valor do modelo), a opção "Aplicar desconto a todos os itens" com o percentual editável e o valor final de cada item já com o desconto.
- Desconto sugerido no modelo (%): vem preenchido no percentual do quadro; o consultor decide se aplica e pode mudá-lo.
- Exibição dos valores ao cliente "por unidade", definida no modelo e ajustável na proposta em rascunho: cada item mostra o preço de uma unidade, com "de R$ X por R$ Y" quando há desconto, sem os cartões e as linhas de total. O padrão continua sendo quantidade, subtotal e totais.
- Bloco de texto "Mostrar depois dos valores": na página do cliente, o bloco vai para o fim, depois do investimento e das condições comerciais (ex.: agradecimento, assinatura, contato). A opção é copiada do bloco para a proposta e pode ser mudada no bloco da proposta.

### Changed

- O aviso prévio saiu dos formulários do modelo e da proposta e da página do cliente. A coluna continua no banco (e no código de verificação dos aceites), sem uso na tela.

### Fixed

- Os formulários do modelo e do bloco de texto mostravam o campo "Ativo" duas vezes.

## [1.3.1] - 2026-11-24

### Changed

- Com as equipes ligadas, o campo *Consultor* da proposta lista só quem o usuário pode escolher pela regra de atribuição (ele mesmo e, conforme o nível, os membros das equipes que gere ou de todas as equipes), ativos e com acesso à entidade da proposta. Antes, a lista trazia todos os usuários da entidade e a escolha errada só era recusada ao salvar. Com as equipes desligadas, nada muda.
- A página de configuração avisa quando a categoria do chamado automático está em branco: se o GLPI exigir categoria em chamado atribuído, os chamados abertos pelo plugin não podem ser editados pela tela até alguém escolher uma.

## [1.3.0] - 2026-11-24

### Added

- Níveis de leitura no direito *Propostas*: **Ler as próprias**, **Ler da equipe** e **Ler todas**. O filtro vale na lista, na busca, no formulário, nas abas, no duplicar e nas ações. Na atualização, todo perfil que já lia propostas ganha *Ler todas*, e ninguém perde acesso.
- Equipes de consultores: grupos filhos de um grupo pai configurado, com o Responsável do grupo como gestor. O gestor vê e age nas propostas da equipe e entra como observador nos chamados delas. A troca de equipe ou de Responsável atualiza os chamados abertos.
- Bloqueio: com as equipes ligadas, quem não está numa equipe não cria nem duplica propostas (exceto o nível *Ler todas*). A troca de consultor obedece ao nível de leitura.
- Notificação "Proposta expirada" e destinatário "Gestor da equipe do consultor" (incluído também nas notificações que já existem).
- Entidade padrão das propostas e avisos de cadastro das equipes na página de configuração.

## [1.2.1] - 2026-11-24

### Security

- O campo interno que libera as ações do plugin (gerar link, reabrir, cancelar, respostas, expiração) podia ser enviado no formulário de edição: quem podia editar uma proposta conseguia mudar status, versão, link e condições de uma proposta travada ou aceita. Agora o sinal é só interno.
- O link público (que permite aceitar ou mandar dúvida como cliente) aparece só para quem pode editar a proposta; quem só lê vê que o link foi enviado e a validade.
- Chamado vinculado validado: precisa ser um chamado que o usuário vê, da entidade da proposta (ou de uma entidade acima ou abaixo).
- Consultor validado: usuário ativo com acesso à entidade da proposta.
- Modelo e bloco de texto copiados por ID validados: ativos e visíveis na entidade da proposta (ou do modelo).
- Mudanças de status com a proposta travada no banco (aceite, dúvida, visualização, gerar link, reabrir, cancelar, expiração): respostas simultâneas não geram aceite duplo, "dúvida" depois do aceite, aceite numa versão reaberta nem dois chamados por duplo clique.
- Limite de envios: grava antes de contar (envios simultâneos não furam o limite), conta o IPv6 pela rede /64 e não conta envio recusado pela validação.
- No máximo 5 dúvidas por proposta a cada 24 horas.
- O registro de acessos ao link grava no máximo uma linha por proposta, versão e IP a cada 10 minutos.
- Link e CPF cifrados registrados no hook `secured_fields`: entram na troca de chave do GLPI (`glpi:security:change_key`) em vez de ficarem ilegíveis.
- Nome e dúvida digitados pelo cliente vão como texto puro no e-mail ao consultor (antes, tags HTML viravam links e imagens no e-mail).
- A busca de CNPJ não segue redirecionamentos.
- Proposta com aceite registrado não pode ser excluída (a exclusão apagava a prova do aceite).

### Fixed

- Duplicar uma proposta recursiva de uma entidade acima, ou gerar o link dela, criava a cópia ou o chamado numa entidade sem acesso do usuário. Agora usa a entidade ativa.
- Reinstalar (`plugin:install --force`) recriava notificação apagada, duplicava o modelo de notificação renomeado e devolvia a coluna "Razão social" retirada da lista. Agora isso é feito só uma vez.
- Desinstalar deixava as pesquisas salvas das listas do plugin.
- README: requisito "GLPI 11.x" (o 12 ainda não é aceito), dependência do cron do sistema para a expiração e aviso de que desinstalar apaga propostas e aceites.

## [1.2.0] - 2026-11-24

### Added

- **Chamado automático:** ao gerar o link de uma proposta sem chamado vinculado, o plugin abre um chamado (requisição) na entidade da proposta, com o consultor como requerente e técnico, e grava o vínculo. Se a abertura falhar, o link é gerado mesmo assim e o consultor recebe um aviso.
- Categoria e grupo técnico do chamado automático na configuração do plugin (opcionais).
- Acompanhamentos privados no chamado vinculado também no envio do link (com a validade), no cancelamento e na expiração.

## [1.1.0] - 2026-11-24

### Added

- **Modelos de proposta** (Ferramentas > Modelos de proposta): validade, aviso prévio, condições, itens com valores de exemplo e blocos de texto, copiados para a proposta na criação. O modelo de origem fica registrado e não pode ser trocado.
- **Blocos de texto** (Ferramentas > Blocos de texto): biblioteca de textos formatados; cada modelo escolhe quais usa e em que ordem. A proposta guarda a própria cópia (aba Blocos de texto, editável enquanto rascunho) e a página pública os mostra antes dos itens.
- **Empresa cliente** na proposta: CNPJ (numérico ou alfanumérico, com dígitos conferidos), razão social e nome fantasia. Razão social e CNPJ válido são obrigatórios para gerar o link.
- **Buscar CNPJ**: consulta pelo servidor do GLPI (proxy do GLPI, 5 s), BrasilAPI por padrão, URL e liga/desliga configuráveis; avisa quando a situação cadastral não é ATIVA.
- **Cargo** no aceite e declaração em nome da empresa (razão social e CNPJ).
- Aba **Propostas** em Administração > Perfis, com os direitos *Propostas* e o novo *Gerenciar modelos e blocos de texto*.
- Testes de CNPJ e do hash formato 2.

### Changed

- Código de verificação no **formato 2**: inclui empresa, blocos de texto e cargo. Aceites anteriores continuam conferidos no formato 1 (coluna `hash_formato`).
- "Solicitado por" passa a ser "Contato na empresa".
- Validação de itens compartilhada entre itens de proposta e de modelo.

### Fixed

- O README dizia que os outros perfis eram liberados em Administração > Perfis, mas faltava a aba de direitos do plugin.

## [1.0.0] - 2026-11-24

Primeira versão publicada. Reúne o que foi desenvolvido nas versões 0.x (abaixo), que não tiveram release própria.

### Added

- Ação automática diária **expirar** (Configurar > Ações automáticas): marca como *Expirada* as propostas *Enviada*, *Visualizada* ou *Com dúvida* cujo link já venceu.
- Botão **Duplicar**: cria um rascunho com os dados, condições e itens da proposta, emissão de hoje e o usuário atual como consultor. Não copia chamado vinculado, status, versão, link, respostas nem acessos.
- Testes PHPUnit (`composer test`) para subtotal, totais, hash dos valores aceitos, código de verificação e CPF.
- CI no GitHub Actions: `composer validate`, sintaxe de todos os arquivos e PHPUnit no PHP 8.2, 8.3 e 8.4; conferência de CRLF, da seção do CHANGELOG e do conteúdo do zip da release.

### Changed

- Cálculos e CPF saíram das classes ligadas ao GLPI para `Calculo` e `Cpf`, testáveis sem o GLPI. O hash dos valores aceitos não mudou (aceites antigos continuam conferindo). Os totais agora são arredondados a 2 casas depois da soma.
- README com instalação pela release, funcionalidades, segurança do link público e exemplo de Nginx para expor só as rotas públicas.

## [0.3.0]

### Added

- **Aceitar proposta** na página pública: nome completo, CPF (com conferência dos dígitos) e declaração. Grava data, IP, navegador, CPF cifrado com a chave do GLPI (e mascarado) e o SHA-256 dos valores aceitos; a página passa a mostrar "Proposta aceita" com o código de verificação.
- **Tenho dúvidas** na página pública: nome e mensagem; status *Com dúvida*.
- Aceite e dúvida viram acompanhamento **privado** no chamado vinculado, sem disparar as notificações do chamado.
- Notificações para o consultor: proposta aberta, aceita e com dúvida (Configurar > Notificações).
- Abas **Respostas do cliente** (CPF completo só para quem pode editar; indica se o código do aceite confere com os valores atuais) e **Acessos ao link** (data, IP e navegador de cada abertura).
- Limite de 10 envios por IP por hora (resposta 429), contado na tabela `glpi_plugin_propostas_tentativas` só com o hash do IP.

### Fixed

- Rotas só-POST de plugin não casam no GLPI 11.0.4 (o roteador de plugins compara tudo como GET). As rotas de aceite e dúvida aceitam qualquer método e conferem o POST no controller.
- Texto com bytes que não são UTF-8 válido (nome, mensagem, User-Agent) é limpo antes de gravar, em vez de causar erro 500.

## [0.2.0]

### Added

- Link público da proposta (`/plugins/propostas/p/{token}`), sem login: página própria (sem CSS/JS do GLPI) com resumo de valores, itens recorrentes com barra de proporção, implantação, condições comerciais e impressão/PDF. Envia `noindex` e `no-store`.
- Botões **Gerar link**, **Reabrir para edição** (nova versão, invalida o link) e **Cancelar proposta** na aba Proposta.
- Token aleatório de 256 bits: o banco guarda o SHA-256 para a busca e uma cópia cifrada com a chave do GLPI para o consultor copiar o link de novo. O link expira no fim do último dia de validade.
- Status *Visualizada* na primeira abertura pelo cliente (quem está logado no GLPI não conta).
- Configuração > Plug-ins > Propostas: endereço externo usado nos links.

### Changed

- Depois de enviada, a proposta fica travada: só os comentários mudam e os itens não podem ser incluídos, alterados ou removidos (inclusive por POST direto). Status e versão só mudam pelas ações.

## [0.1.0]

### Added

- Esqueleto do plugin para GLPI 11: menu **Ferramentas > Propostas**, lista com busca e formulário da proposta.
- Itens da proposta (recorrentes e pontuais) com quantidade, valor unitário, desconto, subtotal e totais.
- Direito `plugin_propostas_proposta`, concedido na instalação aos perfis que administram a configuração.
- Colunas `token_hash` e `token_expira`, reservadas para o link público.
- Colunas padrão na lista: ID, status, emissão, solicitante, consultor, chamado e última atualização.

### Fixed

- Proposta nova vinha com validade 1 e aviso prévio 0. Agora vem com versão 1, validade 15 e aviso prévio 60.
- Depois de criar uma proposta, o GLPI volta para ela, independentemente da preferência "voltar ao item criado".
- Reinstalar (`plugin:install --force`) falhava com "Duplicate entry" em `glpi_profilerights`. Agora o direito só é criado na primeira instalação e os ajustes feitos nos perfis são mantidos.

[Unreleased]: https://github.com/tsathler/glpi-propostas/compare/v1.9.3...HEAD
[1.9.3]: https://github.com/tsathler/glpi-propostas/compare/v1.9.2...v1.9.3
[1.9.2]: https://github.com/tsathler/glpi-propostas/compare/v1.9.1...v1.9.2
[1.9.1]: https://github.com/tsathler/glpi-propostas/compare/v1.9.0...v1.9.1
[1.9.0]: https://github.com/tsathler/glpi-propostas/compare/v1.8.0...v1.9.0
[1.8.0]: https://github.com/tsathler/glpi-propostas/compare/v1.7.5...v1.8.0
[1.7.5]: https://github.com/tsathler/glpi-propostas/compare/v1.7.4...v1.7.5
[1.7.4]: https://github.com/tsathler/glpi-propostas/compare/v1.7.3...v1.7.4
[1.7.3]: https://github.com/tsathler/glpi-propostas/compare/v1.7.2...v1.7.3
[1.7.2]: https://github.com/tsathler/glpi-propostas/compare/v1.7.1...v1.7.2
[1.7.1]: https://github.com/tsathler/glpi-propostas/compare/v1.7.0...v1.7.1
[1.7.0]: https://github.com/tsathler/glpi-propostas/compare/v1.6.0...v1.7.0
[1.6.0]: https://github.com/tsathler/glpi-propostas/compare/v1.5.1...v1.6.0
[1.5.1]: https://github.com/tsathler/glpi-propostas/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/tsathler/glpi-propostas/compare/v1.4.3...v1.5.0
[1.4.3]: https://github.com/tsathler/glpi-propostas/compare/v1.4.2...v1.4.3
[1.4.2]: https://github.com/tsathler/glpi-propostas/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/tsathler/glpi-propostas/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/tsathler/glpi-propostas/compare/v1.3.1...v1.4.0
[1.3.1]: https://github.com/tsathler/glpi-propostas/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/tsathler/glpi-propostas/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/tsathler/glpi-propostas/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/tsathler/glpi-propostas/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/tsathler/glpi-propostas/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/tsathler/glpi-propostas/releases/tag/v1.0.0
