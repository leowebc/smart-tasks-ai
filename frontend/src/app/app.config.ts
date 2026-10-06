import { ApplicationConfig, isDevMode } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideEffects } from '@ngrx/effects';
import { provideStore } from '@ngrx/store';
import { provideStoreDevtools } from '@ngrx/store-devtools';
import { authInterceptor } from './interceptors/auth.interceptor';
import { provideAnimations } from '@angular/platform-browser/animations';
import { routes } from './app-routes';
import { TaskEffects } from './state/tasks/task.effects';
import { taskReducer } from './state/tasks/task.reducer';

export const appConfig: ApplicationConfig = {
  providers: [
    provideAnimations(),
    provideHttpClient(withInterceptors([authInterceptor])),
    provideRouter(routes),
    provideStore({ tasks: taskReducer }),
    provideEffects(TaskEffects),
    provideStoreDevtools({ maxAge: 25, logOnly: !isDevMode() }),
  ]
};
