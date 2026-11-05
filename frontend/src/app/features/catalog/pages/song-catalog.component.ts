import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { debounceTime, distinctUntilChanged, Subject, finalize } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Song } from '../../../core/models/song.model';
import { SongApiService } from '../../../core/services/song-api.service';
import { ApiErrorService } from '../../../core/services/api-error.service';
import { AddToPlaylistModalComponent } from '../components/add-to-playlist-modal.component';

@Component({
  standalone: true,
  imports: [FormsModule, AddToPlaylistModalComponent],
  template: `
    <section class="page">
      <div class="page-heading"><div><p class="eyebrow">CATÁLOGO MAESTRO</p><h1>Canciones</h1></div><button class="btn btn-primary" (click)="openForm()">Nueva canción</button></div>
      <div class="toolbar"><input aria-label="Buscar canciones" [ngModel]="query()" (ngModelChange)="search$.next($event)" placeholder="Buscar por título, artista o álbum..." /><select aria-label="Canciones por página" [ngModel]="limit()" (ngModelChange)="setLimit($event)"><option [ngValue]="5">5</option><option [ngValue]="10">10</option><option [ngValue]="15">15</option><option [ngValue]="25">25</option><option [ngValue]="50">50</option><option [ngValue]="100">100</option></select></div>
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
  readonly search$ = new Subject<string>();
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
}
