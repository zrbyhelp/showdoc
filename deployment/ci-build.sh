#!/usr/bin/env bash
set -euo pipefail

repo_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_dir"

image_file=.showdoc-ci-image
rm -f -- "$image_file"

upstream_frontend_tree=767a33eb2070a46eed36e5036ee6d27957fd84ab
frontend_tree=$(git rev-parse HEAD:web_src)
if [[ "$frontend_tree" != "$upstream_frontend_tree" ]]; then
  npm ci --prefix web_src
  npm run build --prefix web_src
fi

revision=$(git rev-parse --short=12 HEAD)
image="xssg/showdoc:$revision"
base_version=$(node -p 'require("./composer.json").version')
build_version="$base_version-g$revision"

docker build --platform linux/amd64 \
  --file deployment/Dockerfile \
  --build-arg "SHOWDOC_BUILD_VERSION=$build_version" \
  --tag "$image" .

printf '%s\n' "$image" > "$image_file"
