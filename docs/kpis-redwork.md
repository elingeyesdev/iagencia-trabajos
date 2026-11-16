# RedWork — Sistema de KPIs (fichas técnicas)

Implementación: `app/Http/Controllers/Api/KpiApiController.php` (cálculos, una sola implementación
parametrizada por alcance) · `resources/js/kpi-dashboard.js` y `resources/css/kpi-dashboard.css`
(solo presentación) · pruebas: `tests/Feature/Api/KpiIndicadoresTest.php`, `tests/js/kpi-dashboard.test.mjs`.

Fundamento: criterios de Chavarría Briceño (2025), *El indicador clave de desempeño (KPI)*,
Revista FAECO SAPIENS 8(2), 254–264 (definición clara, métricas objetivas, recolección consistente,
precisión y validez, periodicidad, oportunidad y alineación estratégica), aplicados conjuntamente; y el
enfoque metodológico de medición de BA Better World (alineación con objetivos, comparación entre
períodos, evolución histórica, metas medibles, desviaciones). No se usan indicadores ni cifras de aviación.

---

## 1. Problemas de los KPIs anteriores

| Indicador anterior | Problema |
|---|---|
| Publicaciones activas | Contador de estado: no indica si las ofertas atraen interés ni se compara con nada. |
| Contrataciones activas | Contador: no distingue éxito, cancelación ni demora; sube y baja sin interpretación. |
| Total de pagos simulados confirmados (Bs) | Monto acumulado sin período ni denominador; no mide la gestión de validación y puede leerse como ingreso real. |

Ninguno tenía período, comparación, denominador, meta, interpretación ni vínculo explícito con un
objetivo. Se conservan como **estadísticas auxiliares de contexto** (`GET /api/admin/kpis`, sin cambios
de contrato) debajo del nuevo panel.

## 2. Objetivos estratégicos

| Id | Objetivo |
|---|---|
| A | Efectividad de las publicaciones: ¿las ofertas generan interés y postulaciones? |
| B | Eficiencia de las postulaciones: ¿los contratadores deciden oportunamente? |
| C | Conversión a contrataciones: ¿qué proporción de postulaciones se concreta? |
| D | Cumplimiento de contrataciones: ¿se finalizan los acuerdos o se cancelan? |
| E | Control de pagos simulados: registro, confirmación y acumulación de pendientes. |
| F | Calidad del servicio: satisfacción según las calificaciones existentes. |

## 3. Auditoría de datos (resumen) y parámetros metodológicos

**Fuentes reales verificadas** (migraciones y controladores):

- `publicaciones.fecha_publicacion` (date, se fija al crear y no se edita); `estado` 1/0 (la baja es lógica); sin fecha de cierre.
- `postulaciones.fecha_postulacion` (date); estados `pendiente/aceptada/rechazada/cancelada`; `UNIQUE(id_labor, cedula)`.
  **No hay fecha de decisión ni historial de estados.** La cancelación del trabajador **borra físicamente** la fila.
- `contrataciones`: `UNIQUE(id_postulacion)`; al aceptar se crea con `fecha_inicio` = hoy; `fecha_fin` se graba al pasar a
  `finalizada` o `cancelada` (transiciones solo desde `activa`).
- `pagos`: `fecha_registro`, `fecha_confirmacion`, `fecha_anulacion` (datetime de auditoría), `estado`, `es_simulado`, `monto`.
- `calificaciones`: `fecha` (date), `puntuacion` 1–5, `UNIQUE(id_contratacion, cedula_calificador)`; solo sobre contrataciones finalizadas; ambas partes califican.
- Sin claves foráneas en el dominio: las consultas usan `JOIN` (los registros huérfanos quedan excluidos).
- Sin tablas con `timestamps`: todos los períodos usan las fechas propias del evento.

**Parámetros metodológicos** (constantes de `KpiApiController`; **pendientes de aprobación** del propietario):

| Parámetro | Valor | Motivo |
|---|---|---|
| Maduración de cohortes de postulaciones | 7 días (0 para el trabajador) | No juzgar como «sin decisión» solicitudes recién llegadas. |
| Ventana de observación de publicaciones | 14 días | Comparar publicaciones con la misma oportunidad de recibir postulantes. |
| Umbral de antigüedad para alertas | 7 días | Señalar pendientes concretos que requieren atención. |
| Muestra mínima para comparar | 10 casos por período | Evitar conclusiones con pocos datos. |
| Significación de una variación | Prueba z bilateral, 95 % (|z| ≥ 1,96) | Solo se declara «mejora» o «deterioro» si es estadísticamente justificable. |

