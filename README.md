# RedSys PHP Test App

Este proyecto contiene ejemplos oficiales de integración con la pasarela de pagos **RedSys**, adaptados para ser ejecutados en un servidor PHP moderno (en este caso **Render.com**) y facilitar las pruebas desde entornos externos como **Salesforce**.

---

## 📂 Contenido

- `ejemploGeneraPet.php` → Genera los parámetros de una petición (`Ds_MerchantParameters`, `Ds_Signature`, etc.).
- `ejemploRecepcionaPet.php` → Ejemplo de cómo recibir y validar una notificación (callback) desde RedSys.
- `signature.php` → Lógica de firma HMAC SHA-256/512 (según versión).
- `utils.php` → Funciones auxiliares de codificación Base64, URL-safe, etc.
- `index.php` → Punto de entrada del proyecto (redirige a `ejemploGeneraPet.php`).
- `composer.json` → Archivo mínimo para que Render detecte la app PHP.

---

## 🚀 Despliegue en Render.com

1. Haz fork o clona este repositorio en tu cuenta de **GitHub**.
2. Entra a [https://dashboard.render.com/](https://dashboard.render.com/).
3. Crea un nuevo servicio web (**New → Web Service**).
4. Conecta tu repo de GitHub y configura:
   - **Environment**: PHP
   - **Build Command**:
     ```bash
     composer install
     ```
   - **Start Command**:
     ```bash
     php -S 0.0.0.0:10000 -t .
     ```
5. Selecciona el plan **Free**.
6. Render desplegará automáticamente la aplicación y te dará una URL pública, por ejemplo:


---

## 🧪 Pruebas

- Accede a la URL pública y serás redirigido a `ejemploGeneraPet.php`.
- Genera un `Ds_MerchantParameters` y una firma (`Ds_Signature`).
- Copia los valores generados para compararlos con los de tu integración en Salesforce.
- Usa `ejemploRecepcionaPet.php` como endpoint de notificación para validar la verificación de firmas en callbacks de RedSys.

---

## ⚙️ Requisitos

- PHP 7.4+ (Render lo gestiona automáticamente).
- Extensiones estándar de PHP (no se requieren librerías externas).
- Cuenta en RedSys con credenciales de prueba (FUC, terminal, clave secreta).

---

## 📌 Notas

- Render.com permite un plan gratuito con limitaciones (ejecución limitada mensual y "cold starts").
- Para pruebas simples de integración es suficiente.
- Este proyecto está diseñado como **entorno de validación**: no lo uses en producción sin reforzar seguridad y gestión de claves.

---

## 👨‍💻 Autor

Proyecto adaptado por **Javier Tibamoza** para validar la integración RedSys ↔ Salesforce usando ejemplos en PHP desplegados en Render.
