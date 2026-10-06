import { Component, inject, signal } from '@angular/core';
import { NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { AuthService } from '../services/auth.service';
import { HeaderComponent } from './header.component';
import { SidebarComponent } from './sidebar.component';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [RouterOutlet, SidebarComponent, HeaderComponent],
  templateUrl: './app-layout.component.html',
})
export class AppLayoutComponent {
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);

  readonly collapsed = signal(false);
  readonly mobileOpen = signal(false);
  readonly username = signal(this.authService.currentUsername());

  constructor() {
    this.router.events.pipe(
      filter((event): event is NavigationEnd => event instanceof NavigationEnd),
    ).subscribe(() => this.mobileOpen.set(false));
  }

  toggleMenu(): void {
    if (window.matchMedia('(max-width: 800px)').matches) {
      this.mobileOpen.update((open) => !open);
      return;
    }
    this.collapsed.update((collapsed) => !collapsed);
  }

  menuLabel(): string {
    if (window.matchMedia('(max-width: 800px)').matches) {
      return this.mobileOpen() ? 'Fechar menu' : 'Abrir menu';
    }
    return this.collapsed() ? 'Expandir menu' : 'Recolher menu';
  }

  logout(): void {
    this.authService.logout();
  }

  closeMobile(): void {
    this.mobileOpen.set(false);
  }
}
