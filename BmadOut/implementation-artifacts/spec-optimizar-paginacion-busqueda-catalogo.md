---
title: 'Optimizar búsqueda y paginación del catálogo'
type: 'feature'
created: '2026-11-05'
status: 'done'
route: 'dispatch'
review_loop_iteration: 0
 baseline_commit: '7ea706e9c8e6bb780d3be1e4f7c40148c03e5755'
context:
  - 'C:/D/Repo/MSK/frontend/src/app/features/catalog/pages/song-catalog.component.ts'
  - 'C:/D/Repo/MSK/frontend/src/app/core/services/song-api.service.ts'
  - 'C:/D/Repo/MSK/backend/controllers/SongController.php'
  - 'C:/D/Repo/MSK/BD/stored_procedures.sql'
  - 'C:/D/Repo/MSK/BD/schema.sql'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** El catálogo puede superar los 1000 registros y la experiencia debe consultar únicamente la página solicitada, evitando cargar todos los resultados en el navegador. El listado debe permitir controlar desde la interfaz el número de canciones por página.

**Approach:** Mantener la respuesta paginada del endpoint de canciones, reforzar la consulta para que use el índice de búsqueda disponible cuando sea apropiado, validar los parámetros de página y límite en el backend, y exponer tamaños de página pequeños y configurables en el catálogo.

## Boundaries & Constraints

**Always:** Enviar `page` y `limit` como parámetros de cada listado; devolver solo los registros de la página solicitada y metadatos consistentes; conservar búsqueda por título, artista y álbum; resetear a la primera página al cambiar búsqueda o límite; mantener la respuesta API existente y mensajes en español.

**Never:** Cargar el catálogo completo para paginar en Angular; eliminar el conteo total necesario para la paginación; modificar datos existentes, autenticación, CRUD no relacionado o el contrato general `{ status, data, meta, message }`.

**Decisions:** El selector ofrecerá `5, 10, 15, 25, 50 y 100` registros por página. La búsqueda conservará coincidencia parcial por título, artista y álbum para mantener la compatibilidad actual; la optimización se centrará en paginar y limitar en la base de datos, sin cambiar la semántica de resultados.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Listado paginado | `page=2`, `limit=10` | La API devuelve como máximo 10 canciones de la segunda página y `meta` correcto | Normalizar valores inválidos a límites seguros |
| Búsqueda | Texto y parámetros de página | La consulta filtra en BD antes de aplicar `LIMIT/OFFSET`; el navegador recibe solo la página | Mantener respuesta de error visible si falla la API |
| Cambio de límite | 5, 10, 15, 25 u otro tamaño permitido | Se solicita nuevamente la página 1 con el nuevo límite | No conservar una página fuera del rango |
| Última página vacía | Página mayor que `totalPages` | La respuesta es una lista vacía con metadatos válidos | No producir error ni cargar registros adicionales |

</frozen-after-approval>

## Code Map

- `frontend/src/app/features/catalog/pages/song-catalog.component.ts` -- selector de límite, estado de página, debounce de búsqueda y renderizado de metadatos.
- `frontend/src/app/core/services/song-api.service.ts` -- serializa `page`, `limit` y filtros al endpoint.
- `frontend/src/app/core/models/song.model.ts` -- contrato de filtros de canciones.
- `backend/controllers/SongController.php` -- normaliza query params, ejecuta el procedimiento y construye `meta`.
- `BD/stored_procedures.sql` -- procedimiento `sp_song_list` con conteo y consulta paginada.
- `BD/schema.sql` -- índices de `songs`, incluido el índice FULLTEXT de búsqueda.
- `stored_procedures.sql` y `schema.sql` -- copias SQL raíz que deben conservar el mismo comportamiento si son la fuente de instalación.

## Tasks & Acceptance

**Execution:**
- [x] `BD/stored_procedures.sql`, `stored_procedures.sql` -- optimizar y mantener consistente la consulta de búsqueda paginada.
- [x] `backend/controllers/SongController.php` -- validar `page`, `limit` y `offset` sin permitir listados masivos accidentales.
- [x] `frontend/src/app/features/catalog/pages/song-catalog.component.ts` -- ofrecer tamaños configurables y navegar usando metadatos del servidor.
- [x] `frontend/src/app/core/services/song-api.service.ts` -- conservar el envío tipado de parámetros de paginación y filtros.

**Acceptance Criteria:**
- Given más de 1000 canciones, when se solicita una página, then la respuesta contiene como máximo el límite solicitado y nunca el catálogo completo.
- Given una búsqueda por título o artista, when se ejecuta, then el filtrado ocurre en la base de datos antes de aplicar la paginación.
- Given el usuario cambia página o límite, when el listado se actualiza, then la solicitud incluye los valores actuales de `page` y `limit`.
- Given parámetros ausentes, cero, negativos o excesivos, when llega la solicitud, then el backend aplica valores seguros y devuelve metadatos coherentes.

## Implementation Notes

- Se normalizaron `page`, `limit` y `offset` en el controlador, con límite máximo de 100 registros y protección ante parámetros enviados como arreglos.
- La consulta `sp_song_list` mantiene el filtrado parcial por título, artista y álbum y aplica `LIMIT/OFFSET` en la base de datos. Se mantuvieron sincronizadas las copias SQL de `BD/` y raíz.
- El catálogo ofrece 5, 10, 15, 25, 50 y 100 registros por página, reinicia la página al cambiar límite o búsqueda y muestra errores de carga.
- La revisión descartó como preexistentes los movimientos de archivos SQL y confirmó que los esquemas ya contienen `idx_songs_search`.

## Review Triage Log

| Finding | Verdict | Evidence |
|---------|---------|----------|
| Parámetros `limit`, `page` u `offset` enviados como arreglos podían producir `TypeError`. | fixed | El controlador verifica `is_string()` antes de aplicar `filter_var()`. |
| `offset` explícito podía contradecir la página reportada en metadata. | fixed | `page` tiene precedencia; el `offset` legado solo se usa cuando no llega `page` y recalcula la página. |
| Errores de búsqueda/paginación no actualizaban el mensaje visible. | fixed | `load()` usa callback `error` y `ApiErrorService`. |
| No existe prueba de integración contra MySQL. | accepted-risk | No hay infraestructura de pruebas de backend en el repositorio; se ejecutaron aserciones estáticas y validaciones de sintaxis/compilación. |
| El índice FULLTEXT faltaría en bases existentes. | false | `BD/schema.sql` y `schema.sql` ya declaran `FULLTEXT INDEX idx_songs_search`. |
| Los archivos SQL de semillas eliminados son parte del cambio de paginación. | false | El estado inicial ya mostraba esos archivos eliminados y sus copias movidas a `BD/`, por lo que se preservaron como cambios preexistentes. |

## Verification

**Commands:**
- `cd frontend; npm run build` -- esperado: compilación Angular exitosa.
- `php -l backend/controllers/SongController.php` -- esperado: sintaxis PHP válida.
- Aserciones estáticas de paginación -- esperado: parámetros, precedencia de offset, manejo de errores y `LIMIT/OFFSET` presentes.
