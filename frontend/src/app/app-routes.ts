import { Routes } from '@angular/router';

import { AuthGuard } from './auth.guard';
import { LoginComponent } from './components/login/login.component';
import { RegisterComponent } from './components/register/register.component';
import { TaskListComponent } from './components/task-list/task-list.component';
import { AppLayoutComponent } from './layout/app-layout.component';
import { DashboardComponent } from './pages/dashboard/dashboard.component';
import { UnavailableComponent } from './pages/unavailable/unavailable.component';
import { ChatComponent } from './pages/chat/chat.component';
import { ScrapingComponent } from './pages/scraping/scraping.component';
import { UploadComponent } from './pages/upload/upload.component';

export const routes: Routes = [
  { path: 'login', component: LoginComponent },
  { path: 'register', component: RegisterComponent },
  {
    path: '',
    component: AppLayoutComponent,
    canActivate: [AuthGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      { path: 'dashboard', component: DashboardComponent },
      { path: 'tasks', component: TaskListComponent },
      { path: 'task', pathMatch: 'full', redirectTo: 'tasks' },
      { path: 'upload', component: UploadComponent },
      { path: 'scraping', component: ScrapingComponent },
      { path: 'chat', component: ChatComponent },
      { path: 'bases', component: UnavailableComponent, data: { title: 'Bases' } },
      { path: 'settings', component: UnavailableComponent, data: { title: 'Configurações' } },
    ],
  },
];
