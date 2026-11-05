import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Playlist } from '../../../core/models/playlist.model';
import { PlaylistApiService } from '../../../core/services/playlist-api.service';

@Component({ standalone: true, imports: [FormsModule, RouterLink], template: `
<section class="page"><div class="page-heading"><div><p class="eyebrow">ORGANIZACIÓN</p><h1>Playlists</h1></div><button class="btn btn-primary" (click)="create()">Nueva lista</button></div>
@if (error()) {<div class="alert error">{{error()}}</div>}<div class="card-grid">@for (item of items(); track item.id) {<article class="card"><h2>{{item.name}}</h2><p>{{item.description || 'Sin descripción'}}</p><span class="badge badge-primary">{{item.songs_count || 0}} canciones</span><div class="card-actions"><a class="btn btn-secondary" [routerLink]="['/playlists', item.id]">Abrir</a><button class="btn btn-danger" (click)="remove(item)">Eliminar</button></div></article>} @empty {<p class="state">Aún no hay playlists.</p>}</div>
@if (form()) {<div class="modal-overlay"><form class="modal-content" (ngSubmit)="save()"><div class="modal-header"><h3>Nueva playlist</h3></div><div class="modal-body"><div class="form-group"><label>Nombre</label><input name="name" [(ngModel)]="form()!.name" required maxlength="150" /></div><div class="form-group"><label>Descripción</label><textarea name="description" [(ngModel)]="form()!.description"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" (click)="form.set(null)">Cancelar</button><button class="btn btn-primary">Crear</button></div></form></div>}</section>`, changeDetection: ChangeDetectionStrategy.OnPush })
export class PlaylistListComponent {
  private readonly api=inject(PlaylistApiService); readonly items=signal<Playlist[]>([]); readonly error=signal<string|null>(null); readonly form=signal<{name:string;description:string}|null>(null);
  constructor(){this.load()} load(){this.api.list().subscribe({next:r=>this.items.set(r.data||[]),error:e=>this.error.set(e.message||'No se pudieron cargar las playlists.')})}
  create(){this.form.set({name:'',description:''})} save(){const f=this.form();if(!f?.name.trim())return;this.api.create(f).subscribe(()=>{this.form.set(null);this.load()})} remove(item:Playlist){if(confirm(`¿Eliminar "${item.name}"?`))this.api.remove(item.id).subscribe(()=>this.load())}
}
