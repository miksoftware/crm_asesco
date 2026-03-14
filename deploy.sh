#!/bin/bash

PROJECT_NAME="crm"

# ============================================
# Script de Deploy Automatico para Laravel
# ============================================

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PROJECT_DIR="$SCRIPT_DIR"
SRC_DIR="$PROJECT_DIR/src"

echo -e "${BLUE}=========================================="
echo "  Deploy Laravel: $PROJECT_NAME"
echo "==========================================${NC}"
echo ""

cd "$SRC_DIR"

# ==========================================
# 1. Git Pull
# ==========================================
echo -e "${YELLOW}[1/8] Descargando cambios desde Git...${NC}"
git stash --quiet 2>/dev/null || true
BRANCH=$(git rev-parse --abbrev-ref HEAD)

if git pull origin "$BRANCH" 2>&1; then
    echo -e "${GREEN}OK Cambios descargados desde rama '$BRANCH'${NC}"
else
    echo -e "${RED}Error al descargar cambios${NC}"
    exit 1
fi

LAST_COMMIT=$(git log -1 --pretty=format:'%h - %s (%ar) por %an')
echo -e "${BLUE}    Ultimo commit: $LAST_COMMIT${NC}"
echo ""

# ==========================================
# 2. Composer Install
# ==========================================
echo -e "${YELLOW}[2/8] Composer install...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5
echo -e "${GREEN}OK Dependencias actualizadas${NC}"
echo ""

# ==========================================
# 3. Compilar assets + dependencias Node.js
# ==========================================
echo -e "${YELLOW}[3/8] Compilando assets...${NC}"
if [ -f "$SRC_DIR/package.json" ]; then
    docker exec -w /var/www/html ${PROJECT_NAME}_php npm install 2>&1 | tail -3
    docker exec -w /var/www/html ${PROJECT_NAME}_php npm run build 2>&1 | tail -5
    echo -e "${GREEN}OK Assets compilados${NC}"

    # Verificar socket.io-client para evolution-listener
    if docker exec -w /var/www/html ${PROJECT_NAME}_php node -e "require('socket.io-client'); console.log('OK')" 2>/dev/null | grep -q "OK"; then
        echo -e "${GREEN}OK socket.io-client disponible${NC}"
    else
        echo -e "${YELLOW}socket.io-client no disponible, reinstalando...${NC}"
        docker exec -w /var/www/html ${PROJECT_NAME}_php npm install socket.io-client 2>&1 | tail -3
    fi
else
    echo -e "${BLUE}Sin package.json, saltando${NC}"
fi
echo ""

# ==========================================
# 4. Permisos de archivos + storage link
# ==========================================
echo -e "${YELLOW}[4/8] Ajustando permisos y storage...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan storage:link 2>/dev/null || true
docker exec ${PROJECT_NAME}_php chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
docker exec ${PROJECT_NAME}_php chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec ${PROJECT_NAME}_php chmod 666 /var/www/html/.env 2>/dev/null || true
echo -e "${GREEN}OK Permisos y storage ajustados${NC}"
echo ""

# ==========================================
# 5. Publicar assets (Livewire, etc.)
# ==========================================
echo -e "${YELLOW}[5/8] Publicando assets (Livewire)...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan vendor:publish --force --tag=livewire:assets 2>/dev/null || true
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan livewire:publish --assets 2>/dev/null || true
echo -e "${GREEN}OK Assets publicados${NC}"
echo ""

# ==========================================
# 6. Setup: migraciones, seeders, permisos
# ==========================================
echo -e "${YELLOW}[6/8] Setup: migraciones, seeders, permisos, webhooks...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan app:setup 2>&1
echo -e "${GREEN}OK Setup completado${NC}"
echo ""

# ==========================================
# 7. Reiniciar servicios
# ==========================================
echo -e "${YELLOW}[7/8] Reiniciando servicios...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan queue:restart 2>/dev/null || true

cd "$PROJECT_DIR"
docker restart ${PROJECT_NAME}_php ${PROJECT_NAME}_nginx ${PROJECT_NAME}_queue ${PROJECT_NAME}_reverb ${PROJECT_NAME}_evolution_listener 2>&1
sleep 3
echo -e "${GREEN}OK Servicios reiniciados${NC}"
echo ""

# ==========================================
# 8. Verificar servicios
# ==========================================
echo -e "${YELLOW}[8/8] Verificando servicios...${NC}"

for SVC in php nginx queue reverb evolution_listener redis mysql; do
    if docker ps --format '{{.Names}}' | grep -q "${PROJECT_NAME}_${SVC}"; then
        echo -e "${GREEN}  OK ${PROJECT_NAME}_${SVC} corriendo${NC}"
    else
        echo -e "${RED}  FALTA ${PROJECT_NAME}_${SVC} no esta corriendo${NC}"
    fi
done
echo ""

# ==========================================
# Resumen
# ==========================================
echo -e "${GREEN}=========================================="
echo "  Deploy completado exitosamente"
echo "==========================================${NC}"
echo ""
echo -e "${BLUE}Fecha: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo -e "${BLUE}Rama: $BRANCH${NC}"
echo -e "${BLUE}Commit: $LAST_COMMIT${NC}"
echo ""
