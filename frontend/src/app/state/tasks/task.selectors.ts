import { createFeatureSelector, createSelector } from '@ngrx/store';
import { TaskState } from './task.model';

export const selectTaskState = createFeatureSelector<TaskState>('tasks');

export const selectTasks = createSelector(selectTaskState, (state) => state.items);
export const selectTasksLoading = createSelector(selectTaskState, (state) => state.loading);
export const selectTasksLoaded = createSelector(selectTaskState, (state) => state.loaded);
export const selectTasksSaving = createSelector(selectTaskState, (state) => state.saving);
export const selectTaskError = createSelector(selectTaskState, (state) => state.error);
export const selectTaskFormError = createSelector(selectTaskState, (state) => state.formError);
