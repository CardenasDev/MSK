import { ChangeDetectionStrategy, Component, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { finalize } from 'rxjs';
import { Playlist } from '../../../core/models/playlist.model';
import { Song } from '../../../core/models/song.model';
import { PlaylistApiService } from '../../../core/services/playlist-api.service';

@Component({
  selector: 'app-add-to-playlist-modal',
  standalone: true,
  imports: [FormsModule],
  template: `
    <div class="modal-overlay" (click)="cancel.emit()">
      <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="add-playlist-title" (click)="$event.stopPropagation()">
        <div class="modal-header">
          <h3 id="add-playlist-title">Añadir a lista</h3>
          <button type="button" class="close-btn" (click)="cancel.emit()">×</button>
        </div>
        <div class="modal-body">
          <p class="modal-description"><strong>{{ song().title }}</strong> · {{ song().artist }}</p>
          @if (loading()) {
            <p class="state">Cargando playlists…</p>
          } @else if (error()) {
            <div class="alert error">{{ error() }}</div>
          } @else if (!playlists().length) {
            <p class="state">No hay playlists disponibles.</p>
          } @else {
            <div class="playlist-options">
              @for (playlist of playlists(); track playlist.id) {
                <label class="playlist-option">
                  <input type="checkbox" [checked]="selected().has(playlist.id)" (change)="toggle(playlist.id)" />
                  <span><strong>{{ playlist.name }}</strong><small>{{ playlist.songs_count || 0 }} canciones</small></span>
                </label>
              }
            </div>
          }
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" (click)="cancel.emit()">Cancelar</button>
          <button type="button" class="btn btn-primary" [disabled]="loading() || saving() || !selected().size" (click)="save()">
            {{ saving() ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AddToPlaylistModalComponent {
  readonly song = input.required<Song>();
  readonly cancel = output<void>();
  readonly saved = output<void>();
  private readonly playlistsApi = inject(PlaylistApiService);
  readonly playlists = signal<Playlist[]>([]);
  readonly selected = signal<Set<number>>(new Set());
  readonly loading = signal(true);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  constructor() {
    this.playlistsApi.listAll().pipe(finalize(() => this.loading.set(false))).subscribe({
      next: response => {
        const playlists = [...(response.data || [])].sort((a, b) =>
          a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })
        );
        this.playlists.set(playlists);
      },
      error: error => this.error.set(error.message || 'No se pudieron cargar las playlists.')
    });
  }

  toggle(playlistId: number): void {
    this.selected.update(current => {
      const next = new Set(current);
      if (next.has(playlistId)) next.delete(playlistId);
      else next.add(playlistId);
      return next;
    });
  }

  save(): void {
    const playlistIds = [...this.selected()];
    if (!playlistIds.length) return;
    this.saving.set(true);
    this.error.set(null);
    this.playlistsApi.addSongToPlaylists(this.song().id, playlistIds)
      .pipe(finalize(() => this.saving.set(false)))
      .subscribe({
        next: () => this.saved.emit(),
        error: error => this.error.set(error.message || 'No se pudo añadir la canción a las playlists.')
      });
  }
}
