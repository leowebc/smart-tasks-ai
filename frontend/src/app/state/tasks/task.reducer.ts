import { createReducer, on } from '@ngrx/store';
import * as TaskActions from './task.actions';
import { initialTaskState } from './task.model';

export const taskReducer = createReducer(
  initialTaskState,
  on(TaskActions.loadTasks, (state) => ({
    ...state,
    loading: true,
    error: '',
  })),
  on(TaskActions.loadTasksSuccess, (state, { tasks }) => ({
    ...state,
    items: tasks,
    loading: false,
    loaded: true,
    error: '',
  })),
  on(TaskActions.loadTasksFailure, (state, { error }) => ({
    ...state,
    items: [],
    loading: false,
    loaded: true,
    error,
  })),
  on(TaskActions.createTask, TaskActions.updateTask, (state) => ({
    ...state,
    saving: true,
    formError: '',
  })),
  on(TaskActions.saveTaskSuccess, (state) => ({
    ...state,
    saving: false,
    formError: '',
  })),
  on(TaskActions.saveTaskFailure, (state, { error }) => ({
    ...state,
    saving: false,
    formError: error,
  })),
  on(TaskActions.deleteTaskFailure, (state, { error }) => ({
    ...state,
    error,
  })),
  on(TaskActions.clearTaskFeedback, (state) => ({
    ...state,
    error: '',
    formError: '',
  })),
);
