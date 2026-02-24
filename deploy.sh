#!/bin/bash

PROJECT_NAME="crm"

# ============================================
# Script de Deploy Automático para Laravel
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
echo "  🚀 Deploy Laravel: $PROJECT_NAME"
echo "==========================================${NC}"
echo ""

cd "$SRC_DIR"

# ==========================================
# 1. Git Pull
# ==========================================
echo -e "${YELLOW}[1/9] ⬇️  Descargando cambios desde Git...${NC}"
git stash --quiet 2>/dev/null || true
BRANCH=$(git rev-parse --abbrev-ref HEAD)

if git pull origin "$BRANCH" 2>&1; then
    echo -e "${GREEN}✓ Cambios descargados desde rama '$BRANCH'${NC}"
else
    echo -e "${RED}✗ Error al descargar cambios${NC}"
    exit 1
fi

LAST_COMMIT=$(git log -1 --pretty=format:'%h - %s (%ar) por %an')
echo -e "${BLUE}    📝 Último commit: $LAST_COMMIT${NC}"
echo ""

# ==========================================
# 2. Composer Install
# ==========================================
echo -e "${YELLOW}[2/9] 📦 Composer install...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5
echo -e "${GREEN}✓ Dependencias actualizadas${NC}"
echo ""

# ==========================================
# 3. Migraciones
# ==========================================
echo -e "${YELLOW}[3/9] 🗄️  Migraciones...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan migrate --force 2>&1
echo -e "${GREEN}✓ Migraciones ejecutadas${NC}"
echo ""

# ==========================================
# 4. Sincronizar permisos y módulos
# ==========================================
echo -e "${YELLOW}[4/9] 🔐 Sincronizando módulos y permisos...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan permissions:sync 2>&1
echo -e "${GREEN}✓ Permisos sincronizados${NC}"
echo ""

# ==========================================
# 5. Compilar assets + instalar dependencias Node.js
# ==========================================
echo -e "${YELLOW}[5/9] 📦 Compilando assets...${NC}"
if [ -f "$SRC_DIR/package.json" ]; then
    docker exec -w /var/www/html ${PROJECT_NAME}_php npm install 2>&1 | tail -3
    docker exec -w /var/www/html ${PROJECT_NAME}_php npm run build 2>&1 | tail -5
    echo -e "${GREEN}✓ Assets compilados${NC}"
    
    # Verificar que socket.io-client está disponible (necesario para evolution-listener)
    if docker exec -w /var/www/html ${PROJECT_NAME}_php node -e "require('socket.io-client'); console.log('OK')" 2>/dev/null | grep -q "OK"; then
        echo -e "${GREEN}✓ socket.io-client disponible para evolution-listener${NC}"
    else
        echo -e "${YELLOW}⚠️  socket.io-client no disponible, reinstalando...${NC}"
        docker exec -w /var/www/html ${PROJECT_NAME}_php npm install socket.io-client 2>&1 | tail -3
    fi
else
    echo -e "${BLUE}⏭️  Sin package.json, saltando${NC}"
fi
echo ""

# ==========================================
# 6. Permisos de archivos + storage link
# ==========================================
echo -e "${YELLOW}[6/9] 🔐 Ajustando permisos y storage...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan storage:link 2>/dev/null || true
docker exec ${PROJECT_NAME}_php chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
docker exec ${PROJECT_NAME}_php chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
docker exec ${PROJECT_NAME}_php chmod 666 /var/www/html/.env 2>/dev/null || true
echo -e "${GREEN}✓ Permisos y storage ajustados${NC}"
echo ""

# ==========================================
# 7. Publicar assets (Livewire, etc.)
# ==========================================
echo -e "${YELLOW}[7/9] 📦 Publicando assets (Livewire, etc.)...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan vendor:publish --force --tag=livewire:assets 2>/dev/null || true
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan livewire:publish --assets 2>/dev/null || true
echo -e "${GREEN}✓ Assets publicados${NC}"
echo ""

# ==========================================
# 8. Limpiar y recachear
# ==========================================
echo -e "${YELLOW}[8/9] ⚡ Limpiando y recacheando...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan config:cache
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan route:cache
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan view:cache
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan event:cache 2>/dev/null || true
echo -e "${GREEN}✓ Cache reconstruida${NC}"
echo ""

# ==========================================
# 9. Reiniciar servicios + queue workers
# ==========================================
echo -e "${YELLOW}[9/9] 🔄 Reiniciando servicios...${NC}"
# Reiniciar queue workers para que tomen el código nuevo
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan queue:restart 2>/dev/null || true

cd "$PROJECT_DIR"

# Reiniciar todos los servicios (incluyendo reverb y evolution-listener)
docker compose restart php nginx 2>&1
# Reiniciar servicios de WebSocket (si existen)
docker compose restart reverb evolution-listener 2>/dev/null || true
sleep 3
echo -e "${GREEN}✓ Servicios reiniciados${NC}"
echo ""

# ==========================================
# Verificar servicios de WebSocket
# ==========================================
echo -e "${YELLOW}[Extra] � Verificando servicios de WebSocket...${NC}"

# Verificar que Reverb está corriendo
if docker ps --format '{{.Names}}' | grep -q "${PROJECT_NAME}_reverb"; then
    echo -e "${GREEN}  ✓ Reverb (WebSocket server) corriendo${NC}"
else
    echo -e "${YELLOW}  ⚠️  Reverb no está corriendo. Ejecutar: docker compose up -d reverb${NC}"
fi

# Verificar que Evolution Listener está corriendo
if docker ps --format '{{.Names}}' | grep -q "${PROJECT_NAME}_evolution_listener"; then
    echo -e "${GREEN}  ✓ Evolution Listener corriendo${NC}"
else
    echo -e "${YELLOW}  ⚠️  Evolution Listener no está corriendo. Ejecutar: docker compose up -d evolution-listener${NC}"
fi

# Verificar Node.js disponible para el listener
if docker exec ${PROJECT_NAME}_evolution_listener node --version 2>/dev/null; then
    echo -e "${GREEN}  ✓ Node.js disponible en evolution-listener${NC}"
elif docker exec ${PROJECT_NAME}_php node --version 2>/dev/null; then
    echo -e "${GREEN}  ✓ Node.js disponible en php${NC}"
else
    echo -e "${RED}  ✗ Node.js NO disponible. evolution:listen no funcionará.${NC}"
    echo -e "${RED}    Agregar Node.js al Dockerfile del contenedor PHP.${NC}"
fi
echo ""

# ==========================================
# Configurar webhooks de Evolution API
# ==========================================
echo -e "${YELLOW}[Extra] 🔗 Configurando webhooks de Evolution API...${NC}"
docker exec -w /var/www/html ${PROJECT_NAME}_php php artisan channels:setup-webhooks --full 2>&1
echo -e "${GREEN}✓ Webhooks configurados${NC}"
echo ""

# ==========================================
# Resumen
# ==========================================
echo -e "${GREEN}=========================================="
echo "  ✅ Deploy completado exitosamente"
echo "==========================================${NC}"
echo ""
echo -e "${BLUE}📅 Fecha: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo -e "${BLUE}🔀 Rama: $BRANCH${NC}"
echo -e "${BLUE}📝 Commit: $LAST_COMMIT${NC}"
echo ""
