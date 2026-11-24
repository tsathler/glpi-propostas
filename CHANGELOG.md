# Changelog

Todas as mudanças relevantes deste plugin ficam registradas aqui.
O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue o [SemVer](https://semver.org/lang/pt-BR/).

## [Unreleased]

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

[Unreleased]: https://github.com/tsathler/glpi-propostas/compare/v1.2.1...HEAD
[1.2.1]: https://github.com/tsathler/glpi-propostas/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/tsathler/glpi-propostas/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/tsathler/glpi-propostas/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/tsathler/glpi-propostas/releases/tag/v1.0.0
