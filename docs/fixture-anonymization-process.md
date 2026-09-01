# Processo de Anonimização de Fixtures e Dados de Teste

Este documento estabelece o protocolo obrigatório para criação, higienização e validação de fixtures de teste no projeto **GVN Checkout for WooCommerce**, em conformidade com as diretrizes de segurança, LGPD e integridade do repositório.

---

## 1. Princípio Fundamental

**Zero PII em Controle de Versão:** É terminantemente proibido versionar no repositório qualquer dado pessoal real (PII - *Personally Identifiable Information*), credenciais de produção, chaves de API, senhas, tokens de sessão ou registros financeiros de clientes.

Preferência obrigatória: **Fixtures inteiramente sintéticas** geradas a partir do schema formal devem ser sempre priorizadas sobre exportações de bancos reais.

---

## 2. Tabela de Substituição Padronizada

Quando dados de estruturas de banco forem necessários para reproduzir cenários complexos, aplique a seguinte substituição antes de qualquer armazenamento:

| Categoria | Dado Original | Padrão Sintético Obrigatório |
|---|---|---|
| **Nomes** | Nome real do cliente | Nomes fictícios padronizados (ex: `Maria Silva`, `João Ferreira`, `Cliente Teste`) |
| **E-mails** | E-mail de cliente | Domínios reservados para testes RFC 2606: `@exemplo.teste`, `@example.com`, `@teste.local` |
| **Telefones** | Telefone / Celular real | Faixas de teste brasileiras: `(11) 98765-4321`, `(21) 99876-5432`, `(00) 0000-0000` |
| **CPF** | CPF de comprador | CPFs sintéticos com dígitos verificadores válidos de teste (ex: `111.444.777-35`, `222.555.888-96`) |
| **CNPJ** | CNPJ de empresa | CNPJs sintéticos de teste (ex: `11.222.333/0001-81`, `00.000.000/0001-91`) |
| **Endereço** | Residência real | Logradouros públicos conhecidos de referência (ex: `Avenida Paulista, 1000`, `Rua do Ouvidor, 50`) |
| **CEP** | CEP real de entrega | CEPs genéricos de agências centrais ou logradouros modelo (ex: `01310-100`, `20040-030`) |
| **IPs** | IP de acesso | Faixas reservadas para documentação RFC 5737 (`192.0.2.1`, `198.51.100.1`, `203.0.113.1`) ou `127.0.0.1` |
| **Order Keys** | Chave real de pedido | Chaves geradas sintéticas: `wc_order_test_5f8a9b2c1d3e` |
| **Tokens / Segredos** | Credenciais ou senhas | Sequências sintéticas neutras: `sandbox_test_token_xyz_123` |

---

## 3. Envelope Obrigatório de Metadados da Fixture

Toda fixture JSON versionada em `tests/fixtures/` deve conter a seção `metadata`:

```json
{
  "metadata": {
    "fixture_id": "identificador-unico-kebab-case",
    "fixture_format_version": 1,
    "source_plugin_version": "1.13.x",
    "classification": "synthetic",
    "production_export": false,
    "anonymization": {
      "performed": true,
      "method": "Descrição da técnica de geração ou sanitização utilizada",
      "personal_data_present": false,
      "secrets_present": false
    }
  },
  "data": { ... }
}
```

---

## 4. Checklist de Verificação Pré-Commit

Antes de commitar qualquer arquivo de fixture:

- [ ] Executar scanner automatizado de expressões regulares para detectar e-mails corporativos/pessoais reais.
- [ ] Verificar ausência de strings sensíveis como `sk_live_`, `Bearer `, senhas e hashes bcrypt/MD5 reais.
- [ ] Confirmar que todos os CPFs/CNPJs presentes estão na lista aprovada de documentos sintéticos de teste.
- [ ] Validar que o JSON está formatado com 2 espaços e passa no validador estrutural (`json.load`).
- [ ] Confirmar que o arquivo está localizado no subdiretório correto (`tests/fixtures/options/` ou `tests/fixtures/seeds/`).
