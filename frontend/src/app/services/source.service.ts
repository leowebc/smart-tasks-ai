import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface SourceItem {
  id: number;
  original_name: string;
  source_url: string | null;
  status: 'processing' | 'ready' | 'failed' | string;
  error_message: string | null;
  chunk_count: number;
  created_at: string;
  import_id?: number | null;
}

export interface SourceImport {
  id: number;
  title: string;
  root_url: string;
  page_count: number;
  chunk_count: number;
  created_at: string;
  pages: SourceItem[];
}

export interface SourceCatalog {
  groups: SourceImport[];
  pages: SourceItem[];
}

@Injectable({
  providedIn: 'root',
})
export class SourceService {
  private readonly apiUrl = 'http://localhost:9000/api/sources';

  constructor(private readonly http: HttpClient) {}

  list(): Observable<SourceCatalog> {
    return this.http.get<SourceCatalog>(this.apiUrl);
  }

  add(url: string, followLinks = false): Observable<SourceItem | { pages: SourceItem[] }> {
    return this.http.post<SourceItem | { pages: SourceItem[] }>(this.apiUrl, {
      url,
      follow_links: followLinks,
    });
  }

  retry(id: number): Observable<SourceItem> {
    return this.http.post<SourceItem>(`${this.apiUrl}/${id}/retry`, {});
  }

  remove(id: number): Observable<{ status: string }> {
    return this.http.delete<{ status: string }>(`${this.apiUrl}/${id}`);
  }

  removeGroup(id: number): Observable<{ status: string }> {
    return this.http.delete<{ status: string }>(`http://localhost:9000/api/source-imports/${id}`);
  }
}
