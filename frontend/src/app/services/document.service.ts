import { Injectable } from '@angular/core';
import { HttpClient, HttpEvent } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface DocumentItem {
  id: number;
  original_name: string;
  status: 'processing' | 'ready' | 'failed' | string;
  error_message: string | null;
  chunk_count: number;
  size_bytes: number;
  created_at: string;
}

@Injectable({
  providedIn: 'root',
})
export class DocumentService {
  private readonly apiUrl = 'http://localhost:9000/api/documents';

  constructor(private readonly http: HttpClient) {}

  list(): Observable<DocumentItem[]> {
    return this.http.get<DocumentItem[]>(this.apiUrl);
  }

  upload(file: File): Observable<HttpEvent<DocumentItem>> {
    const body = new FormData();
    body.append('file', file);

    return this.http.post<DocumentItem>(this.apiUrl, body, {
      reportProgress: true,
      observe: 'events',
    });
  }

  retry(id: number): Observable<DocumentItem> {
    return this.http.post<DocumentItem>(`${this.apiUrl}/${id}/retry`, {});
  }

  remove(id: number): Observable<{ status: string }> {
    return this.http.delete<{ status: string }>(`${this.apiUrl}/${id}`);
  }
}
