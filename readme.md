# 🛒 GVN Checkout for WooCommerce

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg?style=flat-square&logo=wordpress)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple.svg?style=flat-square&logo=woocommerce)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0+-green.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)

O **GVN Checkout for WooCommerce** substitui o fluxo de checkout padrão e monótono do WooCommerce por uma experiência de compra moderna, de alta conversão, responsiva e com um design premium totalmente otimizado para o mercado brasileiro.

---

## 📸 Demonstração do Checkout

Abaixo está uma demonstração visual da interface limpa e moderna gerada pelo plugin:

![Interface do GVN Checkout](print.png)

---

## ✨ Principais Recursos

*   **⚡ Layout Otimizado para Conversão:** Design de página única (*one-page checkout*) limpo e focado no essencial para reduzir a taxa de abandono de carrinho.
*   **📱 Totalmente Responsivo:** Experiência de compra impecável tanto em dispositivos móveis quanto em computadores.
*   **🎨 Customização Completa:** Controle total de cores (cor primária, botões, cabeçalho e badges) diretamente nas configurações do painel.
*   **🛠️ Formulário Simplificado:** Foco apenas nos campos obrigatórios para o mercado brasileiro (Nome, Sobrenome, CPF, Celular, E-mail e Endereço).
*   **⚡ Autocompletar e Validação de CEP:**
    *   Preenchimento automático do endereço via consulta server-side ao ViaCEP.
    *   Cache inteligente via Transients do WordPress (válido por 7 dias) para evitar requisições repetidas e acelerar o checkout.
    *   Validação de consistência entre CEP e Estado (UF) para evitar erros de entrega.
    *   Campos de endereço travados após preenchimento automático para evitar digitação incorreta (com opção de edição manual caso necessário).
*   **🏷️ Cupom de Desconto Dinâmico:** Seção de cupom com toggle moderno que não distrai o cliente.
*   **🛍️ Order Bump Avançado:** Ofereça produtos adicionais com desconto diretamente no checkout e aumente o Ticket Médio (*AOV*) da sua loja com apenas 1 clique.
*   **🛡️ Máscaras Automáticas:** Formatação inteligente em tempo real nos campos de CPF e Celular.

---

## 🚀 Como Instalar

1.  Faça o download do arquivo compactado do plugin (`checkout.zip`).
2.  No painel do seu WordPress, vá em **Plugins > Adicionar Novo > Enviar Plugin**.
3.  Escolha o arquivo `.zip` e clique em **Instalar Agora**.
4.  Após a instalação, clique em **Ativar Plugin**.

---

## 🛠️ Como Usar

### 1. Criar a Página de Checkout
O plugin funciona através de um shortcode nativo.
1.  Vá em **Páginas > Adicionar Nova** no WordPress.
2.  Insira o shortcode abaixo no conteúdo da página:
    ```text
    [gvn-checkout]
    ```
3.  Publique a página.

> [!TIP]
> Para que os clientes sejam redirecionados corretamente, lembre-se de configurar esta nova página como a página oficial de checkout em **WooCommerce > Configurações > Avançado > Página de checkout**.

### 2. Configurar o Plugin
Todas as opções visuais e de comportamento do checkout podem ser customizadas de forma simples:
1.  Vá em **WooCommerce > Configurações**.
2.  Clique na aba **GVN Checkout**.
3.  Personalize as seguintes seções de acordo com a identidade visual da sua marca:
    *   **Identidade Visual:** Defina a Cor Primária, Cor dos Botões e Texto do Botão de Finalização.
    *   **Cabeçalho (Header):** Ative ou desative o cabeçalho personalizado, defina o título (ex: nome da sua loja), o texto da badge de segurança e as cores do topo.
    *   **Order Bump:** Escolha o produto que deseja oferecer como oferta exclusiva, configure o título atrativo, a descrição da oferta, o preço especial e o texto de chamada para ação (CTA).

---

## 🔒 Requisitos do Sistema

*   **WordPress:** 6.0 ou superior.
*   **WooCommerce:** 7.0 ou superior.
*   **PHP:** 7.4 ou superior.
*   **Gateways de Pagamento:** Compatível com os principais gateways de pagamento do mercado brasileiro que operam de forma nativa na finalização do WooCommerce.

---

## 📄 Licença

Este plugin é distribuído sob a licença **GPL-2.0+**. Sinta-se livre para usá-lo, modificá-lo e compartilhá-lo.
