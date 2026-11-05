import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { debounceTime, distinctUntilChanged, Subject, finalize } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { BulkSongImportError, BulkSongImportRow, Song } from '../../../core/models/song.model';
import { SongApiService } from '../../../core/services/song-api.service';
import { ApiErrorService } from '../../../core/services/api-error.service';
import { AddToPlaylistModalComponent } from '../components/add-to-playlist-modal.component';
import * as XLSX from 'xlsx';

@Component({
  standalone: true,
  imports: [FormsModule, AddToPlaylistModalComponent],
  template: `
    <section class="page">
      <div class="page-heading"><div><p class="eyebrow">CATÁLOGO MAESTRO</p><h1>Canciones</h1></div><div class="table-actions"><button class="btn btn-secondary" (click)="downloadTemplate()">Descargar plantilla</button><button class="btn btn-primary" (click)="openForm()">Nueva canción</button><button class="btn btn-primary" (click)="openImport()">Importar canciones</button></div></div>
      @if (importOpen()) { <div class="modal-overlay"><section class="modal-content import-modal"><div class="modal-header"><h3>Importación masiva</h3><button type="button" class="close-btn" (click)="closeImport()">×</button></div><div class="modal-body">
        <p class="modal-description">Selecciona un archivo .xlsx o .xls (máximo 5 MB y 2.500 filas). La plantilla debe contener la hoja <strong>songs</strong>.</p>
        <div class="form-group"><label for="song-import-file">Archivo de canciones</label><input id="song-import-file" type="file" accept=".xlsx,.xls" (change)="inspectImportFile($event)" /></div>
        @if (importLoading()) { <p class="state">Analizando archivo…</p> }
        @if (importErrors().length) { <div class="alert error"><strong>Corrige estos errores antes de confirmar:</strong><ul>@for (error of importErrors(); track $index) { <li>Fila {{ error.row }}, {{ error.field }}: {{ error.message }}</li> }</ul><button class="btn btn-secondary" (click)="downloadImportErrors()">Descargar errores</button></div> }
        @if (importRows().length && !importErrors().length) { <div class="alert success">Se validaron {{ importRows().length }} filas. Revisa la previsualización y confirma para enviarlas al servidor.</div><div class="table-responsive import-preview"><table class="table"><thead><tr><th>Fila</th><th>Título</th><th>Artista</th><th>Álbum</th><th>Género</th><th>Duración</th></tr></thead><tbody>@for (row of importRows().slice(0, 10); track $index) { <tr><td>{{ $index + 2 }}</td><td>{{ row.title }}</td><td>{{ row.artist }}</td><td>{{ row.album || '—' }}</td><td>{{ row.genre || '—' }}</td><td>{{ row.duration_seconds ?? 0 }}</td></tr> }</tbody></table></div> }
        @if (importResult(); as result) { <div class="alert success">Importación completada: {{ result }} canciones insertadas.</div> }
      </div><div class="modal-footer"><button type="button" class="btn btn-secondary" (click)="closeImport()">Cancelar</button><button type="button" class="btn btn-primary" [disabled]="!importRows().length || importErrors().length > 0 || importLoading()" (click)="confirmImport()">Confirmar importación</button></div></section></div> }
      <div class="catalog-toolbar">
        <label class="catalog-search">
          <span class="search-icon" aria-hidden="true">⌕</span>
          <span class="sr-only">Buscar canciones</span>
          <input aria-label="Buscar canciones" [ngModel]="query()" (ngModelChange)="search$.next($event)" placeholder="Buscar por título, artista o álbum..." />
          @if (query()) { <button type="button" class="search-clear" aria-label="Limpiar búsqueda" (click)="search$.next('')">×</button> }
        </label>
        <label class="page-size">
          <span>Mostrar</span>
          <select aria-label="Canciones por página" [ngModel]="limit()" (ngModelChange)="setLimit($event)"><option [ngValue]="5">5</option><option [ngValue]="10">10</option><option [ngValue]="15">15</option><option [ngValue]="25">25</option><option [ngValue]="50">50</option><option [ngValue]="100">100</option></select>
          <span>canciones</span>
        </label>
      </div>
      @if (errors.message()) { <div class="alert error">{{ errors.message() }}</div> }
      @if (loading()) { <p class="state">Cargando catálogo…</p> } @else if (!songs().length) { <p class="state">No hay canciones para mostrar.</p> } @else {
        <div class="table-responsive"><table class="table"><thead><tr><th>Título</th><th>Artista</th><th>Álbum</th><th>Acciones</th></tr></thead><tbody>
          @for (song of songs(); track song.id) { <tr><td>{{ song.title }}</td><td>{{ song.artist }}</td><td>{{ song.album || '—' }}</td><td class="table-actions"><button class="btn btn-secondary" [class.btn-added]="song.playlists_count" (click)="openAddToPlaylist(song)">{{ song.playlists_count ? 'Añadida' : 'Añadir a lista' }}</button><button class="btn btn-icon btn-secondary" (click)="edit(song)">Editar</button><button class="btn btn-danger btn-icon" (click)="remove(song)">Eliminar</button></td></tr> }
        </tbody></table></div>
      }
      <div class="pagination"><button class="btn btn-secondary" [disabled]="page() <= 1 || loading()" (click)="load(page()-1)">Anterior</button><span>Página {{ page() }} de {{ totalPages() || 1 }}</span><button class="btn btn-secondary" [disabled]="!totalPages() || page() >= totalPages() || loading()" (click)="load(page()+1)">Siguiente</button></div>
      @if (editing()) { <div class="modal-overlay"><form class="modal-content" (ngSubmit)="save()"><div class="modal-header"><h3>{{ editing()!.id ? 'Editar canción' : 'Nueva canción' }}</h3><button type="button" class="close-btn" (click)="editing.set(null)">×</button></div><div class="modal-body"><div class="form-group"><label>Título</label><input name="title" [(ngModel)]="editing()!.title" required /></div><div class="form-group"><label>Artista</label><input name="artist" [(ngModel)]="editing()!.artist" required /></div><div class="form-group"><label>Álbum</label><input name="album" [(ngModel)]="editing()!.album" /></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" (click)="editing.set(null)">Cancelar</button><button class="btn btn-primary">Guardar</button></div></form></div> }
      @if (songToAdd(); as song) { <app-add-to-playlist-modal [song]="song" (cancel)="songToAdd.set(null)" (saved)="handlePlaylistSaved()" /> }
    </section>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class SongCatalogComponent {
  private readonly api = inject(SongApiService); private readonly destroyRef = inject(DestroyRef);
  readonly errors = inject(ApiErrorService); readonly songs = signal<Song[]>([]); readonly loading = signal(false); readonly query = signal(''); readonly page = signal(1); readonly limit = signal(25); readonly totalPages = signal(0); readonly editing = signal<Partial<Song> | null>(null); readonly songToAdd = signal<Song | null>(null);
  readonly search$ = new Subject<string>(); readonly importOpen = signal(false); readonly importLoading = signal(false); readonly importRows = signal<BulkSongImportRow[]>([]); readonly importErrors = signal<BulkSongImportError[]>([]); readonly importResult = signal<number | null>(null);
  constructor() { this.search$.pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed(this.destroyRef)).subscribe(q => { this.query.set(q); this.load(1); }); this.load(1); }
  load(page: number): void { const requestedPage = Math.max(1, Math.trunc(page)); this.page.set(requestedPage); this.loading.set(true); this.api.getSongs({ search: this.query(), page: requestedPage, limit: this.limit() }).pipe(finalize(() => this.loading.set(false))).subscribe({ next: r => { this.songs.set(r.data || []); this.page.set(r.meta?.page || requestedPage); this.limit.set(r.meta?.limit || this.limit()); this.totalPages.set(r.meta?.totalPages || 0); }, error: error => this.errors.show(error.message || 'No se pudo cargar el catálogo.') }); }
  setLimit(value: number): void { this.limit.set(+value); this.load(1); }
  openForm(): void { this.editing.set({ title: '', artist: '', album: null }); }
  edit(song: Song): void { this.editing.set({ ...song }); }
  openAddToPlaylist(song: Song): void { this.songToAdd.set(song); }
  handlePlaylistSaved(): void { this.songToAdd.set(null); }
  remove(song: Song): void {
    if (!confirm(`¿Eliminar "${song.title}"?`)) return;
    this.api.remove(song.id).subscribe({
      next: () => this.load(this.page()),
      error: error => this.errors.show(error.message || 'No se pudo eliminar la canción.')
    });
  }
  save(): void { const value = this.editing(); if (!value?.title?.trim() || !value.artist?.trim()) return; const dto = { title: value.title.trim(), artist: value.artist.trim(), album: value.album || undefined }; const request = value.id ? this.api.update(value.id, dto) : this.api.create(dto); request.subscribe(() => { this.editing.set(null); this.load(this.page()); }); }
  openImport(): void { this.importOpen.set(true); this.importRows.set([]); this.importErrors.set([]); this.importResult.set(null); }
  closeImport(): void { if (!this.importLoading()) this.importOpen.set(false); }
  downloadTemplate(): void {
    const worksheet = XLSX.utils.aoa_to_sheet([['title', 'artist', 'album', 'genre', 'duration_seconds']]);
    const workbook = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(workbook, worksheet, 'songs');
    XLSX.writeFile(workbook, 'plantilla-canciones.xlsx');
  }
  inspectImportFile(event: Event): void {
    const input = event.target as HTMLInputElement; const file = input.files?.[0];
    this.importRows.set([]); this.importErrors.set([]); this.importResult.set(null);
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { this.importErrors.set([{ row: 0, field: 'archivo', message: 'El archivo no puede superar 5 MB.' }]); return; }
    if (!/\.(xlsx|xls)$/i.test(file.name)) { this.importErrors.set([{ row: 0, field: 'archivo', message: 'Solo se aceptan archivos .xlsx o .xls.' }]); return; }
    this.importLoading.set(true);
    file.arrayBuffer().then(buffer => {
      try {
        const workbook = XLSX.read(buffer, { type: 'array', cellDates: false, bookVBA: false });
        if (!workbook.SheetNames.includes('songs')) { this.importErrors.set([{ row: 0, field: 'hoja', message: 'El archivo debe contener una hoja llamada songs.' }]); return; }
        const sheet = workbook.Sheets['songs']; const matrix = XLSX.utils.sheet_to_json<unknown[]>(sheet, { header: 1, defval: null, raw: true });
        const headers = (matrix[0] || []).map(value => String(value ?? '').trim());
        const expected = ['title', 'artist', 'album', 'genre', 'duration_seconds'];
        const errors: BulkSongImportError[] = [];
        if (headers.length !== expected.length || headers.some((header, index) => header !== expected[index])) {
          errors.push({ row: 1, field: 'encabezados', message: `Deben ser exactamente: ${expected.join(', ')}.` });
        }
        const rows = matrix.slice(1); if (rows.length > 2500) errors.push({ row: 0, field: 'filas', message: 'El máximo permitido es 2.500 registros.' });
        const parsed: BulkSongImportRow[] = [];
        rows.forEach((values, index) => {
          const rowNumber = index + 2; const cells = values as unknown[]; const title = String(cells[0] ?? '').trim(); const artist = String(cells[1] ?? '').trim(); const album = String(cells[2] ?? '').trim(); const genre = String(cells[3] ?? '').trim(); const rawDuration = cells[4];
          if (cells.every(value => value === null || String(value).trim() === '')) { errors.push({ row: rowNumber, field: 'fila', message: 'La fila no puede estar vacía.' }); return; }
          if (!title) errors.push({ row: rowNumber, field: 'title', message: 'El título es obligatorio.' });
          if (!artist) errors.push({ row: rowNumber, field: 'artist', message: 'El artista es obligatorio.' });
          const duration = rawDuration === null || rawDuration === '' ? 0 : Number(rawDuration);
          if (!Number.isInteger(duration) || duration < 0 || duration > 86399) errors.push({ row: rowNumber, field: 'duration_seconds', message: 'Debe ser un entero entre 0 y 86.399.' });
          parsed.push({ title, artist, album: album || undefined, genre: genre || undefined, duration_seconds: duration });
        });
        this.importRows.set(parsed); this.importErrors.set(errors);
      } catch { this.importErrors.set([{ row: 0, field: 'archivo', message: 'No se pudo leer el archivo como una hoja de cálculo válida.' }]); }
      finally { this.importLoading.set(false); }
    }).catch(() => { this.importLoading.set(false); this.importErrors.set([{ row: 0, field: 'archivo', message: 'No se pudo leer el archivo seleccionado.' }]); });
  }
  confirmImport(): void {
    const rows = this.importRows(); if (!rows.length || this.importErrors().length) return;
    this.importLoading.set(true); this.api.bulkImport(rows).pipe(finalize(() => this.importLoading.set(false))).subscribe({
      next: response => { const count = Number(response.data?.inserted_count ?? rows.length); this.importResult.set(count); this.importRows.set([]); this.load(1); },
      error: error => this.importErrors.set((error.error?.errors || []).map((item: { field?: string; message?: string }, index: number) => ({ row: 0, field: item.field || `fila ${index + 1}`, message: item.message || error.message || 'Importación rechazada.' })))
    });
  }
  downloadImportErrors(): void {
    const worksheet = XLSX.utils.json_to_sheet(this.importErrors().map(error => ({ fila: error.row, campo: error.field, motivo: error.message })));
    const workbook = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(workbook, worksheet, 'errores'); XLSX.writeFile(workbook, 'errores-importacion.xlsx');
  }
}
