import { ChangeDetectionStrategy, Component, inject, input, output, signal } from '@angular/core';
import { finalize } from 'rxjs';
import { Group } from '../../../core/models/group.model';
import { Playlist } from '../../../core/models/playlist.model';
import { GroupApiService } from '../../../core/services/group-api.service';

@Component({
  selector: 'app-add-to-group-modal',
  standalone: true,
  template: `
    <div class="modal-overlay" (click)="cancel.emit()">
      <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="add-group-title" (click)="$event.stopPropagation()">
        <div class="modal-header">
          <h3 id="add-group-title">Añadir a grupos</h3>
          <button type="button" class="close-btn" (click)="cancel.emit()">×</button>
        </div>
        <div class="modal-body">
          <p class="modal-description"><strong>{{ playlist().name }}</strong></p>
          @if (loading()) {
            <p class="state">Cargando grupos…</p>
          } @else if (error()) {
            <div class="alert error">{{ error() }}</div>
          } @else if (!groups().length) {
            <p class="state">No hay grupos disponibles.</p>
          } @else {
            <div class="playlist-options">
              @for (group of groups(); track group.id) {
                <label class="playlist-option">
                  <input type="checkbox" [checked]="selected().has(group.id)" (change)="toggle(group.id)" />
                  <span><strong>{{ group.name }}</strong><small>{{ group.playlists_count || 0 }} playlists</small></span>
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
export class AddToGroupModalComponent {
  readonly playlist = input.required<Playlist>();
  readonly cancel = output<void>();
  readonly saved = output<void>();
  private readonly groupsApi = inject(GroupApiService);
  readonly groups = signal<Group[]>([]);
  readonly selected = signal<Set<number>>(new Set());
  readonly loading = signal(true);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  constructor() {
    this.groupsApi.list().pipe(finalize(() => this.loading.set(false))).subscribe({
      next: response => {
        const groups = [...(response.data || [])].sort((a, b) =>
          a.name.localeCompare(b.name, 'es', { sensitivity: 'base' })
        );
        this.groups.set(groups);
      },
      error: error => this.error.set(error.message || 'No se pudieron cargar los grupos.')
    });
  }

  toggle(groupId: number): void {
    this.selected.update(current => {
      const next = new Set(current);
      if (next.has(groupId)) next.delete(groupId);
      else next.add(groupId);
      return next;
    });
  }

  save(): void {
    const groupIds = [...this.selected()];
    if (!groupIds.length) return;
    this.saving.set(true);
    this.error.set(null);
    this.groupsApi.addPlaylistToGroups(this.playlist().id, groupIds)
      .pipe(finalize(() => this.saving.set(false)))
      .subscribe({
        next: () => this.saved.emit(),
        error: error => this.error.set(error.message || 'No se pudo añadir la playlist a los grupos.')
      });
  }
}
