# Image utilisée par Railway. L'image officielle PHP inclut déjà pdo_sqlite.
FROM php:8.3-cli

WORKDIR /app
COPY . .

# Serveur PHP sur le port fourni par Railway.
# La base vit sur le volume (variable DB_PATH) : un redéploiement ne la touche pas.
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8080} -t public"]
