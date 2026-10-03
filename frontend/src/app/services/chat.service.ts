import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface ChatSource {
  id: number;
  original_name: string;
  source_url: string | null;
}

export interface ChatExcerpt {
  document_id: number | null;
  original_name: string;
  source_url: string | null;
  chunk_index: number;
  excerpt: string;
  score?: number;
  origin?: 'document' | 'scraping';
}

export interface ChatReply {
  answer: string;
  sources: ChatSource[];
  excerpts: ChatExcerpt[];
  sufficient: boolean;
  retrieval?: 'rag';
}

@Injectable({
  providedIn: 'root',
})
export class ChatService {
  private readonly apiUrl = 'http://localhost:9000/api/chat';

  constructor(private readonly http: HttpClient) {}

  ask(question: string, sourceIds: number[]): Observable<ChatReply> {
    return this.http.post<ChatReply>(this.apiUrl, {
      question,
      source_ids: sourceIds,
    });
  }
}
