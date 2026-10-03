import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = 'http://localhost:9000/api';

  constructor(private http: HttpClient, private router: Router) {}

  login(username: string, password: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/login`, { username, password });
  }

  register(username: string, password: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/register`, { username, password });
  }

  logout() {
    localStorage.removeItem('token');
    this.router.navigate(['/login']);
  }

  isAuthenticated(): boolean {
    const token = localStorage.getItem('token');
    if (!token) {
      return false;
    }
    const payload = this.readPayload(token);
    if (payload?.exp && payload.exp * 1000 <= Date.now()) {
      localStorage.removeItem('token');
      return false;
    }
    return true;
  }

  currentUsername(): string {
    const token = localStorage.getItem('token');
    const username = token ? this.readPayload(token)?.username : undefined;
    return typeof username === 'string' && username.length > 0 ? username : 'Usuário';
  }

  private readPayload(token: string): { exp?: number; username?: string } | null {
    const segment = token.split('.')[1];
    if (!segment) {
      return null;
    }
    try {
      const padded = segment.replace(/-/g, '+').replace(/_/g, '/');
      const pad = padded.length % 4 === 0 ? '' : '='.repeat(4 - (padded.length % 4));
      const payload = JSON.parse(atob(padded + pad)) as { exp?: unknown; username?: unknown };
      return {
        exp: typeof payload.exp === 'number' ? payload.exp : undefined,
        username: typeof payload.username === 'string' ? payload.username : undefined,
      };
    } catch {
      return null;
    }
  }
}
