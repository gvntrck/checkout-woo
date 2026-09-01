#!/usr/bin/env bash
set -e

echo "==> Executando checagem de sintaxe PHP (php -l)..."
ERRORS=0

for file in $(find . -type f -name "*.php" ! -path "./vendor/*" ! -path "./node_modules/*"); do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "[ERRO] Erro de sintaxe detectado em: $file"
        php -l "$file"
        ERRORS=$((ERRORS + 1))
    fi
done

if [ $ERRORS -eq 0 ]; then
    echo "==> Todos os arquivos PHP passaram na checagem de sintaxe com sucesso."
    exit 0
else
    echo "==> Falha: $ERRORS arquivo(s) com erro de sintaxe."
    exit 1
fi