**Períodos:** últimos 30 días, últimos 90 días, año actual (parcial, comparado con el mismo tramo del año
anterior) e intervalo personalizado (máx. 731 días, sin fechas futuras). El período anterior es el
inmediatamente previo de igual duración. Denominador cero → `null` → «Sin datos suficientes».

**Metas:** no existe referencia histórica aprobada. La primera medición sobre datos reales constituye la
**línea base**; todas las metas figuran como **pendientes de aprobación**. No se inventaron metas.

---

## 4. Fichas técnicas de los KPIs implementados

### KPI 1 — Efectividad de publicaciones (`efectividad_publicaciones`)

| Campo | Contenido |
|---|---|
| Objetivo | A |
| Definición | Proporción de publicaciones de una cohorte que recibió al menos una postulación dentro de sus primeros 14 días. |
| Fórmula | `(publicaciones con 1.ª postulación ≤ 14 días después de publicarse / publicaciones de la cohorte) × 100` |
| Numerador / denominador | Publicaciones con éxito / publicaciones con `fecha_publicacion` en el período y `≤ hoy − 14 días`. |
| Fuente | `publicaciones` ⟕ `postulaciones` (MIN(`fecha_postulacion`) por publicación, cualquier estado). |
| Filtros | Período; categoría (admin); propietario (contratador). Incluye publicaciones desactivadas después (la baja es posterior a la oportunidad). |
| Unidad | % (1 decimal) |
| Frecuencia | Al cargar o filtrar el dashboard (consulta en línea a la API). |
| Comparación | Cohorte del período anterior equivalente. |
| Responsable | Administrador (global y por categoría); contratador (sus ofertas). |
| Meta | Línea base inicial; meta pendiente de aprobación. |
| Interpretación | Valor bajo → ofertas que no generan interés (revisar pago, descripción, categoría). |
| Decisión | Reforzar o revisar categorías con baja efectividad; orientar a contratadores sobre sus ofertas. |
| Limitaciones | Las postulaciones canceladas se borran físicamente: si la única postulación fue retirada, la publicación figura sin interés. Sin historial de visibilidad (estado 1/0). |

### KPI 2 — Tasa de gestión de postulaciones (`gestion_postulaciones`)

| Campo | Contenido |
|---|---|
| Objetivo | B |
| Definición | Porcentaje de postulaciones evaluables que ya tiene una decisión (aceptada o rechazada). |
| Fórmula | `((aceptadas + rechazadas) / (pendientes + aceptadas + rechazadas)) × 100` |
| Numerador / denominador | Postulaciones con decisión / postulaciones evaluables de la cohorte. |
| Fuente | `postulaciones` ⨝ `publicaciones`. |
| Filtros | `fecha_postulacion` en el período y con ≥ 7 días de antigüedad; excluye `cancelada` (retirada por el trabajador). Trabajador: sin maduración y solo sus postulaciones («Postulaciones con respuesta»). |
| Unidad | % |
| Frecuencia | Al cargar o filtrar. |
| Comparación | Cohorte del período anterior. |
| Responsable | Administrador; contratador (las suyas). |
| Meta | Línea base; pendiente de aprobación. |
| Interpretación | Valor bajo → solicitudes sin atender. |
| Decisión | Recordar a contratadores con pendientes; detectar cuellos de botella. |
| Limitaciones | Mide el estado actual de la cohorte, no el momento de la decisión (no existe esa fecha). |

### KPI 3 — Tiempo de respuesta → **no calculable**; sustituido por *Antigüedad de postulaciones pendientes*

| Campo | Contenido |
|---|---|
| Objetivo | B |
| Motivo del reemplazo | No existe fecha de evaluación ni historial de estados. `fecha_inicio` de la contratación coincide con la aceptación solo por la vía de aceptar, pero `ContratacionApiController::store` admite una fecha libre, y el rechazo no guarda fecha. No se usa ninguna fecha sustituta sin demostrar que represente el evento. |
| Definición alternativa | Antigüedad (días) de las postulaciones que **hoy** siguen pendientes. |
| Fórmula | `antigüedad = hoy − fecha_postulacion`; se informa cantidad, mediana, máximo y número con antigüedad > 7 días. |
| Fuente | `postulaciones` (estado `pendiente`) ⨝ `publicaciones`. |
| Unidad | Días · cantidad |
| Comparación | No aplica (estado actual, sin histórico de estados). |
| Responsable / decisión | Administrador y contratador: atender primero las solicitudes más antiguas (el contratador ve sus 5 más antiguas, sin datos personales del trabajador). |
| Requisito para el KPI original | Columna aditiva `fecha_decision` (o tabla de historial de estados) en `postulaciones`: **pendiente de autorización**. |

### KPI 4 — Conversión a contratación (`conversion_contratacion`)

