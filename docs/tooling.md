# Guia de Ferramentas e Portas de Qualidade (Tooling)

Este documento descreve o ambiente de desenvolvimento, os scripts automatizados e as ferramentas de verificação de qualidade do **GVN Checkout for WooCommerce** (Fase 1 do plano de evolução).

---

## 1. Comandos Disponíveis via Composer

No ambiente WSL, Linux ou CI com PHP 7.4+ e Composer instalados:

| Comando | Descrição |
|---|---|
| `composer run syntax` | Executa `php -l` em todos os arquivos PHP do projeto. |
| `composer run lint` | Executa o PHPCS com os padrões do WordPress e WooCommerce. |
| `composer run lint:fix` | Executa o PHPCBF para auto-formatação e correção de espaçamento/indentações. |
| `composer run analyse` | Executa a análise estática do PHPStan com a extensão `phpstan-wordpress`. |
| `composer test` | Executa a suite de testes unitários isolados via PHPUnit. |
| `composer run check` | Executa o pipeline local completo: syntax + lint + analyse + test. |
| `composer run build:zip` | Gera o pacote de release reproduzível em `dist/gvn-checkout.zip`. |

---

## 2. Execução no Ambiente Windows com WSL

No Windows 11 com WSL instalado:

```bash
# Executar a verificação completa no WSL
wsl bash -c "cd /mnt/c/Users/Administrador/Documents/antigravity/checkouts/checkout-woo && bash scripts/syntax-check.sh"
```

---

## 3. Estrutura dos Arquivos de Configuração

- `composer.json`: Definição de dependências dev, autoload PSR-4 e scripts unificados.
- `phpcs.xml.dist`: Regras do WordPress Coding Standards e compatibilidade PHP 7.4+.
- `phpstan.neon.dist`: Análise estática focada no núcleo WordPress/WooCommerce.
- `phpunit.xml.dist`: Configuração de suites (`unit`, `integration`, `security-smoke`).
- `.github/workflows/ci.yml`: Pipeline de integração contínua (matriz PHP 7.4 a 8.3).
