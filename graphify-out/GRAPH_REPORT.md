# Graph Report - checkout-woo  (2026-08-03)

## Corpus Check
- 25 files · ~40,355 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 172 nodes · 170 edges · 25 communities (15 shown, 10 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 7 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d23e28e1`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- GVN_Custom_Fields
- GVN_Address_Validation
- GVN_Admin
- GVN_Checkout
- GVN_Order_Bump
- gvn_checkout_init
- What You Must Do When Invoked
- Passos
- /graphify
- 🛒 GVN Checkout for WooCommerce
- graphify reference: extra exports and benchmark
- graphify reference: query, path, explain
- graphify reference: add a URL and watch a folder
- graphify reference: commit hook and native CLAUDE.md integration
- graphify reference: incremental update and cluster-only
- graphify reference: GitHub clone and cross-repo merge
- graphify reference: transcribe video and audio
- AGENTS.md
- extraction-spec.md

## God Nodes (most connected - your core abstractions)
1. `GVN_Custom_Fields` - 23 edges
2. `GVN_Address_Validation` - 17 edges
3. `GVN_Admin` - 14 edges
4. `What You Must Do When Invoked` - 12 edges
5. `GVN_Checkout` - 11 edges
6. `/graphify` - 10 edges
7. `GVN_Order_Bump` - 9 edges
8. `Passos` - 9 edges
9. `graphify reference: extra exports and benchmark` - 8 edges
10. `gvn_checkout_init()` - 7 edges

## Surprising Connections (you probably didn't know these)
- `gvn_checkout_init()` --calls--> `GVN_Address_Validation`  [INFERRED]
  gvn-checkout.php → includes/class-gvn-address-validation.php
- `gvn_checkout_init()` --calls--> `GVN_Admin`  [INFERRED]
  gvn-checkout.php → includes/class-gvn-admin.php
- `gvn_checkout_init()` --calls--> `GVN_Checkout`  [INFERRED]
  gvn-checkout.php → includes/class-gvn-checkout.php
- `gvn_checkout_init()` --calls--> `GVN_Custom_Fields`  [INFERRED]
  gvn-checkout.php → includes/class-gvn-custom-fields.php
- `gvn_checkout_init()` --calls--> `GVN_Order_Bump`  [INFERRED]
  gvn-checkout.php → includes/class-gvn-order-bump.php

## Import Cycles
- None detected.

## Communities (25 total, 10 thin omitted)

### Community 11 - "What You Must Do When Invoked"
Cohesion: 0.13
Nodes (15): Part A - Structural extraction for code files, Part B - Semantic extraction (parallel subagents), Part C - Merge AST + semantic into final extraction, Step 0 - GitHub repos and multi-path merge (only if a URL or several paths), Step 1 - Ensure graphify is installed, Step 2.5 - Video and audio (only if video files detected), Step 2 - Detect files, Step 3 - Extract entities and relationships (+7 more)

### Community 12 - "Passos"
Cohesion: 0.13
Nodes (14): 1. Confirmar que está na raiz do repositório, 2. Instalar Graphify se necessário, 3. Instalar a skill local para Codex, 4. Gerar o grafo somente do código, 5. Finalizar clustering, relatório e visualização, 6. Ativar uso automático no Codex, 7. Instalar atualização automática via Git hook, 8. Validar a instalação (+6 more)

### Community 13 - "/graphify"
Cohesion: 0.20
Nodes (9): For /graphify add and --watch, For /graphify query, For the commit hook and native CLAUDE.md integration, For --update and --cluster-only, /graphify, Honesty Rules, Interpreter guard for subcommands, Usage (+1 more)

### Community 14 - "🛒 GVN Checkout for WooCommerce"
Cohesion: 0.20
Nodes (9): 1. Criar a Página de Checkout, 2. Configurar o Plugin, 🚀 Como Instalar, 🛠️ Como Usar, 📸 Demonstração do Checkout, 🛒 GVN Checkout for WooCommerce, 📄 Licença, ✨ Principais Recursos (+1 more)

### Community 15 - "graphify reference: extra exports and benchmark"
Cohesion: 0.22
Nodes (8): graphify reference: extra exports and benchmark, Step 6b - Wiki (only if --wiki flag), Step 7 - Neo4j export (only if --neo4j or --neo4j-push flag), Step 7a - FalkorDB export (only if --falkordb or --falkordb-push flag), Step 7b - SVG export (only if --svg flag), Step 7c - GraphML export (only if --graphml flag), Step 7d - MCP server (only if --mcp flag), Step 8 - Token reduction benchmark (only if total_words > 5000)

### Community 16 - "graphify reference: query, path, explain"
Cohesion: 0.33
Nodes (5): For /graphify explain, For /graphify path, graphify reference: query, path, explain, Step 0 — Constrained query expansion (REQUIRED before traversal), Step 1 — Traversal

### Community 17 - "graphify reference: add a URL and watch a folder"
Cohesion: 0.50
Nodes (3): For /graphify add, For --watch, graphify reference: add a URL and watch a folder

### Community 18 - "graphify reference: commit hook and native CLAUDE.md integration"
Cohesion: 0.50
Nodes (3): For git commit hook, For native CLAUDE.md integration, graphify reference: commit hook and native CLAUDE.md integration

### Community 19 - "graphify reference: incremental update and cluster-only"
Cohesion: 0.50
Nodes (3): For --cluster-only, For --update (incremental re-extraction), graphify reference: incremental update and cluster-only

## Knowledge Gaps
- **61 isolated node(s):** `Usage`, `What graphify is for`, `Step 0 - GitHub repos and multi-path merge (only if a URL or several paths)`, `Step 1 - Ensure graphify is installed`, `Step 2 - Detect files` (+56 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `gvn_checkout_init()` connect `gvn_checkout_init` to `GVN_Custom_Fields`, `GVN_Address_Validation`, `GVN_Admin`, `GVN_Checkout`, `GVN_Order_Bump`?**
  _High betweenness centrality (0.143) - this node is a cross-community bridge._
- **Why does `GVN_Custom_Fields` connect `GVN_Custom_Fields` to `GVN_Address_Validation`, `GVN_Admin`, `gvn_checkout_init`?**
  _High betweenness centrality (0.096) - this node is a cross-community bridge._
- **Why does `GVN_Address_Validation` connect `GVN_Address_Validation` to `gvn_checkout_init`?**
  _High betweenness centrality (0.073) - this node is a cross-community bridge._
- **Are the 3 inferred relationships involving `GVN_Custom_Fields` (e.g. with `gvn_checkout_init()` and `.validate_address_fields()`) actually correct?**
  _`GVN_Custom_Fields` has 3 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Usage`, `What graphify is for`, `Step 0 - GitHub repos and multi-path merge (only if a URL or several paths)` to the rest of the system?**
  _61 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `GVN_Custom_Fields` be split into smaller, more focused modules?**
  _Cohesion score 0.10476190476190476 - nodes in this community are weakly interconnected._
- **Should `GVN_Address_Validation` be split into smaller, more focused modules?**
  _Cohesion score 0.11764705882352941 - nodes in this community are weakly interconnected._