| Campo | Contenido |
|---|---|
| Objetivo | C |
| Definición | Proporción de las postulaciones con decisión que generó una contratación válida. |
| Fórmula | `(postulaciones aceptadas con contratación / (aceptadas + rechazadas)) × 100` |
| Numerador / denominador | `COUNT(DISTINCT contratación)` de postulaciones aceptadas / postulaciones decididas de la misma cohorte que el KPI 2. |
| Fuente | `postulaciones` ⟕ `contrataciones` (`UNIQUE(id_postulacion)` garantiza una contratación por postulación; además se usa `COUNT(DISTINCT)`). |
| Tratamiento de pendientes | Excluidas del denominador (aún no tienen resultado); su volumen lo informa el KPI 2. |
| Unidad | % |
| Comparación | Cohorte anterior. |
| Responsable | Administrador; contratador; trabajador («Postulaciones convertidas en contratación»). |
| Meta | Línea base; pendiente de aprobación. |
| Interpretación | Indica la efectividad del proceso de selección. |
| Decisión | Identificar categorías u ofertas donde se pierden oportunidades. |
| Limitaciones | Una aceptada sin contratación (datos heredados) no cuenta como conversión y se reporta en `calidad_datos.aceptadas_sin_contratacion`. |

### KPI 5 — Finalización de contrataciones (`finalizacion_contrataciones`)

| Campo | Contenido |
|---|---|
| Objetivo | D |
| Definición | Proporción de contrataciones cerradas en el período que terminó como `finalizada`. |
| Fórmula | `(finalizadas / (finalizadas + canceladas)) × 100`, por `fecha_fin` en el período. |
| Registros elegibles | Solo resultado definitivo; las `activa` **no** cuentan como fracaso. |
| Fuente | `contrataciones` ⨝ `postulaciones` ⨝ `publicaciones`. |
| Unidad | % · complementario: tasa de cancelación = 100 − finalización. |
| Comparación | Período anterior. |
| Responsable | Administrador; contratador; trabajador. |
| Meta | Línea base; pendiente de aprobación. |
| Interpretación | Una caída con significación estadística se marca como deterioro (más cancelaciones). |
| Decisión | Investigar causas de cancelación por categoría o contratador. |
| Limitaciones | Granularidad diaria; contrataciones cerradas sin `fecha_fin` (heredadas) se excluyen y se informan en `calidad_datos`. |

### KPI 6 — Confirmación de pagos simulados (`confirmacion_pagos`)

| Campo | Contenido |
|---|---|
| Objetivo | E |
| Definición | Proporción de los pagos simulados válidos (no anulados) registrados en el período que ya fue confirmada. |
| Fórmula | `(confirmados / (confirmados + pendientes)) × 100`, por `fecha_registro` y estado actual. |
| Anulados | Excluidos del denominador para no distorsionar la eficiencia de validación; se informan aparte: `tasa_anulacion = anulados / registrados × 100`. |
| Complemento | Mediana de horas entre `fecha_registro` y `fecha_confirmacion` (fechas de auditoría reales). |
| Fuente | `pagos` (solo `es_simulado = 1`) ⨝ contrataciones ⨝ postulaciones ⨝ publicaciones. |
| Unidad | % · horas |
| Comparación | Período anterior. |
| Responsable | Administrador (confirma); contratador (sus registros). |
| Meta | Línea base; pendiente de aprobación. |
| Decisión | Priorizar la validación administrativa; detectar acumulación. |
| Limitaciones | Registros muy recientes aún pendientes reducen el valor del período en curso. Montos SIMULADOS: no son ingresos ni dinero depositado. |

### KPI 7 — Pagos simulados pendientes de confirmación (estado actual)

| Campo | Contenido |
|---|---|
| Objetivo | E |
| Medidas | Cantidad, monto total pendiente (Bs, simulado), antigüedad mediana y máxima (días desde `fecha_registro`), cantidad con más de 7 días. |
| Fuente | `pagos` con `estado = pendiente` y `es_simulado = 1`. |
| Comparación | No aplica (estado actual). |
| Decisión | Identificar operaciones concretas que requieren confirmación o anulación. |
| Limitaciones | No representa deuda bancaria ni dinero real. |

### KPI 8 — Calidad percibida (`calidad_calificaciones`)

