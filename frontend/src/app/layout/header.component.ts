import { Component, input, output } from '@angular/core';

@Component({
  selector: 'app-header',
  standalone: true,
  templateUrl: './header.component.html',
})
export class HeaderComponent {
  readonly username = input('Usuário');
  readonly menuLabel = input('Recolher menu');
  readonly toggleMenu = output<void>();
  readonly logout = output<void>();
}
