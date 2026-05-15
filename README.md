# Igualdad - Sistema de Gestión de Registros Retributivos y Planes de Igualdad

## 📋 Descripción General

**Igualdad** es una aplicación web desarrollada en PHP 8+ que facilita la gestión integral de registros salariales, planes de igualdad de género y análisis de brecha salarial para empresas. El sistema soporta múltiples tipos de cuenta (persona cliente, Técnico, persona administradora) y proporciona herramientas para subir, procesar y generar reportes sobre datos retributivos.

### Características Principales

- ✅ **Gestión de Registros Retributivos**: Subida y validación de archivos Excel con datos de salarios
- ✅ **Análisis de Igualdad**: Cálculo automático de brechas salariales por categoría
- ✅ **Generación de Planes**: Creación de documentos Word con planes de igualdad personalizados
- ✅ **Multi-rol**: Soporte para persona cliente, Técnico, persona administradora con permisos diferenciados
- ✅ **Formularios Dinámicos**: Complementos cuantitativos y cualitativos (bajas, formación, excedencias, permisos)
- ✅ **Descarga de Plantillas**: Plantillas Excel predefinidas para registro retributivo
- ✅ **API de Chat**: Integración con Gemini API para asistencia a usuarios

---

## 🚀 Inicio Rápido

### Requisitos Previos

- **PHP**: 8.1 o superior
- **MySQL**: 8.0 o superior
- **Composer**: 2.0 o superior
- **Servidor Web**: Apache con soporte para .htaccess (XAMPP recomendado)

### Instalación

1. **Clonar o descargar el repositorio**:
   ```bash
   git clone <repository-url> Igualdad
   cd Igualdad
   ```

2. **Instalar dependencias con Composer**:
   ```bash
   composer install
   ```

3. **Configurar variables de entorno**:
   ```bash
   cp .env.example .env
   ```
   Editar `.env` con tus valores reales:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=igualdadconsulting
   DB_USER=root
   DB_PASS=

   GEMINI_API_KEY=tu_clave_gemini_aqui

   APP_BASE_URL=http://localhost/Igualdad
   APP_BASE_PATH=/Igualdad
   APP_ENV=development
   ```

4. **Crear base de datos**:
   ```bash
   mysql -u root -p < base_datos/BD_igualdad.sql
   mysql -u root -p igualdadconsulting < base_datos/Insrt_medidas.sql
   ```

5. **Permisos de directorios** (solo en Linux/Mac):
   ```bash
   chmod 755 uploads/
   chmod 644 .env
   ```

6. **Acceder a la aplicación**:
   - URL: `http://localhost/Igualdad`
   - Credenciales: Según datos insertados en BD (ver `BD_igualdad.sql`)

---

## 📁 Estructura del Proyecto

```
Igualdad/
├── base_datos/              # Scripts SQL
│   ├── BD_igualdad.sql      # Esquema inicial
│   └── Insrt_medidas.sql    # Datos de inicialización
├── config/                  # Configuración
│   ├── config.php           # Cargas .env y define constantes DB/API
│   └── mail.php             # Configuración SMTP (si aplica)
├── controller/              # Controladores (lógica de negocio)
│   ├── admin_controller.php
│   ├── cliente_controller.php
│   ├── empresa_controller.php
│   ├── tecnico_controller.php
│   └── ...
├── html/                    # Vistas/Templates HTML
│   ├── index_cliente.php    # Dashboard cliente
│   ├── index_empresa.php    # Dashboard empresa
│   ├── tecnico.html.php     # Dashboard técnico
│   ├── admin.html.php       # Dashboard administrador
│   ├── complemento_formularios.php  # Formularios dinámicos
│   └── ...
├── model/                   # Modelos/Entidades
│   ├── admin.php
│   ├── empresa.php
│   ├── tecnico.php
│   └── ...
├── php/                     # Scripts PHP utilitarios
│   ├── auth.php             # Autenticación y verificación de sesión
│   ├── login.php            # Formulario/lógica de login
│   ├── logout.php           # Cierre de sesión
│   ├── procesar_registro_retributivo.php  # Procesamiento de uploads
│   ├── generar_cuadro_porcentajes.php     # Cálculo de brechas
│   ├── generar_word_desdeexcel.php        # Generación de documentos Word
│   ├── download_archivo_subido.php  # Descarga de archivos del usuario
│   ├── download_archivo.php         # Descarga de plantillas
│   ├── api_chat.php         # Endpoint de chat con Gemini
│   ├── fragments/           # Fragmentos PHP reutilizables
│   │   └── sidebar.php      # Menú lateral dinámico unificado
│   └── ...
├── css/                     # Estilos CSS
│   ├── global.css           # Estilos globales (colores, fuentes, layout)
│   ├── admin.css
│   ├── cliente.css
│   ├── tecnico.css
│   ├── chatbot.css          # Estilos del widget de chat
│   └── ...
├── assets/                  # Recursos estáticos (JS, imágenes)
│   └── js/
│       └── chatbot.js       # Lógica del widget de chat
├── uploads/                 # Directorio para archivos subidos (NO en git)
│   ├── empresa_13/          # Subdirectorio por empresa
│   └── .gitkeep
├── PlantillaRegistroRetributivo/  # Plantillas Excel
│   └── Herramienta_Registro_Retributivo.xlsx
├── vendor/                  # Dependencias de Composer (NO en git)
│   ├── autoload.php
│   ├── phpoffice/
│   ├── phpmailer/
│   └── ...
├── composer.json            # Configuración de dependencias
├── composer.lock            # Lock file (debe estar en git)
├── .env.example             # Template de configuración (.env no en git)
├── .gitignore               # Archivos excluidos del repositorio
└── README.md                # Este archivo
```

