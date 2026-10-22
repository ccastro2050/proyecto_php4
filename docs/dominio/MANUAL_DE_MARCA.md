# Manual de marca — Comercial Los Andes S.A.

> **Qué es este documento.** La identidad visual del sistema: el logosímbolo,
> la paleta con sus valores exactos, las tipografías y **dónde manda cada
> color**. Es una restricción, no una sugerencia.
>
> **La empresa es ficticia** —es el dominio del ejemplo del curso—, pero los
> contrastes están **calculados**, no estimados.
>
> ⚠️ **Y hay que decirlo de entrada: el front de este repositorio TODAVÍA NO
> aplica este manual.** Lo que hoy se ve es Bootstrap sin personalizar. La
> §6 dice exactamente qué falta, y está anotado en
> [`PENDIENTES.md`](PENDIENTES.md).
>
> Versión 1.0 · 22 de octubre de 2026.

---

## 1. El logosímbolo y sus tres piezas

```
Los Andes ▲ comercial s.a.
└───┬───┘ │  └─────┬─────┘
 logotipo │     descriptor
       isotipo
```

| Pieza | Qué es | Cómo se compone |
|---|---|---|
| **Logotipo** | La palabra | `Los Andes` en Georgia, **versalitas**, interletraje +2px |
| **Isotipo** | El símbolo | El triángulo `▲` en ámbar — la cordillera |
| **Descriptor** | Qué es la empresa | `comercial s.a.` en minúscula, tamaño menor |

> **Los tres van juntos y en ese orden.** El isotipo se usa suelto solo cuando
> no cabe el conjunto —un favicon, una pestaña—, nunca por gusto.

---

## 2. La paleta, con sus contrastes calculados

| Nombre | Hex | Para qué | Sobre `niebla` | Con blanco encima |
|---|---|---|---|---|
| **azul cordillera** | `#17495B` | Color principal: títulos, barra, botón primario | **8.76** ✅ AA | **9.81** ✅ AA |
| **verde páramo** | `#2F7D5C` | Lo que salió bien: confirmaciones, factura activa | **4.46** ⚠ solo texto grande | **4.99** ✅ AA |
| **rojo anulada** | `#B4402F` | Lo que falló o se anuló | **5.05** ✅ AA | **5.65** ✅ AA |
| **piedra** | `#4A4A4A` | Texto corriente | **7.92** ✅ AA | **8.86** ✅ AA |
| **ámbar cosecha** | `#E39B2D` | **Solo acento**: el isotipo y el foco | **2.09** ❌ | **2.34** ❌ |
| **niebla** | `#F4F2EC` | El fondo | — | — |

> Los números son contrastes **WCAG**, con la fórmula `(L1+0.05)/(L2+0.05)`.
> El nivel **AA** pide **4.5** para texto normal y **3.0** para texto grande o
> componentes de interfaz.

### Las dos reglas que salen de esa tabla

> **1. El ámbar NUNCA lleva texto, ni es texto.** Con 2.09 sobre el fondo no
> alcanza ni el mínimo de texto grande. Si alguna vez hace falta una palabra
> sobre ámbar, va en `piedra` o en `azul cordillera` — nunca en blanco.

> **2. El verde páramo no se usa para texto pequeño sobre el fondo.** 4.46 se
> queda a cuatro centésimas del 4.5, y eso no se redondea hacia arriba. Para
> texto, en negrita o grande; para un botón, con **blanco encima**, donde sí
> llega a 4.99.

---

## 3. Las tipografías

| Uso | Fuente | Por qué |
|---|---|---|
| Logotipo y títulos | **Georgia**, serif | Da peso institucional y está en todos los sistemas: no hay que descargarla |
| Texto y formularios | **la del sistema** (`system-ui`) | Se lee igual en Windows, macOS y Linux, y no cuesta una petición de red |
| Datos tabulares, códigos, dinero | **monoespaciada** | Las cifras se alinean por columna: `2.500.000` y `150.000` se comparan de un vistazo |

