import { Component, computed, effect, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { QuickActionsComponent } from '../../layout/quick-actions.component';
import { StatCardComponent } from '../../layout/stat-card.component';
import { DocumentService } from '../../services/document.service';
import * as TaskActions from '../../state/tasks/task.actions';
import { TASK_STATUS_LABELS } from '../../state/tasks/task.model';
import {
  selectTaskError,
  selectTasks,
  selectTasksLoaded,
  selectTasksLoading,
} from '../../state/tasks/task.selectors';

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
  private documentsRequested = false;

  readonly statusLabels = TASK_STATUS_LABELS;
  readonly loading = this.store.selectSignal(selectTasksLoading);
  readonly errorMessage = this.store.selectSignal(selectTaskError);
  readonly total = computed(() => this.taskList().length);
  readonly pending = computed(() => this.countByStatus('pending'));
  readonly inProgress = computed(() => this.countByStatus('in_progress'));
  readonly completed = computed(() => this.countByStatus('completed'));
  readonly recent = computed(() => this.taskList().slice(-5).reverse());
  readonly documentTotal = signal<number | null>(null);
  readonly documentHint = signal('Contagem da sua conta.');

  constructor() {
    this.store.dispatch(TaskActions.loadTasks());
    effect(() => {
      if (!this.tasksLoaded() || this.documentsRequested) {
        return;
      }
      this.documentsRequested = true;
      this.loadDocuments();
    });
  }

  private countByStatus(status: 'pending' | 'in_progress' | 'completed'): number {
    return this.taskList().filter((task) => task.status === status).length;
  }

  private loadDocuments(): void {
    this.documentService.list().subscribe({
      next: (documents) => this.documentTotal.set(Array.isArray(documents) ? documents.length : 0),
      error: () => this.documentHint.set('Não foi possível carregar os documentos.'),
    });
  }
}
