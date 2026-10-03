import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-quick-actions',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './quick-actions.component.html',
})
export class QuickActionsComponent {
  readonly actions = [
    { label: 'Nova tarefa', link: '/tasks', available: true },
    { label: 'Upload', link: '/upload', available: true },
    { label: 'Importar URL', link: '/scraping', available: true },
    { label: 'Chat com IA', link: '/chat', available: true },
    { label: 'Minhas bases', link: '/bases', available: false },
  ];
}
