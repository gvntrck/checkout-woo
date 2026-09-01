# Governança de Gates e Registro de Evidências

Este documento define os papéis de aprovação para os gates de qualidade (P0/P1) e a política de arquivamento de evidências do projeto **GVN Checkout for WooCommerce** (Tarefa F0.20).

---

## 1. Responsabilidades e Papéis

| Nível do Gate | Descrição | Critério de Aprovação | Responsável |
|---|---|---|---|
| **Gate P0 (Crítico)** | Integridade financeira, criação de pedidos, cálculo de totais, dados de pagamento e conformidade fiscal | Zero divergência monetária, 100% dos testes da matriz aprovados em sandbox e ausência de bugs abertos | Tech Lead / Arquiteto do Projeto |
| **Gate P1 (Alto)** | Compatibilidade com gateways homologados, migrações de opções e integridade de campos brasileiros | Migração idempotente comprovada em fixtures e todos os fluxos de gateway verificados | Desenvolvedor Responsável / QA Lead |
| **Gate P2 (Médio)** | Ajustes visuais, experiência administrativa e otimizações de performance | Conformidade com budgets de performance e acessibilidade WCAG 2.2 AA | Equipe de Desenvolvimento |

---

## 2. Local e Padrão de Arquivamento de Evidências

Todas as evidências de teste, relatórios e logs anonimizados devem ser versionados e organizados na seguinte estrutura:

- `docs/evidence/`
  - `syntax/`: Relatórios de verificação de sintaxe e lint.
  - `fixtures/`: Validação estrutural de fixtures de options e seed.
  - `gateways/`: Fichas de homologação sandbox por gateway e versão (sem credenciais ou PII).
  - `e2e/`: Relatórios e logs de execuções automatizadas e manuais.
  - `performance/`: Relatórios de budgets e medições de TTFB / queries.

---

## 3. Protocolo de Aprovação de Release

1. Nenhuma release candidata (F15) pode ser promovida para produção com itens pendentes nos Gates P0 ou P1.
2. Toda homologação de gateway deve conter evidência formal arquivada com data, versão exata e hash do commit testado.
