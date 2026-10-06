import { createAction, props } from '@ngrx/store';
import { TaskItem, TaskStatus } from './task.model';

export const loadTasks = createAction('[Tasks] Load');
export const loadTasksSuccess = createAction('[Tasks] Load Success', props<{ tasks: TaskItem[] }>());
export const loadTasksFailure = createAction('[Tasks] Load Failure', props<{ error: string }>());

export const createTask = createAction(
  '[Tasks] Create',
  props<{ title: string; description: string | null; status: TaskStatus }>(),
);
export const updateTask = createAction(
  '[Tasks] Update',
  props<{ id: number; title: string; description: string | null; status: TaskStatus }>(),
);
export const saveTaskSuccess = createAction('[Tasks] Save Success');
export const saveTaskFailure = createAction('[Tasks] Save Failure', props<{ error: string }>());

export const deleteTask = createAction('[Tasks] Delete', props<{ id: number }>());
export const deleteTaskSuccess = createAction('[Tasks] Delete Success', props<{ id: number }>());
export const deleteTaskFailure = createAction('[Tasks] Delete Failure', props<{ error: string }>());
export const clearTaskFeedback = createAction('[Tasks] Clear Feedback');
