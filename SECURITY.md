# Política de seguridad

## Reporte de vulnerabilidades

Ante una vulnerabilidad de seguridad, **no debe abrirse un issue público**. El reporte se
realiza de forma privada a **bsierra@intellihelp.tech**.

Se solicita incluir, en la medida de lo posible: descripción, pasos de reproducción, impacto
y una posible mitigación. El reporte recibirá respuesta a la brevedad.

## Manejo de secretos y datos

- Las credenciales reales (base de datos, `client_secret` de Google) residen en `config.php`
  o en variables de entorno; **no se versionan** y, en producción, `config.php` se ubica
  **fuera del web root**.
- El repositorio no contiene datos reales de comprobantes (imágenes, exportaciones, planillas).
- El acceso se restringe al dominio de la organización mediante Google Workspace (`hd`).
- Los comprobantes se persisten como borrador y requieren **revisión humana** antes de su
  aprobación.
