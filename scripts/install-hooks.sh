#!/usr/bin/env bash
# Instala os git hooks do projeto em .git/hooks/ como symlinks.
# Rodar uma vez após clonar o repositório:
#     bash scripts/install-hooks.sh
set -e

REPO_ROOT="$(git rev-parse --show-toplevel)"
HOOKS_SRC="$REPO_ROOT/scripts/git-hooks"
HOOKS_DST="$REPO_ROOT/.git/hooks"

if [ ! -d "$HOOKS_DST" ]; then
    echo "Diretório .git/hooks não encontrado — este script precisa rodar dentro do repositório." >&2
    exit 1
fi

for hook in "$HOOKS_SRC"/*; do
    name="$(basename "$hook")"
    target="$HOOKS_DST/$name"
    chmod +x "$hook"
    ln -sfn "../../scripts/git-hooks/$name" "$target"
    echo "instalado: $target -> ../../scripts/git-hooks/$name"
done
