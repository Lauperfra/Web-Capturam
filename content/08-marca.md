# 08 · Dirección visual y marca

Un sistema sobrio, técnico y contemporáneo, coherente con el logotipo.

## Paleta de color (extraída de la marca)

| Uso | Color | Hex |
|---|---|---|
| Azul pizarra | Titulares, navegación, fondos y elementos de autoridad | `#596074` |
| Verde agua | Acentos, enlaces, datos y estados positivos | `#91B5B4` |
| Azul profundo | Contraste, footer, tablas y llamadas principales | `#102D3A` |
| Blanco cálido | Fondos suaves y respiración visual | `#F3F6F4` |
| Blanco | Fondos principales y contraste | `#FFFFFF` |

## Tipografía y composición
Sans serif legible y técnica, con títulos de gran tamaño, párrafos cortos, anchura de lectura contenida y abundante espacio. La sensación de confianza debe proceder de la precisión, no de efectos visuales. Evitar carruseles automáticos, contadores no demostrables y animaciones que dificulten la lectura.

## Fotografía
- Priorizar imágenes propias de emplazamientos, visitas, equipos y entregables; son la prueba visual más creíble.
- Mostrar escala, infraestructura y contexto territorial. Evitar imágenes genéricas de bombillas, hojas, enchufes o apretones de manos.
- Mantener luz natural, saturación contenida y horizontes limpios. Usar planos aéreos solo cuando expliquen implantación o integración.
- Reservar espacio negativo para titulares y comprobar recortes en escritorio, móvil y redes sociales.
- No colocar el logotipo sobre fondos sin contraste; utilizar las versiones positiva o negativa adecuadas.

## Banco inicial de imágenes (solo para maquetar, no son reales)
Imágenes conceptuales generadas para maquetación — **no representan proyectos reales de Capturam** y deben identificarse internamente como material conceptual generado por IA hasta sustituirlas por fotografía real:
1. Hero híbrido: solar, eólica, BESS y subestación → uso recomendado: portada.
2. Solar fotovoltaica con inspección técnica → sector FV / ingeniería.
3. Parque eólico e infraestructura de evacuación → sector eólico.
4. BESS asociado a fotovoltaica y subestación → BESS / hibridación.

Para casos de éxito y referencias concretas, usar exclusivamente fotografías y documentación reales con autorización.

## Especificación de assets

| Activo | Formato / tamaño recomendado | Notas |
|---|---|---|
| Logotipo principal | SVG + PNG transparente | Versiones color positiva, color negativa, negro y blanco. |
| Favicon | SVG + PNG 32/48/180/512 px | Usar isotipo "C" y comprobar legibilidad a 16–32 px. |
| Hero escritorio | WebP/AVIF, 2400 × 1350 px | Punto focal desplazado; mantener espacio para copy. |
| Hero móvil | WebP/AVIF, 1080 × 1440 px | Recorte específico, no automático desde escritorio. |
| Sectores | WebP/AVIF, 1600 × 1067 px | Una imagen coherente por tecnología. |
| Proyectos | WebP/AVIF, 1600 × 1067 px | Fotografía propia y plano/diagrama real autorizado. |
| Open Graph | JPG, 1200 × 630 px | Plantilla con titular corto, marca y alto contraste. |
| Iconos | SVG | Sistema lineal único; solar, eólica, BESS, red, terreno y permisos. |
| Documentos descargables | PDF accesible | Presentación corporativa, fichas y política de calidad si existe. |

## Rendimiento y accesibilidad
- Generar `srcset` y tamaños responsivos; evitar cargar el hero 4K en móvil.
- Definir texto alternativo descriptivo, no repetir el pie de foto.
- Mantener contraste WCAG AA y navegación completa por teclado.
- No incrustar texto relevante dentro de imágenes.
- Aplicar lazy loading fuera del primer viewport y reservar dimensiones para evitar saltos de diseño.

*Fuente: Dossier de contenidos para la web — Capturam Ingeniería, agosto 2026, secciones 10–12.*

**Logo real disponible en:** `static/img/logo-capturam.png` (facilitado directamente por Laura).
