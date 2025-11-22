#!/bin/bash

# Script de inicialización para desarrollo
echo "🚀 Iniciando microservicio PHP Auth Booking..."

# Crear la red externa si no existe
docker network create microservices-network 2>/dev/null || echo "Red microservices-network ya existe"

# Construir y levantar los contenedores
echo "📦 Construyendo contenedores..."
docker-compose up --build -d

# Esperar a que la base de datos esté lista
echo "⏳ Esperando a que PostgreSQL esté listo..."
sleep 10

# Ejecutar migraciones
echo "🔄 Ejecutando migraciones..."
docker-compose exec php-auth-booking php artisan migrate --force

# Generar clave de aplicación si no existe
echo "🔑 Verificando clave de aplicación..."
docker-compose exec php-auth-booking php artisan key:generate --force

# Limpiar caché
echo "🧹 Limpiando caché..."
docker-compose exec php-auth-booking php artisan config:clear
docker-compose exec php-auth-booking php artisan cache:clear
docker-compose exec php-auth-booking php artisan route:clear

echo "✅ Microservicio iniciado correctamente!"
echo "🌐 Aplicación disponible en: http://localhost:8080"
echo "🗄️  Adminer (BD) disponible en: http://localhost:8081"
echo ""
echo "Para ver los logs: docker-compose logs -f"
echo "Para detener: docker-compose down"