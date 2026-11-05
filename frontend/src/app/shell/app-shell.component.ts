import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [RouterLink, RouterLinkActive],
  template: `
    <header class="app-header">
      <a routerLink="/catalog" class="brand">♫ <span>MSK</span></a>
      <nav aria-label="Navegación principal">
        <a routerLink="/catalog" routerLinkActive="active">Catálogo</a>
        <a routerLink="/playlists" routerLinkActive="active">Playlists</a>
        <a routerLink="/groups" routerLinkActive="active">Grupos</a>
      </nav>
    </header>
    <main class="container"><ng-content /></main>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppShellComponent {}
