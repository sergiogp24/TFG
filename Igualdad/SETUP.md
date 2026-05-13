# 🚀 Guía de Configuración del Proyecto

## Prerequisitos

- PHP 8.0+
- MySQL 5.7+
- Composer
- XAMPP (o servidor Apache con PHP)

---

## 1️⃣ Instalación Inicial

### Paso 1: Clonar repositorio
```bash
git clone <repo-url> Igualdad
cd Igualdad
```

### Paso 2: Instalar dependencias PHP
```bash
composer install
```

### Paso 3: Crear archivo .env
```bash
cp .env.example .env
```

### Paso 4: Configurar .env
Edita el archivo `.env` con tus valores locales:

```env
# Base de datos local
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=tu_contraseña_mysql
DB_NAME=igualdadconsulting

# Mail (para desarrollo, puedes usar Mailtrap)
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=tu_usuario_mailtrap
MAIL_PASSWORD=tu_contraseña_mailtrap

# Gemini API (opcional para chatbot)
GEMINI_API_KEY=tu_gemini_api_key
```

### Paso 5: Crear base de datos
```bash
mysql -u root -p < base_datos/BD_igualdad.sql
```

### Paso 6: Configurar servidor web
En XAMPP, asegúrate que:
- La carpeta está en `C:\xampp\htdocs\Igualdad`
- Accedes a `http://localhost/Igualdad`

---

## 2️⃣ Variables de Entorno (.env)

### Desarrollo (Development)
```env
APP_ENV=development
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
MAIL_HOST=smtp.mailtrap.io
GEMINI_API_KEY=
```

### Producción (Production)
```env
APP_ENV=production
DB_HOST=db.ejemplo.com
DB_USER=usuario_prod
DB_PASS=contraseña_segura_prod
MAIL_HOST=smtp.ionos.es
MAIL_USERNAME=email@tuempresa.com
MAIL_PASSWORD=contraseña_smtp_real
GEMINI_API_KEY=tu_api_key_real
```

---

## 3️⃣ Configuración de Base de Datos

### Crear BD desde SQL
```bash
# Opción 1: Línea de comando
mysql -u root -p igualdadconsulting < base_datos/BD_igualdad.sql

# Opción 2: phpMyAdmin
# 1. Abre phpMyAdmin (http://localhost/phpmyadmin)
# 2. Crea BD nueva: igualdadconsulting
# 3. Ve a "Importar" y sube BD_igualdad.sql
```

### Crear tabla de auditoría (si aplica)
```bash
mysql -u root -p igualdadconsulting < base_datos/auditoria.sql
```

---

## 4️⃣ Credenciales Iniciales

### Admin
- **Usuario:** admin
- **Contraseña:** (solicitar al administrador)

### Técnico
- **Usuario:** tecnico
- **Contraseña:** (solicitar al administrador)

### Cliente
- **Usuario:** cliente
- **Contraseña:** (solicitar al administrador)

---

## 5️⃣ Configuración de Email

### Opción A: Mailtrap (Recomendado para desarrollo)
1. Crea cuenta en [mailtrap.io](https://mailtrap.io)
2. Copia credenciales SMTP
3. Actualiza `.env`:
   ```env
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=465
   MAIL_USERNAME=tu_username
   MAIL_PASSWORD=tu_password
   ```

### Opción B: IONOS (Producción)
```env
MAIL_HOST=smtp.ionos.es
MAIL_PORT=465
MAIL_SECURE=ssl
MAIL_USERNAME=tu-email@tuempresa.com
MAIL_PASSWORD=tu-contraseña-smtp
MAIL_FROM=noreply@tuempresa.com
```

### Opción C: Gmail (NO recomendado)
Gmail bloquea apps externas por defecto. Si quieres usar Gmail:
1. Habilita "Aplicaciones menos seguras"
2. O usa Google App Passwords (mejor)

---

## 6️⃣ Integración con APIs Externas

### Gemini (Chatbot)
1. Obtén API key en: https://makersuite.google.com/app/apikey
2. Actualiza `.env`:
   ```env
   GEMINI_API_KEY=AIzaSy...
   ```

---

## 7️⃣ Seguridad - NO Subir a GitHub

❌ **NUNCA subas estos archivos:**
- `.env` (contiene credenciales reales)
- `config/mail.php` (si contiene credenciales)

✅ **Usa `.gitignore` (ya configurado):**
```
.env
.env.*
config/mail.php
```

✅ **SÍ sube:**
- `.env.example` (sin valores sensibles)
- `.gitignore`
- Todos los demás archivos del proyecto

---

## 8️⃣ Desarrollo Local Seguro

### Cambiar contraseña SMTP después de clone
```bash
# 1. Edita .env
nano .env

# 2. Actualiza MAIL_PASSWORD con valor temporal/test
MAIL_PASSWORD=test-password-123

# 3. Git sabe ignorar .env (verifica con):
git status  # .env NO debe aparecer

# 4. Commit sin .env
git add .
git commit -m "Nuevo cambio"
```

### Estructura de carpetas segura
```
Igualdad/
├── .env               ← NO SUBIR (credenciales reales)
├── .env.example       ← SÍ SUBIR (template sin valores)
├── .gitignore         ← SÍ SUBIR
├── config/
│   ├── config.php     ← SÍ SUBIR
│   └── mail.php       ← NO SUBIR (si contiene secrets)
├── uploads/           ← NO SUBIR (archivos usuarios)
└── vendor/            ← NO SUBIR (dependencies)
```

---

## 9️⃣ Troubleshooting

### Error: Cannot connect to database
```
Solución:
1. Verifica que MySQL está corriendo
2. Verifica credenciales en .env
3. Verifica que la BD existe:
   mysql -u root -p -e "SHOW DATABASES;"
```

### Error: Undefined variable APP_ENV
```
Solución:
1. Copia .env.example a .env
2. Verifica que .env existe en raíz del proyecto
```

### Error: SMTP authentication failed
```
Solución:
1. Verifica credenciales SMTP en .env
2. Comprueba que host:puerto es correcto
3. Prueba con Mailtrap primero
```

### Error: Cannot redeclare function
```
Solución:
1. Limpia cache PHP
2. Reinicia servidor web
3. Ejecuta: composer dump-autoload
```

---

## 🔟 Comandos Útiles

```bash
# Ver status de git
git status

# Ver qué archivos se ignorarán
git check-ignore -v *

# Reinstalar dependencias
composer install

# Actualizar dependencias
composer update

# Limpiar autoload
composer dump-autoload -o

# Ver version de PHP
php -v

# Ejecutar tests (si existen)
php vendor/bin/phpunit
```

---

## Contacto y Soporte

Para preguntas sobre configuración:
- 📧 Email: support@example.com
- 📞 Teléfono: +34 XXX XXX XXX
- 📚 Documentación: /docs

