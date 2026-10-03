import { Component, inject } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { map } from 'rxjs';
import { toSignal } from '@angular/core/rxjs-interop';

@Component({
  selector: 'app-unavailable',
  standalone: true,
  templateUrl: './unavailable.component.html',
})
export class UnavailableComponent {
  private readonly route = inject(ActivatedRoute);

  readonly title = toSignal(this.route.data.pipe(map((data) => String(data['title'] ?? 'Módulo'))), {
    initialValue: 'Módulo',
  });
}
