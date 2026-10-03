import { Component, effect, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { QuickActionsComponent } from '../../layout/quick-actions.component';
import { StatCardComponent } from '../../layout/stat-card.component';
import { DocumentService } from '../../services/document.service';
import * as TaskActions from '../../state/tasks/task.actions';
import { TaskItem } from '../../state/tasks/task.model';
import { selectTaskError, selectTasks, selectTasksLoaded } from '../../state/tasks/task.selectors';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [RouterLink, StatCardComponent, QuickActionsComponent],
  templateUrl: './dashboard.component.html',
})
export class DashboardComponent {
  private readonly store = inject(Store);
  private readonly documentService = inject(DocumentService);
  private readonly taskList = this.store.selectSignal(selectTasks);
  private readonly tasksLoaded = this.store.selectSignal(selectTasksLoaded);
  private readonly taskError = this.store.selectSignal(selectTaskError);
  private documentsRequested = false;

  readonly loading = signal(true);
  readonly errorMessage = signal('');
  readonly total = signal<number | null>(null);
  readonly recent = signal<TaskItem[]>([]);
  readonly documentTotal = signal<number | null>(null);
  readonly documentHint = signal('Contagem da sua conta.');

  constructor() {
    this.store.dispatch(TaskActions.loadTasks());
    effect(() => {
      if (!this.tasksLoaded() || this.documentsRequested) {
        return;
      }
      this.documentsRequested = true;
      const items = this.taskList();
      this.total.set(items.length);
      this.recent.set(items.slice(-5).reverse());
      this.loading.set(false);
      this.errorMessage.set(this.taskError());
      this.loadDocuments();
    });
  }

  private loadDocuments(): void {
    this.documentService.list().subscribe({
      next: (documents) => this.documentTotal.set(Array.isArray(documents) ? documents.length : 0),
      error: () => this.documentHint.set('Não foi possível carregar os documentos.'),
    });
  }
}
