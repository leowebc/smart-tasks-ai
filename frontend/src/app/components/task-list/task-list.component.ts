import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { Actions, ofType } from '@ngrx/effects';
import { Store } from '@ngrx/store';
import { DialogService } from '../../services/dialog.service';
import * as TaskActions from '../../state/tasks/task.actions';
import { TaskItem } from '../../state/tasks/task.model';
import {
  selectTaskError,
  selectTaskFormError,
  selectTasks,
  selectTasksLoading,
  selectTasksSaving,
} from '../../state/tasks/task.selectors';

@Component({
  selector: 'app-task-list',
  standalone: true,
  imports: [ReactiveFormsModule, MatButtonModule],
  templateUrl: './task-list.component.html',
  styleUrl: './task-list.component.scss',
})
export class TaskListComponent {
  private readonly store = inject(Store);
  private readonly actions$ = inject(Actions);
  private readonly dialogs = inject(DialogService);
  private readonly formBuilder = inject(FormBuilder);

  readonly form = this.formBuilder.nonNullable.group({
    title: ['', [Validators.required, Validators.maxLength(255)]],
    description: [''],
  });

  readonly tasks = this.store.selectSignal(selectTasks);
  readonly loading = this.store.selectSignal(selectTasksLoading);
  readonly saving = this.store.selectSignal(selectTasksSaving);
  readonly errorMessage = this.store.selectSignal(selectTaskError);
  readonly formError = this.store.selectSignal(selectTaskFormError);
  readonly editingId = signal<number | null>(null);

  constructor() {
    const destroyRef = inject(DestroyRef);
    this.store.dispatch(TaskActions.loadTasks());
    this.actions$.pipe(ofType(TaskActions.saveTaskSuccess), takeUntilDestroyed(destroyRef)).subscribe(() => {
      this.cancelEdit();
    });
    this.actions$.pipe(ofType(TaskActions.deleteTaskSuccess), takeUntilDestroyed(destroyRef)).subscribe(({ id }) => {
      if (this.editingId() === id) {
        this.cancelEdit();
      }
    });
    this.actions$.pipe(ofType(TaskActions.deleteTaskFailure), takeUntilDestroyed(destroyRef)).subscribe(({ error }) => {
      void this.dialogs.error(error);
    });
  }

  edit(task: TaskItem): void {
    this.editingId.set(task.id);
    this.store.dispatch(TaskActions.clearTaskFeedback());
    this.form.setValue({
      title: task.title,
      description: task.description ?? '',
    });
  }

  cancelEdit(): void {
    this.editingId.set(null);
    this.store.dispatch(TaskActions.clearTaskFeedback());
    this.form.reset({ title: '', description: '' });
  }

  save(): void {
    if (this.saving()) {
      return;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const title = this.form.controls.title.value.trim();
    const description = this.form.controls.description.value.trim();
    const payloadDescription = description === '' ? null : description;
    const editingId = this.editingId();
    this.store.dispatch(editingId === null
      ? TaskActions.createTask({ title, description: payloadDescription })
      : TaskActions.updateTask({ id: editingId, title, description: payloadDescription }));
  }

  async remove(task: TaskItem): Promise<void> {
    if (!await this.dialogs.confirm(`Excluir a tarefa "${task.title}"?`)) {
      return;
    }
    this.store.dispatch(TaskActions.deleteTask({ id: task.id }));
  }

  formatDate(value: string | null): string {
    if (!value) {
      return '—';
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('pt-BR');
  }
}
