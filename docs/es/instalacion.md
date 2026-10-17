# Instalar Huella en tu tienda WooCommerce

Huella registra en la AEAT (Verifactu) cada factura que crea **PDF Invoices & Packing Slips for
WooCommerce**, y pone en la factura el código QR obligatorio. Tú sigues facturando como siempre.

## Antes de empezar

- WordPress 6.4 o superior, WooCommerce 8.9 o superior, PHP 7.4 o superior.
- El plugin **PDF Invoices & Packing Slips for WooCommerce** (WP Overnight), con la factura
  activada (WooCommerce → PDF Invoices → Documentos → Factura → Activar).
- Una cuenta en Huella (gratis en pruebas) y, para producción, el certificado electrónico de tu
  empresa ([guía del certificado](certificado.md)).

## 1. Instala el plugin

1. Descarga `huella-verifactu.zip` desde tu panel de Huella (Primeros pasos, paso 4).
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin**, elige el ZIP y **Activar**.

## 2. Conéctalo con tu cuenta

1. En tu panel de Huella, crea una **clave de pruebas** (`hk_test_…`). Cópiala: solo se muestra una vez.
2. En WordPress: **WooCommerce → Ajustes → Verifactu**, pega la clave y guarda.
   Recomendado: en lugar de pegarla, añádela a `wp-config.php`:
   ```php
   define( 'HUELLA_API_KEY', 'hk_test_…' );
   ```
3. Al guardar, la página muestra la conexión: tu cuenta, la insignia amarilla **PRUEBAS** y tus empresas.
4. Elige la empresa que emite las facturas de esta tienda.

## 3. El NIF del cliente

Por defecto Huella añade un campo opcional **NIF/CIF** en el checkout (clásico y por bloques). Si el
cliente lo rellena con un NIF válido, la factura es **completa (F1)**; si no, **simplificada (F2)**,
hasta 400 € (por encima, pide el NIF). Si tu tienda ya guarda el NIF en otro campo, elige "leer
de un campo existente" e indica su nombre.

## 4. Comprueba que funciona

Haz un pedido de prueba y crea su factura. En el pedido verás el estado **Verifactu**: *Pendiente*
→ *Enviado* → *Aceptado* (en pruebas, la AEAT responde en segundos o minutos). El PDF de la factura
lleva el **QR tributario** y la leyenda **VERI\*FACTU**.

Si algo falla, el pedido lo dice (y hay un botón **Reintentar envío**). Ninguna factura se pierde:
Huella reintenta sola durante 48 horas.

## 5. Pasar a producción

Cuando quieras que las facturas tengan validez fiscal:

1. Sube el certificado de tu empresa en el panel (Emisores y certificados).
2. Elige un plan (Plan).
3. Crea una **clave de producción** (`hk_live_…`) y cámbiala en WooCommerce → Ajustes → Verifactu.
   La insignia pasa a verde **PRODUCCIÓN**.

Las facturas de pruebas no cuentan para la AEAT; desde ese momento, cada factura nueva se registra
de verdad. No hace falta tocar nada más.

## Reembolsos

Un reembolso de un pedido ya registrado necesita una **factura rectificativa** (R1 o R5). Huella te
avisa en el pedido y en el escritorio; por ahora la emites con tu programa de facturación o con PDF
Invoices Professional. **No anules la factura original**: sigue siendo válida.
