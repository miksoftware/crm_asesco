# Product Overview

ASESCO BPO es un sistema de gestión de procesos de negocio (BPO) para "Asesorías Especializadas y Cobranzas".

## Propósito
Aplicación web interna para gestionar cobranzas y servicios de asesoría a clientes, con integración de WhatsApp para comunicación.

## Módulos Implementados

### Autenticación
- Login con credenciales (email/password)
- Logout con confirmación
- Redirección automática según estado de sesión
- Credenciales admin: `admin@asesco.com` / `password`

### Dashboard
- Vista general de operaciones
- Estadísticas y métricas (pendiente)
- Feed de actividad reciente (pendiente)

### Usuarios (Configuración)
- CRUD completo de usuarios
- Tabla dinámica con paginación y búsqueda
- Asignación de roles a usuarios
- Notificaciones toast con SweetAlert2

### Roles y Permisos
- CRUD de roles con colores personalizados
- Permisos organizados por módulo y acción
- Acciones: ver, crear, editar, eliminar
- Módulos: Dashboard, Canales, Usuarios, Roles, Clientes, Cobranzas, Reportes

### Canales WhatsApp (Evolution API)
- Integración con Evolution API v2
- Crear/editar/eliminar canales
- Conexión vía código QR (mostrado en modal)
- Estados: conectado, desconectado, conectando, escanear QR
- Asignación de usuarios a canales
- Sincronización automática de estado

## Módulos Planificados
- Clientes: Gestión de cartera de clientes
- Cobranzas: Seguimiento de cobros y pagos
- Reportes: Informes y estadísticas
- Chat WhatsApp: Interfaz de mensajería integrada

## Usuarios Objetivo
Personal interno y administradores que gestionan operaciones BPO.

## Idioma
La interfaz está en español. Mensajes de error, etiquetas y textos deben estar en español.

## Configuración Regional
- Zona horaria: America/Bogota
- Locale: es (español)
