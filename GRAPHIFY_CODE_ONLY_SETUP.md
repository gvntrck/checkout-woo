# Graphify Code-Only — Setup para Codex CLI

> Instruções para o agente executar na raiz do repositório.

## Objetivo

Instalar e configurar o Graphify para o **Codex CLI**, usando **somente análise local de código** e deixando a atualização automática via Git hook.

Regras:

- Não configurar API keys.
- Não enviar documentação, PDFs ou imagens para LLM.
- Usar sempre `--code-only`.
- Deixar o Graphify integrado ao Codex.
- Instalar o hook do Graphify para atualizar o grafo após operações do Git.
- Executar os comandos de forma idempotente: se algo já estiver instalado/configurado, não quebrar nem duplicar configuração.

## Passos

### 1. Confirmar que está na raiz do repositório

```bash
git rev-parse --show-toplevel
```

Entre na raiz retornada antes de continuar.

### 2. Instalar Graphify se necessário

Primeiro verifique:

```bash
command -v graphify
```

Se não existir, prefira `uv`:

```bash
uv tool install graphifyy
```

Se `uv` não estiver disponível e `pipx` estiver:

```bash
pipx install graphifyy
```

Confirme:

```bash
graphify --version
```

### 3. Instalar a skill local para Codex

```bash
graphify install --project --platform codex
```

### 4. Gerar o grafo somente do código

```bash
graphify . --code-only
```

**Nunca substitua este comando por `graphify .` neste setup**, pois isso pode tentar processar documentação e exigir uma API key.

### 5. Finalizar clustering, relatório e visualização

```bash
graphify . --cluster-only
```

### 6. Ativar uso automático no Codex

```bash
graphify codex install
```

Isso adiciona as orientações necessárias ao `AGENTS.md` e configura a integração do Codex.

### 7. Instalar atualização automática via Git hook

```bash
graphify hook install
```

O hook mantém o grafo atualizado após operações do Git, como commits.

Valide:

```bash
graphify hook status
```

### 8. Validar a instalação

Verifique:

```bash
ls -la graphify-out
```

O projeto deve possuir pelo menos o grafo gerado. Quando disponíveis, também devem existir:

```text
graphify-out/
├── graph.json
├── GRAPH_REPORT.md
└── graph.html
```

Teste uma consulta:

```bash
graphify query "quais são os principais componentes deste projeto?"
```

## Atualização durante desenvolvimento

O hook cuida da atualização após operações do Git, mas o grafo pode ficar desatualizado durante uma sessão antes do commit.

Quando houver mudanças estruturais importantes e for necessário consultar o grafo antes de commitar, rode:

```bash
graphify . --update --code-only
```

Não é necessário rodar isso após alterações pequenas de texto, CSS ou ajustes triviais.

## Resultado esperado

Ao terminar:

- Graphify instalado.
- Skill do Graphify disponível para o Codex.
- Integração do Codex configurada.
- `AGENTS.md` orientando o Codex a consultar o grafo quando apropriado.
- Grafo criado somente a partir do código.
- Git hook do Graphify instalado.
- Nenhuma API key configurada ou necessária.

## Uso diário

O usuário pode continuar usando o Codex normalmente.

Não é necessário invocar `$graphify` em toda tarefa.

Use o Graphify principalmente para:

- investigar bugs que atravessam vários arquivos;
- localizar dependências;
- entender fluxo entre classes e funções;
- refatorações;
- perguntas de arquitetura.

Para tarefas simples, o Codex pode trabalhar normalmente.