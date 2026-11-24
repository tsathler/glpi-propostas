# Changelog

Todas as mudanças relevantes deste plugin ficam registradas aqui.
O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue o [SemVer](https://semver.org/lang/pt-BR/).

## [Unreleased]

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
