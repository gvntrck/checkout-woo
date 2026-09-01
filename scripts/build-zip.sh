#!/usr/bin/env bash
set -e

PLUGIN_SLUG="gvn-checkout"
BUILD_DIR="/tmp/${PLUGIN_SLUG}-build"
OUTPUT_DIR="./dist"
ZIP_FILE="${OUTPUT_DIR}/${PLUGIN_SLUG}.zip"

echo "==> Gerando pacote de release reprodutível do ${PLUGIN_SLUG}..."

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/${PLUGIN_SLUG}"
mkdir -p "$OUTPUT_DIR"

# Copia apenas arquivos de runtime
cp -r assets html includes templates languages gvn-checkout.php readme.txt readme.md uninstall.php "$BUILD_DIR/${PLUGIN_SLUG}/" 2>/dev/null || true

# Remove artefatos temporários
find "$BUILD_DIR" -name ".DS_Store" -delete 2>/dev/null || true
find "$BUILD_DIR" -name "Thumbs.db" -delete 2>/dev/null || true
find "$BUILD_DIR" -name "*~" -delete 2>/dev/null || true

# Empacotamento via PHP ZipArchive (portátil e determinístico)
php -r "
\$zip = new ZipArchive();
\$zipFile = '$ZIP_FILE';
if (\$zip->open(\$zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, 'Falha ao criar arquivo ZIP' . PHP_EOL);
    exit(1);
}
\$sourceDir = '$BUILD_DIR';
\$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(\$sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach (\$files as \$file) {
    if (!\$file->isDir()) {
        \$filePath = \$file->getRealPath();
        \$relativePath = substr(\$filePath, strlen(\$sourceDir) + 1);
        \$zip->addFile(\$filePath, str_replace('\\\\', '/', \$relativePath));
    }
}
\$zip->close();
"

rm -rf "$BUILD_DIR"

echo "==> Pacote gerado com sucesso em: ${ZIP_FILE}"
sha256sum "$ZIP_FILE"
