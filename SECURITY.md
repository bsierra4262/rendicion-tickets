# Política de seguridad

## Reportar una vulnerabilidad

Si encontrás una vulnerabilidad de seguridad, **no abras un issue público**.
Reportala de forma privada a: **bsierra@intellihelp.tech**.

Incluí, si podés: descripción, pasos para reproducir, impacto y una posible mitigación.
Vas a recibir una respuesta a la brevedad.

## Manejo de secretos y datos

- Las credenciales reales (base de datos, `client_secret` de Google) viven en `config.php`,
  que **no se versiona** (`.gitignore`) y en producción se despliega **fuera del web root**.
- El repositorio nunca debe contener datos reales de comprobantes (fotos, exportaciones, planillas).
- El acceso está restringido al dominio de la organización mediante Google Workspace (`hd`).
- Los comprobantes se guardan como borrador y requieren **revisión humana** antes de aprobarse.
