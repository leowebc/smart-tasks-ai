import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { TaskItem, TaskStatus } from '../state/tasks/task.model';

@Injectable({
  providedIn: 'root'
})
export class TaskService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getTasks(): Observable<TaskItem[]> {
    return this.http.get<TaskItem[]>(`${this.apiUrl}/tasks`);
  }

  addTask(title: string, description: string | null, status: TaskStatus): Observable<TaskItem> {
    return this.http.post<TaskItem>(`${this.apiUrl}/tasks`, { title, description, status });
  }

  updateTask(
    id: number,
    title: string,
    description: string | null,
    status: TaskStatus,
  ): Observable<TaskItem> {
    return this.http.put<TaskItem>(`${this.apiUrl}/tasks/${id}`, { title, description, status });
  }

  deleteTask(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/tasks/${id}`);
  }
}
