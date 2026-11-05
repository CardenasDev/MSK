import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'catalog' },
  { path: 'catalog', title: 'Catálogo Maestro | MSK', loadComponent: () => import('./features/catalog/pages/song-catalog.component').then(m => m.SongCatalogComponent) },
  { path: 'playlists', children: [
    { path: '', title: 'Mis Listas | MSK', loadComponent: () => import('./features/playlists/pages/playlist-list.component').then(m => m.PlaylistListComponent) },
    { path: ':id', title: 'Detalle de lista | MSK', loadComponent: () => import('./features/playlists/pages/playlist-detail.component').then(m => m.PlaylistDetailComponent) }
  ]},
  { path: 'groups', children: [
    { path: '', title: 'Grupos | MSK', loadComponent: () => import('./features/groups/pages/group-list.component').then(m => m.GroupListComponent) },
    { path: ':id', title: 'Detalle de grupo | MSK', loadComponent: () => import('./features/groups/pages/group-detail.component').then(m => m.GroupDetailComponent) }
  ]},
  { path: '**', redirectTo: 'catalog' }
];
