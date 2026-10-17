# El certificado electrónico para Verifactu

Huella envía tus facturas a la AEAT identificándose con **tu** certificado electrónico, como
harías tú en la Sede. Sin él, Huella registra y encadena tus facturas, pero no puede enviarlas: ni
a la AEAT de pruebas ni a producción. En cuanto lo subas, se envía todo lo que estaba en cola.

## Qué certificado

| Si eres | Necesitas |
|---|---|
| **Sociedad** (SL, SA…) | Certificado de **representante de persona jurídica** (lleva el NIF de la empresa) |
| **Autónomo** | Certificado de **persona física** (lleva tu NIF) |

Vale el de la FNMT o el de otro prestador cualificado español. El NIF del certificado tiene que ser
el de la empresa que emite las facturas.

## Exportarlo como archivo .p12 / .pfx

Si lo tienes instalado en el navegador:

- **Chrome / Edge (Windows):** Configuración → Privacidad y seguridad → Seguridad → Gestionar
  certificados → Personal → elige el certificado → **Exportar** → "Sí, exportar la clave privada"
  → formato PFX → pon una **contraseña**.
- **Firefox:** Ajustes → Privacidad & Seguridad → Ver certificados → Sus certificados → elige el
  certificado → **Hacer copia** → pon una contraseña.
- **macOS (Llavero):** Acceso a Llaveros → Mis certificados → clic derecho → **Exportar** → formato
  .p12 → pon una contraseña.

## Subirlo a Huella

Panel de Huella → **Emisores y certificados** → elige el archivo, escribe la contraseña → **Subir**.

- Huella comprueba que no ha caducado (y que le quedan al menos 7 días) y que su NIF es el de tu
  empresa.
- Se guarda **cifrado**: solo el servicio que envía a la AEAT puede abrirlo, en el momento de enviar.
  **La contraseña no se guarda.**
- Te avisamos por correo 30, 7 y 1 día antes de que caduque. Al renovarlo, sube el nuevo: el antiguo
  se borra.

## Si la AEAT lo rechaza

Huella pausa el envío y te avisa (correo, panel y aviso en WordPress). Tus facturas esperan en cola,
no se pierde ninguna. Sube un certificado válido y se envían solas.