---

## 4. Dónde manda cada color en la interfaz

| Elemento | Color |
|---|---|
| Barra superior | azul cordillera, texto en blanco |
| Botón principal («Agregar», «Emitir») | azul cordillera con texto blanco |
| Botón secundario («Volver») | borde azul, fondo transparente |
| Botón destructivo («Eliminar», «Anular») | rojo anulada con texto blanco |
| Aviso de éxito | verde páramo |
| Aviso de error | rojo anulada |
| Etiqueta de factura **activa** | verde páramo |
| Etiqueta de factura **anulada** | rojo anulada |
| Fondo de la página | niebla |
| Texto corriente | piedra |
| Foco del teclado | ámbar, con 2px de grosor |

> **El foco en ámbar es una decisión de accesibilidad, no de estética.** El
> contorno que Bootstrap pone por defecto es del mismo azul del botón: sobre
> un botón azul **no se ve**, y quien navega con el teclado pierde el rastro.

---

## 5. Cómo se implementa esto en PHP

La marca vive en **un archivo de variables**, y las pantallas usan la
variable, nunca el valor:

```css
/* front_php/publico/marca.css */
:root {
  --azul-cordillera: #17495B;
  --verde-paramo:    #2F7D5C;
  --rojo-anulada:    #B4402F;
  --piedra:          #4A4A4A;
  --ambar-cosecha:   #E39B2D;
  --niebla:          #F4F2EC;
}
```

> **Por qué variables y no el hex en cada vista.** Con diecisiete vistas, un
> color escrito a mano en cada una son diecisiete sitios donde cambiarlo — y
> dieciséis donde olvidarlo. Con variables, **un color que no esté en el
> manual sencillamente no existe en la interfaz**.
>
> Y va **después** de Bootstrap en el `<head>`: lo último que se carga es lo
> que manda.

---

## 6. ⚠️ Lo que falta para que el front cumpla este manual

Medido hoy en `front_php/`:

| | Estado |
|---|---|
| `publico/estilos.css` | **23 líneas** y dos reglas: el borde punteado y el campo de solo lectura. Ningún color de marca |
| `publico/marca.css` | **No existe** |
| La barra | `navbar-dark bg-dark` — el gris de Bootstrap, no azul cordillera |
| El nombre | dice **«Empresa de Ejemplo»**, no «Los Andes ▲ comercial s.a.» |
| Logosímbolo | no hay |
| Fondo, tipografías, foco | los de Bootstrap |

**Qué habría que hacer**, en orden:

1. crear `publico/marca.css` con las seis variables de la §2;
2. cargarlo **después** de Bootstrap en `vistas/plantilla.php`;
3. poner el logosímbolo en la barra, con el nombre real;
4. mapear los colores de la §4 sobre las clases de Bootstrap que ya usan las
   vistas (`btn-primary`, `btn-outline-danger`, `alert-success`…);
5. comprobar el resultado con la §7.

> **Esto es criterio de la v4** —«imagen corporativa con su manual de marca, y
> la interfaz gráfica lo cumple»—, así que mientras no esté, la v4 de este
> repositorio tiene ese criterio **en rojo**. Decirlo aquí es más útil que
> escribir un manual que describa una interfaz que no existe.

---

## 7. Cómo comprobar que la interfaz cumple el manual

| Qué se mira | Cómo |
|---|---|
| Que no haya colores fuera del manual | Buscar `#` en las vistas: cada hex que aparezca tiene que estar en la §2 — o ser una variable |
| Que el contraste alcance | Herramientas del navegador → Inspeccionar → la pestaña de accesibilidad da el contraste de cada texto |
| Que el foco se vea | Recorrer una pantalla **solo con el tabulador**: tiene que saberse siempre dónde está |
| Que funcione en pantalla pequeña | Reducir la ventana a 360 px de ancho: sin barra de desplazamiento horizontal |
