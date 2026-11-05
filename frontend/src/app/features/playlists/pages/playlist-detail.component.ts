import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { debounceTime, distinctUntilChanged, finalize, Subject, switchMap, takeUntil } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { PlaylistDetail, PlaylistSong } from '../../../core/models/playlist.model';
import { Song } from '../../../core/models/song.model';
import { PlaylistApiService } from '../../../core/services/playlist-api.service';
import { SongApiService } from '../../../core/services/song-api.service';

@Component({
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <section class="page">
      @if (loading()) {
        <p class="state">Cargando playlist…</p>
      }
      @if (item(); as playlist) {
        <a routerLink="/playlists" class="back">← Playlists</a>
        <div class="page-heading">
          <div><p class="eyebrow">PLAYLIST</p><h1>{{ playlist.name }}</h1><p>{{ playlist.description || 'Sin descripción' }}</p></div>
          <button class="btn btn-primary" (click)="openAddSong()">Añadir canción</button>
        </div>
        @if (error()) { <div class="alert error">{{ error() }}</div> }
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>#</th><th>Título</th><th>Artista</th><th>Álbum</th><th></th></tr></thead>
            <tbody>
              @for (song of songs(); track song.song_id; let i = $index) {
                <tr>
                  <td>{{ i + 1 }}</td><td>{{ song.title }}</td><td>{{ song.artist }}</td><td>{{ song.album || '—' }}</td>
                  <td>
                    <button class="btn btn-secondary btn-icon" [disabled]="i === 0" (click)="move(song.song_id, i)">↑</button>
                    <button class="btn btn-secondary btn-icon" [disabled]="i === songs().length - 1" (click)="move(song.song_id, i + 2)">↓</button>
                    <button class="btn btn-danger btn-icon" (click)="remove(song.song_id)">Quitar</button>
                  </td>
                </tr>
              } @empty { <tr><td colspan="5">Esta playlist está vacía.</td></tr> }
            </tbody>
          </table>
        </div>
      }
      @if (addDialog()) {
        <div class="modal-overlay">
          <div class="modal-content">
            <div class="modal-header"><h3>Añadir canción a la playlist</h3><button class="close-btn" (click)="closeAddSong()">×</button></div>
            <div class="modal-body">
              <div class="form-group"><label for="song-search">Buscar por título o artista</label><input id="song-search" [ngModel]="search()" (ngModelChange)="searchInput.next($event)" placeholder="Ej. California Love" /></div>
              @if (searching()) { <p class="state">Buscando…</p> }
              @if (selectedSong(); as selected) { <div class="selected-item"><strong>{{ selected.title }}</strong><span>{{ selected.artist }}</span><button class="btn btn-secondary" (click)="selectedSong.set(null)">Cambiar</button></div> }
              @if (!selectedSong()) {
                <div class="song-results">
                  @for (song of searchResults(); track song.id) {
                    <button class="song-result" (click)="selectSong(song)"><strong>{{ song.title }}</strong><span>{{ song.artist }} · {{ song.album || 'Sin álbum' }}</span></button>
                  } @empty { <p class="state">Escribe para buscar canciones del catálogo.</p> }
                </div>
              }
              @if (error()) { <div class="alert error">{{ error() }}</div> }
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" (click)="closeAddSong()">Cancelar</button><button class="btn btn-primary" [disabled]="!selectedSong() || saving()" (click)="add()">Añadir</button></div>
          </div>
        </div>
      }
    </section>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class PlaylistDetailComponent {
  private readonly api = inject(PlaylistApiService);
  private readonly songsApi = inject(SongApiService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);
  readonly item = signal<PlaylistDetail | null>(null);
  readonly songs = signal<PlaylistSong[]>([]);
  readonly loading = signal(true);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly addDialog = signal(false);
  readonly search = signal('');
  readonly searchResults = signal<Song[]>([]);
  readonly selectedSong = signal<Song | null>(null);
  readonly searching = signal(false);
  readonly searchInput = new Subject<string>();
  private readonly playlistId = Number(this.route.snapshot.paramMap.get('id'));

  constructor() {
    this.searchInput.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      takeUntilDestroyed(this.destroyRef),
      switchMap(query => {
        this.search.set(query);
        if (!query.trim()) {
          this.searchResults.set([]);
          return [];
        }
        this.searching.set(true);
        return this.songsApi.getSongs({ search: query, page: 1, limit: 25 }).pipe(finalize(() => this.searching.set(false)));
      })
    ).subscribe({
      next: response => this.searchResults.set(response.data || []),
      error: error => this.error.set(error.message || 'No se pudo buscar en el catálogo.')
    });
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set(null);
    this.api.getById(this.playlistId).pipe(
      switchMap(response => {
        this.item.set(response.data);
        return this.api.getSongs(this.playlistId);
      }),
      finalize(() => this.loading.set(false))
    ).subscribe({
      next: response => this.songs.set(response.data || []),
      error: error => this.error.set(error.message || 'No se pudo cargar la playlist.')
    });
  }

  openAddSong(): void {
    this.error.set(null);
    this.addDialog.set(true);
    this.search.set('');
    this.searchResults.set([]);
    this.selectedSong.set(null);
  }

  closeAddSong(): void { this.addDialog.set(false); }
  selectSong(song: Song): void { this.selectedSong.set(song); }

  add(): void {
    const song = this.selectedSong();
    if (!song) return;
    this.saving.set(true);
    this.api.addSong(this.playlistId, song.id).pipe(finalize(() => this.saving.set(false))).subscribe({
      next: () => { this.closeAddSong(); this.load(); },
      error: error => this.error.set(error.message || 'No se pudo añadir la canción.')
    });
  }

  remove(songId: number): void {
    this.api.removeSong(this.playlistId, songId).subscribe({
      next: () => this.load(),
      error: error => this.error.set(error.message || 'No se pudo quitar la canción.')
    });
  }

  move(songId: number, position: number): void {
    this.api.reorderSong(this.playlistId, songId, position).subscribe({
      next: () => this.load(),
      error: error => this.error.set(error.message || 'No se pudo reordenar la canción.')
    });
  }
}
