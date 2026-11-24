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

## O que já funciona (0.1.0)

- Menu **Ferramentas > Propostas**, com lista e busca
- Cadastro da proposta: título, versão, status, solicitante, consultor, chamado vinculado, emissão, validade, aviso prévio e condições comerciais
- Aba **Itens**: incluir e remover linhas recorrentes ou pontuais, com subtotal e totais
- Histórico de alterações

## Roteiro

Veja [docs/desenho.md](docs/desenho.md).

## Licença

GPL-3.0-or-later. Veja [LICENSE](LICENSE).
