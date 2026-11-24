# Desenho do plugin Propostas

Referência: uma página de proposta comercial com link público, resumo de valores, itens com desconto, condições comerciais e aceite pelo cliente (nome, CPF, declaração e código de verificação dos valores).

## Decisões

| Assunto | Decisão |
|---|---|
| Onde ficam os dados | Objeto próprio do plugin, no menu **Ferramentas > Propostas** |
| Quem aprova | Pessoa **sem login** no GLPI, por link com token |
| Onde roda a página pública | **No mesmo servidor do GLPI**, como rota pública do próprio plugin |
| Exposição | O GLPI continua **só interno**. Um `server` próprio no Nginx expõe **apenas** a rota pública do plugin e devolve 404 para todo o resto |
| Versão do GLPI | 11.x (PHP 8.4) |

Por que não um app separado: como fica no mesmo servidor e no mesmo banco, ele não isolaria quase nada. Teria só o custo de mais um sistema e de uma credencial de API. O isolamento real vem do Nginx, que libera um caminho só.

## Modelo de dados

- `glpi_plugin_propostas_propostas`: cabeçalho, status, versão, consultor, chamado vinculado, emissão, validade, aviso prévio, condições, `token_hash` (SHA-256 do token; o token em si não é guardado) e `token_expira`.
- `glpi_plugin_propostas_propostaitems`: tipo (recorrente/pontual), descrição, quantidade, valor unitário, desconto %, ordem.
- Etapa 3: `..._aceites` (nome, CPF criptografado com a chave do GLPI, IP, user-agent, data, hash dos valores) e `..._acessos` (cada abertura do link).

Status: Rascunho → Enviada → Visualizada → Aceita / Com dúvida / Expirada / Cancelada.

## Etapas

1. **Esqueleto** (feito): menu, lista, formulário, itens e totais.
2. **Link público** (feito, 0.2.0): botão "Gerar link", rota pública `/plugins/propostas/p/{token}` com `#[SecurityStrategy('no_check')]` (`src/Controller/PublicController.php`), página só de leitura com o layout da referência e `noindex`. Proposta enviada fica travada: mudar valores exige "Reabrir para edição", que gera nova versão e invalida o link anterior.
   - Token: 32 bytes aleatórios em base64url (43 caracteres). O banco guarda `token_hash` (SHA-256, usado na busca) e `token_cifrado` (`GLPIKey`, para o consultor copiar o link de novo).
   - Expira às 23:59:59 do dia emissão + validade. Link expirado responde 410; token inexistente ou revogado, 404 (sem dizer qual).
   - Abrir o link estando logado no GLPI não muda o status para *Visualizada*.
   - Os links usam o endereço de **Configuração > Plug-ins > Propostas** ou, em branco, o "URL de acesso" do GLPI. Em ambiente clonado, confira se esse endereço não aponta para outro servidor.
   - Ao atualizar o plugin, rode `bin/console cache:clear` para a rota nova ser carregada.
3. **Aceite e dúvida:** formulário com nome, CPF (com conferência dos dígitos) e declaração; código de verificação = SHA-256 dos itens e totais da versão; dúvida vira acompanhamento no chamado vinculado; registro de acessos; notificações nativas (proposta aberta, aceita, dúvida).
4. **Acabamento:** expiração automática (ação automática), impressão/PDF, limite de tentativas por IP.

## Nginx (rascunho, ainda não aplicado)

Um `server` separado para o acesso externo, que só repassa a rota pública. O `(aceitar|duvida)` já prevê as rotas da etapa 3:

```nginx
server {
    listen 443 ssl;
    server_name <nome-externo>;

    root /var/www/html/glpi/public;

    location ~ ^/plugins/propostas/p/[A-Za-z0-9_-]{43}(/(aceitar|duvida))?$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location / { return 404; }
}
```

A página pública não pode depender dos CSS e JS do GLPI (ficariam bloqueados). Ela leva o próprio CSS embutido.
