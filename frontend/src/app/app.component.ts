import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { AppShellComponent } from './shell/app-shell.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, AppShellComponent],
  template: '<app-shell><router-outlet /></app-shell>',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppComponent {}
