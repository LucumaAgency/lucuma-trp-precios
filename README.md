# Lucuma TRP Precios

Plugin WordPress: precios por idioma de TranslatePress. Cada plan guarda un precio por moneda y la web muestra el que corresponde al idioma de la URL (no a la IP del visitante, para no romper SEO ni caché).

| Idioma TranslatePress | Moneda | Ejemplo |
|---|---|---|
| `es_PE` (por defecto, `/`) | PEN | `S/ 2.000` |
| `en_US` (`/en/`) | USD | `$1,200` |

## Instalación

Deployer for Git (ya activo en lucumaagency.com) → Install plugin → `https://github.com/LucumaAgency/lucuma-trp-precios` (rama `main`). El archivo del plugin está en la raíz del repo.

## Cargar precios

- **Panel:** Ajustes → Precios por idioma. Una fila por plan: clave, nombre interno, monto PEN, monto USD y sufijo opcional por moneda (`/mes`, `/mo`). Vaciar la clave borra el plan.
- **API (administrador, application password):**
  - `GET /wp-json/lucuma-trp-precios/v1/planes`
  - `PATCH` añade o actualiza: `{"planes":[{"clave":"web-basica","nombre":"Web básica","precios":{"PEN":2000,"USD":1200},"sufijo":{"PEN":"","USD":""}}]}`
  - `POST` con el mismo cuerpo reemplaza todos los planes.

## Usar en la web

```
[lucuma_precio plan="web-basica"]                 S/ 2.000      | $1,200
[lucuma_precio plan="seo-mensual"]                S/ 900/mes    | $450/mo
[lucuma_precio plan="seo-mensual" campo="numero"] 900           | 450
[lucuma_precio campo="moneda"]                    PEN           | USD
[lucuma_precio campo="simbolo"]                   S/            | $
```

En Bricks: elemento Shortcode o Rich Text con el shortcode, o datos dinámicos `{echo:lucuma_trp_precio(web-basica)}`.

- El texto alrededor ("Desde", "por mes") va fuera del shortcode y se traduce normal en TranslatePress.
- La salida va en `<span class="ltp-precio ltp-pen|ltp-usd" data-moneda data-monto data-no-translation>`: TranslatePress no la traduce.
- Si falta un precio, el visitante no ve nada; un administrador conectado ve un aviso amarillo `[precio sin definir: plan / moneda]`.

## Ajustes por código

- `ltp_monedas` (filtro): cambiar símbolos, separadores o qué idiomas usan cada moneda.
- `ltp_formatear` (filtro): formato final del precio.

## Pendiente

- Schema `priceCurrency` en las ofertas de SEOPress según idioma.