---

## 👥 Roles de Usuario

### 1. **PERSONA CLIENTE**
- Sube registros retributivos (archivos Excel)
- Completa complementos (bajas, formación, excedencias, permisos)
- Descarga plantillas y documentos generados
- Accede a su área privada (perfil, reuniones)
- Interactúa con chatbot para soporte

### 2. **TÉCNICO**
- Gestiona múltiples empresas asignadas
- Revisa y valida registros subidos
- Genera planes de igualdad
- Accede a contratos y mantenimientos
- Contacto directo con empresas

### 3. **PERSONA ADMINISTRADORA**
- Gestión completa de usuarios (crear, editar, eliminar)
- Supervisión de todas las empresas
- Control de acceso a datos
- Auditoría y mantenimiento del sistema
- Gestión de roles y permisos

---

## 🔄 Flujo Principal de Registro Retributivo

```
Paso 1: Subida de Registro
  ↓ (Cliente sube archivo Excel)
  
Paso 2: Análisis de Igualdad (DESBLOQUEADO)
  ↓ (Complementos: bajas, formación, excedencias, permisos, cuestionarios)
  
Paso 3: Generación de Plan
  ↓ (Descarga Word con análisis y recomendaciones)
```

### Tipos de Registro

- **REGISTRO_RETRIBUTIVO**: Archivo Excel con datos de empleados (genera automáticamente cuadro de porcentajes y Word)
- **REGISTRO_MALFORMATEADO**: Archivo propio del usuario (sin procesamiento automático)

## 🗄️ Base de Datos

### Tablas Principales

| Tabla | Descripción |
|-------|-------------|
| `usuario` | Personas usuarias del sistema (persona cliente, técnico, persona administradora) |
| `usuario_empresa` | Relación usuario-empresa |
| `empresa` | Datos de empresas |
| `archivos` | Registros de archivos subidos |
| `cliente_medida` | Medidas completadas por cliente |
| `datos_empleados` | Datos de empleados del registro |
| `contrato_empresa` | Contratos activos |

Consulta [BD_igualdad.sql](base_datos/BD_igualdad.sql) para estructura completa.

---

## 📦 Dependencias Principales (Composer)

```json
{
  "require": {
    "phpoffice/phpspreadsheet": "^1.x",
    "phpmailer/phpmailer": "^6.x",
    "maennchen/zipstream-php": "^2.x",
    "psr/simple-cache": "^1.x"
  }
}
```

### Librerías Usadas

- **PhpSpreadsheet**: Lectura/escritura de archivos Excel
- **PHPMailer**: Envío de correos
- **ZipStream**: Generación de archivos ZIP
- **Composer Autoloader**: Carga automática de clases PSR-4

---

## 🔐 Seguridad

### Prácticas Implementadas

✅ **Prepared Statements**: Todas las consultas SQL usan parámetros vinculados (`bind_param`)  
✅ **Session-Based Auth**: Autenticación mediante sesiones PHP seguras  
✅ **CSRF Protection**: Tokens CSRF en formularios (si aplica)  
✅ **Input Validation**: Validación y sanitización de entrada  
✅ **Environment Variables**: Credenciales en `.env` (no en código)  
✅ **File Upload Validation**: Verificación de MIME types y extensiones

### Credenciales Seguras

- Nunca commitear `.env` con valores reales (usar `.env.example`)
- Usar credenciales diferentes para dev/staging/producción
- Rotación regular de GEMINI_API_KEY

---

## 🧪 Testing

Actualmente no hay tests automatizados configurados. Para agregar PHPUnit:

```bash
composer require --dev phpunit/phpunit
./vendor/bin/phpunit
```

Se recomienda crear tests para:
- Autenticación (login/logout)
- Validación de subida de archivos
- Cálculo de brechas salariales
- Generación de documentos

---

## 🐛 Troubleshooting

### Error: "Sesión inválida"
- Verificar que `config.php` está cargando `.env` correctamente
- Verificar que `auth.php` está verificando `$_SESSION['user']['id_usuario']`

### Error: "No se puede crear carpeta uploads"
- Verificar permisos: `chmod 755 uploads/`
- Asegurar que el usuario del servidor web tiene acceso de escritura

### Error: "Base de datos no encontrada"
- Ejecutar scripts SQL: `mysql -u root -p igualdadconsulting < base_datos/BD_igualdad.sql`
- Verificar variables en `.env`

---

## 📝 Notas de Desarrollo

### Última Actualización
- **Fecha**: 2026-05-11
- **Cambios**: 
  - Refactorizado config.php para cargar .env
  - Eliminado vendor/ del repositorio (usar `composer install`)
  - Limpieza de datos reales de uploads/
  - Extracción de sidebar a fragmentos reutilizables

### Próximas Mejoras

- [ ] Agregar PHPUnit tests
- [ ] Implementar API REST para mobile app
- [ ] Caché de resultados de cálculos
- [ ] Exportación a PDF alternativa
- [ ] Dashboard mejorado con gráficos interactivos

---

## 📄 Licencia

[Especificar licencia si aplica]

---

## ✉️ Contacto & Soporte

Para preguntas, reportar bugs o sugerir mejoras, contactar al equipo de desarrollo.

**Evaluadores**: Para instalar y ejecutar el proyecto, seguir sección "Inicio Rápido".
