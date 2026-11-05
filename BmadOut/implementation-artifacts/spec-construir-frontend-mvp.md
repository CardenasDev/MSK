---
title: 'Construir frontend MVP del Gestor de Música'
type: 'feature'
created: '2026-11-05'
status: 'done'
route: 'dispatch'
review_loop_iteration: 0
baseline_commit: '7d2900375ae7665b657ccb9fa554df33ff55ff6d'
context:
  - 'C:/D/Repo/MSK/BmadOut/planning-artifacts/prds/prd-MSK-2026-11-05/prd.md'
  - 'C:/D/Repo/MSK/BmadOut/planning-artifacts/architecture/architecture-MSK-2026-11-05/ARCHITECTURE-SPINE.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** El proyecto contiene únicamente estilos y la página HTML base, por lo que el usuario no puede gestionar el catálogo, las playlists ni los grupos definidos en el PRD.

**Approach:** Construir la SPA Angular standalone del MVP con navegación, estado reactivo basado en Signals, servicios HTTP tipados y vistas responsivas para catálogo, playlists y grupos, conectadas a los endpoints PHP existentes.

## Boundaries & Constraints

**Always:** Mantener Angular standalone, `ChangeDetectionStrategy.OnPush`, Signals/RxJS, respuestas `{ status, data, meta, message }`, validación de formularios, estados de carga/error/vacío, debounce mínimo de 300 ms para búsquedas y proxy `/api`.

**Never:** Implementar reproducción de audio, autenticación, carga de archivos, integraciones externas ni modificar el backend o el esquema de base de datos como parte de esta entrega.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Catálogo | Búsqueda, página y límite | Solicitud paginada después de 300 ms y tabla actualizada | Mostrar error recuperable y conservar la vista |
| Crear entidad | Nombre vacío o espacios | No se envía la solicitud y se muestra validación | Mensaje junto al campo |
| API no disponible | Cualquier carga o mutación | La vista deja de cargar y permanece usable | Mensaje visible en español |
| Relación duplicada | Canción/lista ya vinculada | La lista conserva su estado y no agrega duplicado | Mostrar mensaje del backend |

</frozen-after-approval>

## Code Map

- `frontend/angular.json` -- configuración Angular 18 standalone, build y proxy de desarrollo.
- `frontend/src/styles.scss` -- sistema visual oscuro existente; reutilizar variables, botones, formularios, tablas y modales.
- `frontend/src/index.html` -- shell HTML en español y punto de montaje `app-root`.
- `frontend/src/` -- actualmente no contiene `main.ts` ni componentes; crear la estructura `app/core`, `app/features` y rutas lazy.
- `backend/index.php` -- rutas REST disponibles para canciones, playlists, grupos y relaciones pivote.
- `backend/controllers/SongController.php` -- filtros `search`, `genre`, `page`, `limit`; la respuesta incluye metadatos de paginación.
- `backend/controllers/PlaylistController.php` -- CRUD de playlists y restauración.
- `backend/controllers/MusicGroupController.php` -- CRUD de grupos y restauración.
- `backend/controllers/PlaylistSongController.php` y `GroupPlaylistController.php` -- listar, agregar, quitar y reordenar relaciones.

## Tasks & Acceptance

**Execution:**
- [x] `frontend/src/main.ts`, `app.config.ts`, `app.routes.ts` -- inicializar Angular standalone, HttpClient, navegación y shell de aplicación.
- [x] `frontend/src/app/core/models/*` y `core/services/*` -- definir modelos, envelope API, manejo de errores y servicios HTTP tipados.
- [x] `frontend/src/app/features/catalog/*` -- implementar catálogo, búsqueda con debounce, paginación, detalle y CRUD de canciones.
- [x] `frontend/src/app/features/playlists/*` -- implementar listado, CRUD, detalle, asociación, remoción y reordenamiento.
- [x] `frontend/src/app/features/groups/*` -- implementar listado, CRUD, detalle y asociación/remoción de playlists.
- [x] `frontend/src/app/shell/*` y estilos relacionados -- implementar navegación, layout responsivo, feedback y modales reutilizables.

**Acceptance Criteria:**
- Given la aplicación iniciada, when el usuario visita `/`, then es dirigido al catálogo y puede navegar a playlists y grupos sin recarga completa.
- Given un catálogo disponible, when el usuario escribe una búsqueda, then la API se consulta una sola vez tras al menos 300 ms y la tabla muestra resultados y paginación.
- Given un nombre válido, when el usuario crea, edita o elimina una playlist o grupo, then la vista se actualiza y muestra el mensaje de resultado.
- Given una playlist o grupo seleccionado, when el usuario administra relaciones, then puede agregar, quitar y reordenar sin alterar la entidad maestra relacionada.
- Given un error HTTP, when la solicitud termina, then se muestra un mensaje en español y se libera el estado de carga.
- Given una ventana de 375 px o más, when se renderiza cualquier vista, then el contenido sigue siendo usable sin desbordamiento horizontal innecesario.

## Implementation Notes

## Verification

**Commands:**
- `cd frontend; npm install` -- esperado: dependencias instaladas sin errores.
- `cd frontend; npm run build` -- esperado: compilación Angular exitosa.

**Manual checks (if no CLI):**
- Iniciar PHP en `localhost:8000` y Angular en `localhost:4200`; comprobar navegación, CRUD y relaciones desde el navegador.