| Campo | Contenido |
|---|---|
| Objetivo | F |
| Definición | Promedio de las puntuaciones (1–5) emitidas en el período, siempre con su número de calificaciones. |
| Fórmula | `Σ puntuación / n`; distribución por puntuación; desglose por rol evaluado (admin); cobertura = finalizadas con ≥ 1 calificación / finalizadas. |
| Sentido | Contratador → califica al trabajador; trabajador → califica al contratador. El contratador y el trabajador ven solo las calificaciones **recibidas**. |
| Comparación | Prueba z de diferencia de medias (varianza por período), mínimo 10 calificaciones por período. |
| Unidad | Puntos (1–5) |
| Responsable | Administrador; cada usuario sobre sí mismo. |
| Meta | Línea base; pendiente de aprobación. |
| Decisión | Detectar áreas o participantes con baja satisfacción. |
| Limitaciones | Calificación voluntaria (sesgo de respuesta); con n < 10 se muestra «muestra reducida». |

### Complementarios incluidos

- **Tasa de cancelación** (KPI 5, lectura inversa) y **estado actual de contrataciones**.
- **Embudo** de una misma cohorte de postulaciones: evaluables → con decisión → con contratación → finalizada (denominador común).
- **Evolución mensual** (12 meses): volúmenes y tasas por mes; meses parciales o inmaduros marcados; tasas con denominador cero = `null`.
- **Resultados por categoría** (admin): efectividad, conversión y finalización por categoría.
- **Alertas**: solo deterioros estadísticamente significativos, pendientes que superan el umbral y avisos de calidad de datos.

## 5. Indicadores descartados

| Indicador | Motivo |
|---|---|
| Tiempo de respuesta (mediana) | Sin fecha de decisión fiable (ver KPI 3). |
| Porcentaje de postulaciones respondidas por contratador (ranking) | Redundante con KPI 2 por contratador; el administrador no necesita exponer desempeño individual nominal en el dashboard. Disponible para cada contratador en su propio panel. |
| Duración media de contrataciones | `fecha_inicio` puede fijarse libremente en `store`: no es confiable. |
| Ingresos por comisiones | Comisión 0 % y pagos simulados: siempre 0, sin valor de decisión (el reporte PDF de comisiones ya existe). |
| Publicaciones activas / contrataciones activas / total confirmado | Contadores sin denominador; quedan como estadísticas auxiliares. |

## 6. Distribución por rol

| Indicador | Administrador | Contratador (propios) | Trabajador (personal) |
|---|---|---|---|
| Efectividad de publicaciones (A) | ✔ global + categoría | ✔ sus ofertas | — |
| Gestión de postulaciones (B) | ✔ | ✔ «Postulaciones que evalué» | ✔ «Postulaciones con respuesta» |
| Antigüedad de pendientes (B) | ✔ | ✔ + 5 más antiguas | — |
| Conversión a contratación (C) | ✔ | ✔ | ✔ |
| Finalización de contrataciones (D) | ✔ | ✔ | ✔ |
| Confirmación de pagos simulados (E) | ✔ | ✔ sus registros | — (solo montos confirmados/pendientes) |
| Pagos pendientes (E) | ✔ | ✔ | ✔ (sus pagos) |
| Calidad (F) | ✔ + por rol + cobertura | ✔ recibidas | ✔ recibidas |
| Embudo, evolución | ✔ | ✔ | ✔ |
| Categorías, alertas de calidad de datos | ✔ | — | — |

## 7. Endpoints

| Método y ruta | Rol | Controlador |
|---|---|---|
| `GET /api/admin/kpis` | administrador | `KpiApiController@index` (auxiliares, contrato sin cambios) |
| `GET /api/admin/kpis/indicadores?periodo=&desde=&hasta=&categoria=` | administrador | `KpiApiController@indicadores` |
| `GET /api/contratador/kpis?periodo=&desde=&hasta=` | contratador | `KpiApiController@contratador` |
| `GET /api/trabajador/kpis?periodo=&desde=&hasta=` | trabajador | `KpiApiController@trabajador` |
| `GET /api/categorias` | autenticado | existente (opciones del filtro) |

Respuesta: `periodo` (con anterior), `parametros`, `indicadores[]` (valor, numerador, denominador, cohorte,
anterior, variación en puntos y evaluación), `embudo`, `pendientes`, `pagos`, `contrataciones`, `calidad`,
`evolucion[]`, `calidad_datos`, `categorias[]` (admin), `alertas[]`, `simulado: true` y `aviso`.
Sin datos personales (solo agregados; el contratador ve títulos de sus propias publicaciones).

## 8. Cambios de esquema pendientes de autorización (no ejecutados)

1. `postulaciones.fecha_decision` (datetime, nullable) — habilita el KPI de tiempo de respuesta.
2. Cancelación lógica de postulaciones (en lugar del borrado físico) — conserva el historial para los KPIs 1 y 2 (ya figura como defecto conocido).
3. Aprobación de los parámetros metodológicos de la sección 3 y de metas sobre la línea base.
