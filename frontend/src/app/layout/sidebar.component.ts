import { Component, input } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from '../services/auth.service';

interface MenuItem {
  label: string;
  link: string;
  available: boolean;
  icon: 'home' | 'tasks' | 'upload' | 'web' | 'chat' | 'bases' | 'settings';
}

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [RouterLink, RouterLinkActive],
  templateUrl: './sidebar.component.html',
})
export class SidebarComponent {
  readonly collapsed = input(false);

  readonly items: MenuItem[] = [
    { label: 'Dashboard', link: '/dashboard', available: true, icon: 'home' },
    { label: 'Minhas Tarefas', link: '/tasks', available: true, icon: 'tasks' },
    { label: 'Upload', link: '/upload', available: true, icon: 'upload' },
    { label: 'Web Scraping', link: '/scraping', available: true, icon: 'web' },
    { label: 'Chat com IA', link: '/chat', available: true, icon: 'chat' },
    { label: 'Bases', link: '/bases', available: false, icon: 'bases' },
    { label: 'Configurações', link: '/settings', available: false, icon: 'settings' },
  ];

  constructor(private readonly authService: AuthService) {}

  logout(): void {
    this.authService.logout();
  }
}
