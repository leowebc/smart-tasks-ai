import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class TaskService {
  private apiUrl = 'http://localhost:9000/api';

  constructor(private http: HttpClient) {}

  getTasks(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/tasks`);
  }

  addTask(title: string, description: string | null): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/tasks`, { title, description });
  }

  updateTask(id: number, title: string, description: string | null): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/tasks/${id}`, { title, description });
  }

  deleteTask(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/tasks/${id}`);
  }
}
