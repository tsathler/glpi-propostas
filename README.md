# Propostas (plugin para GLPI 11)

Propostas comerciais dentro do GLPI: cabeçalho, itens recorrentes e pontuais com quantidade, valor unitário e desconto, e totais calculados. Nas próximas versões, o cliente poderá abrir a proposta por um link público e aceitá-la ou enviar dúvidas, sem login no GLPI.

## Requisitos

- GLPI 11.0 ou superior (testado no 11.0.4)
- PHP 8.2 ou superior

## Instalação

```bash
cd /var/www/html/glpi/plugins
# copie a pasta "propostas" para cá (o nome da pasta precisa ser exatamente "propostas")
sudo -u www-data php ../bin/console plugin:install propostas --username=<seu login>
sudo -u www-data php ../bin/console plugin:activate propostas
```

Depois de instalar, os perfis que administram a configuração recebem acesso total. Para liberar outros perfis, use **Administração > Perfis**. Saia e entre de novo no GLPI para o menu aparecer em **Ferramentas > Propostas**.

Ao atualizar, rode também `sudo -u www-data php ../bin/console cache:clear`, para o GLPI carregar a rota pública do plugin.

## O que já funciona (0.3.0)

- Menu **Ferramentas > Propostas**, com lista e busca
- Cadastro da proposta: título, solicitante, consultor, chamado vinculado, emissão, validade, aviso prévio e condições comerciais
- Aba **Itens**: incluir e remover linhas recorrentes ou pontuais, com subtotal e totais
- **Link para o cliente**: gerar, copiar, reabrir como nova versão ou cancelar. A página pública não pede login e mostra a proposta no formato de proposta comercial
- Proposta enviada fica travada para edição
- O cliente **aceita** (nome, CPF e declaração, com código de verificação dos valores) ou envia **dúvidas** pela própria página; as respostas viram acompanhamento privado no chamado vinculado
- Abas **Respostas do cliente** e **Acessos ao link**
- Notificações para o consultor: proposta aberta, aceita e com dúvida (exigem as notificações por e-mail ativas no GLPI)
- Histórico de alterações

## Expor o link para fora da rede

O GLPI pode continuar só na rede interna. Crie um `server` no Nginx para o endereço externo que libere **apenas** a rota pública do plugin e informe esse endereço em **Configuração > Plug-ins > Propostas**. Exemplo em [docs/desenho.md](docs/desenho.md).

## Roteiro

Veja [docs/desenho.md](docs/desenho.md).

## Licença

GPL-3.0-or-later. Veja [LICENSE](LICENSE).
