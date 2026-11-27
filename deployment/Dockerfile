FROM star7th/showdoc:v3.9.8

ARG SHOWDOC_BUILD_VERSION

COPY composer.json composer.lock index.php mcp.php favicon.ico robots.txt .htaccess /showdoc_data/html/
COPY web_src/index.html /showdoc_data/html/web_src/index.html
COPY Sqlite/.user.ini Sqlite/deny.php /showdoc_data/html/Sqlite/
COPY --chmod=0755 server/bin/anydoc /usr/local/bin/anydoc
COPY deployment/initialize.php /usr/local/lib/showdoc-initialize.php

# Stage source without carrying a developer database, uploads, or runtime state into the image.
RUN --mount=type=bind,source=server,target=/tmp/fork-server \
    --mount=type=bind,source=web,target=/tmp/fork-web \
    --mount=type=bind,source=install,target=/tmp/fork-install \
    --mount=type=bind,source=Public,target=/tmp/fork-public \
    rsync -a --delete --exclude='.env' --exclude='.env.*' --exclude='Runtime/' \
      /tmp/fork-server/ /showdoc_data/html/server/ \
    && rsync -a --delete /tmp/fork-web/ /showdoc_data/html/web/ \
    && rsync -a --delete --exclude='install.lock' /tmp/fork-install/ /showdoc_data/html/install/ \
    && rsync -a --delete --exclude='Uploads/' /tmp/fork-public/ /showdoc_data/html/Public/

RUN if [ -n "$SHOWDOC_BUILD_VERSION" ]; then \
      php -r '$file = "/showdoc_data/html/composer.json"; \
        $config = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); \
        $config["version"] = $argv[1]; \
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); \
        if (file_put_contents($file, $json . PHP_EOL) === false) { exit(1); }' "$SHOWDOC_BUILD_VERSION"; \
    fi
