# Product Overview

ASESCO BPO es un sistema de gestión de procesos de negocio (BPO) para "Asesorías Especializadas y Cobranzas".

## Propósito
Aplicación web interna para gestionar cobranzas y servicios de asesoría a clientes, con integración de WhatsApp para comunicación.

## Módulos Implementados

### Autenticación
- Login con credenciales (email/password)
- Logout con confirmación SweetAlert2
- Redirección automática según estado de sesión
- Credenciales admin: `admin@asesco.com` / `password`
- Botón de login deshabilitado durante proceso de autenticación

### Dashboard
- Vista general de operaciones
- Acceso controlado por permiso `dashboard.ver`
- Estadísticas y métricas (pendiente)
- Feed de actividad reciente (pendiente)

### Usuarios (Configuración)
- CRUD completo de usuarios
- Tabla dinámica con paginación y búsqueda
- Ordenamiento por columnas (nombre, email, fecha)
- Asignación de múltiples roles a usuarios
- Foto de perfil con avatar por defecto (UI Avatars API)
- Notificaciones toast con SweetAlert2
- Permisos: `usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.eliminar`

### Roles y Permisos
- CRUD de roles con colores personalizados
- Permisos organizados por módulo y acción
- Acciones estándar: ver, crear, editar, eliminar
- Módulos dinámicos desde base de datos
- Rol admin tiene acceso total (no se puede eliminar)
- Permisos: `roles.ver`, `roles.crear`, `roles.editar`, `roles.eliminar`
- Sincronización via `php artisan permissions:sync`

### Canales WhatsApp (Evolution API)
- Integración con Evolution API v2
- Crear/editar/eliminar canales (instancias)
- Conexión vía código QR mostrado en modal
- Estados: conectado, desconectado, conectando, escanear QR
- Sincronización automática desde Evolution API al cargar página
- Botón reiniciar instancia
- Botón actualizar estado
- Asignación de usuarios a canales
- Permisos: `canales.ver`, `canales.crear`, `canales.editar`, `canales.eliminar`

### Chat WhatsApp
- Interfaz de mensajería integrada con Evolution API
- Lista de conversaciones con búsqueda y filtros
- Panel de mensajes con envío en tiempo real
- Panel de información de contacto editable
- Sistema de etiquetas para organizar contactos
- Notificaciones de mensajes nuevos (badge en sidebar y header)
- Acciones rápidas: marcar como pagado, crear seguimiento
- Permisos especiales: `chats.ver`, `chats.enviar`, `chats.etiquetas`

### Sistema de Permisos
- Middleware `CheckPermission` para proteger rutas
- Verificación de permisos en componentes Livewire
- Sidebar dinámico que oculta menús sin acceso
- Botones de acción condicionales según permisos
- Usuarios con rol `admin` tienen acceso total
- Error 403 para accesos no autorizados
- Comando `php artisan permissions:sync` para sincronizar módulos

## Módulos Planificados
- Clientes: Gestión de cartera de clientes
- Cobranzas: Seguimiento de cobros y pagos
- Reportes: Informes y estadísticas

## Usuarios Objetivo
Personal interno y administradores que gestionan operaciones BPO.

## Idioma
La interfaz está completamente en español. Mensajes de error, etiquetas, validaciones y textos deben estar en español.

## Configuración Regional
- Zona horaria: America/Bogota
- Locale: es (español)
- Faker locale: es_ES
