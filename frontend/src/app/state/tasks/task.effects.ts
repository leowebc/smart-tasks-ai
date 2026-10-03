import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, exhaustMap, map, of, switchMap } from 'rxjs';
import { TaskService } from '../../services/task.service';
import { TaskItem } from './task.model';
import * as TaskActions from './task.actions';

@Injectable()
export class TaskEffects {
  private readonly actions$ = inject(Actions);
  private readonly taskService = inject(TaskService);

  loadTasks$ = createEffect(() => this.actions$.pipe(
    ofType(TaskActions.loadTasks),
    switchMap(() => this.taskService.getTasks().pipe(
      map((tasks) => TaskActions.loadTasksSuccess({
        tasks: Array.isArray(tasks) ? tasks as TaskItem[] : [],
      })),
      catchError((error: HttpErrorResponse) => of(TaskActions.loadTasksFailure({
        error: error.status === 0
          ? 'Não foi possível conectar ao servidor.'
          : 'Não foi possível carregar as tarefas.',
      }))),
    )),
  ));

  createTask$ = createEffect(() => this.actions$.pipe(
    ofType(TaskActions.createTask),
    exhaustMap(({ title, description }) => this.taskService.addTask(title, description).pipe(
      map(() => TaskActions.saveTaskSuccess()),
      catchError((error: HttpErrorResponse) => of(TaskActions.saveTaskFailure({
        error: this.saveError(error),
      }))),
    )),
  ));

  updateTask$ = createEffect(() => this.actions$.pipe(
    ofType(TaskActions.updateTask),
    exhaustMap(({ id, title, description }) => this.taskService.updateTask(id, title, description).pipe(
      map(() => TaskActions.saveTaskSuccess()),
      catchError((error: HttpErrorResponse) => of(TaskActions.saveTaskFailure({
        error: this.saveError(error),
      }))),
    )),
  ));

  deleteTask$ = createEffect(() => this.actions$.pipe(
    ofType(TaskActions.deleteTask),
    exhaustMap(({ id }) => this.taskService.deleteTask(id).pipe(
      map(() => TaskActions.deleteTaskSuccess({ id })),
      catchError(() => of(TaskActions.deleteTaskFailure({
        error: 'Não foi possível excluir a tarefa.',
      }))),
    )),
  ));

  reloadAfterChange$ = createEffect(() => this.actions$.pipe(
    ofType(TaskActions.saveTaskSuccess, TaskActions.deleteTaskSuccess),
    map(() => TaskActions.loadTasks()),
  ));

  private saveError(error: HttpErrorResponse): string {
    const message = error.error?.error;
    return typeof message === 'string' && message.length > 0
      ? message
      : 'Não foi possível salvar a tarefa.';
  }
}
