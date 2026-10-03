import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, ElementRef, ViewChild, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth.service';
import { ChatExcerpt, ChatService } from '../../services/chat.service';
import { DialogService } from '../../services/dialog.service';
import { DocumentItem, DocumentService } from '../../services/document.service';
import { SourceImport, SourceItem, SourceService } from '../../services/source.service';

interface SourceSnippet {
  chunkIndex: number;
  text: string;
}

interface SourceGroup {
  key: string;
  name: string;
  url: string | null;
  origin: 'document' | 'scraping';
  snippets: SourceSnippet[];
}

interface ChatTurn {
  role: 'user' | 'assistant';
  content: string;
  shown: string;
  sources: SourceGroup[];
}

@Component({
  selector: 'app-chat',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './chat.component.html',
  styleUrl: './chat.component.scss',
})
export class ChatComponent {
  private readonly auth = inject(AuthService);
  private readonly chatApi = inject(ChatService);
  private readonly documentsApi = inject(DocumentService);
  private readonly sourcesApi = inject(SourceService);
  private readonly dialogs = inject(DialogService);
  private revealTimer: ReturnType<typeof setInterval> | null = null;

  @ViewChild('log') private log?: ElementRef<HTMLElement>;

  readonly documents = signal<DocumentItem[]>([]);
  readonly groups = signal<SourceImport[]>([]);
  readonly sources = signal<SourceItem[]>([]);
  readonly openGroups = signal<number[]>([]);
  readonly selected = signal<number[]>([]);
  readonly messages = signal<ChatTurn[]>([]);
  readonly history = signal<ChatTurn[][]>([]);
  readonly showHistory = signal(false);
  readonly loading = signal(false);
  readonly loadingSources = signal(true);
  readonly errorMessage = signal('');
  question = '';

  constructor() {
    inject(DestroyRef).onDestroy(() => this.stopReveal());
    this.reload();
  }

  reload(): void {
    this.loadingSources.set(true);
    this.documentsApi.list().subscribe({
      next: (documents) => this.documents.set((documents ?? []).filter((item) => item.status === 'ready')),
      error: () => this.errorMessage.set('Não foi possível carregar os uploads.'),
    });
    this.sourcesApi.list().subscribe({
      next: (catalog) => {
        const groups = catalog?.groups ?? [];
        const covered = new Set(groups.flatMap((group) => group.pages.map((page) => page.id)));
        this.groups.set(groups);
        this.sources.set((catalog?.pages ?? []).filter((page) => page.status === 'ready' && !covered.has(page.id)));
        this.loadingSources.set(false);
      },
      error: () => {
        this.loadingSources.set(false);
        this.errorMessage.set('Não foi possível carregar as páginas.');
      },
    });
  }

  toggle(id: number): void {
    this.selected.update((ids) => ids.includes(id) ? ids.filter((item) => item !== id) : [...ids, id]);
  }

  checked(id: number): boolean {
    return this.selected().includes(id);
  }

  expanded(id: number): boolean {
    return this.openGroups().includes(id);
  }

  toggleOpen(id: number): void {
    this.openGroups.update((ids) => ids.includes(id) ? ids.filter((item) => item !== id) : [...ids, id]);
  }

    readyIds(group: SourceImport): number[] {
    return group.pages.filter((page) => page.status === 'ready').map((page) => page.id);
  }

  groupChecked(group: SourceImport): boolean {
    const ids = this.readyIds(group);
    return ids.length > 0 && ids.every((id) => this.selected().includes(id));
  }

  markGroup(input: HTMLInputElement, group: SourceImport): boolean {
    const ids = this.readyIds(group);
    const chosen = ids.filter((id) => this.selected().includes(id)).length;
    input.indeterminate = chosen > 0 && chosen < ids.length;
    return chosen === ids.length && ids.length > 0;
  }

  toggleGroup(group: SourceImport): void {
    const ids = this.readyIds(group);
    const all = this.groupChecked(group);
    this.selected.update((current) => all
      ? current.filter((id) => !ids.includes(id))
      : [...new Set([...current, ...ids])]);
  }

  newConversation(): void {
    this.stopReveal();
    this.loading.set(false);
    if (this.messages().length > 0) {
      this.history.update((items) => [this.complete(this.messages()), ...items].slice(0, 8));
    }
    this.messages.set([]);
    this.showHistory.set(false);
    this.errorMessage.set('');
  }

  openHistory(thread: ChatTurn[]): void {
    this.stopReveal();
    this.loading.set(false);
    this.messages.set(this.complete(thread));
    this.showHistory.set(false);
  }

  send(): void {
    const question = this.question.trim();
    const sourceIds = this.selected();
    if (question === '' || sourceIds.length === 0 || this.loading()) {
      return;
    }
    if (!this.auth.isAuthenticated()) {
      sessionStorage.setItem('authNotice', 'Sua sessão expirou. Entre de novo.');
      this.auth.logout();
      return;
    }
    this.loading.set(true);
    this.errorMessage.set('');
    this.messages.update((items) => [...items, { role: 'user', content: question, shown: question, sources: [] }]);
    this.question = '';
    this.scrollLog();
    this.chatApi.ask(question, sourceIds).subscribe({
      next: (reply) => this.reveal(reply.answer ?? '', reply.excerpts ?? []),
      error: (error: HttpErrorResponse) => {
        this.loading.set(false);
        const body = error.error as { error?: unknown; message?: unknown } | null;
        const text = body?.error ?? body?.message;
        const message = error.status === 401
          ? 'Sua sessão expirou. Entre de novo.'
          : typeof text === 'string' && text !== ''
            ? text
            : error.status === 0
              ? 'Não foi possível conectar ao servidor.'
              : 'Não foi possível obter a resposta.';
        this.errorMessage.set(message);
        void this.dialogs.error(message);
      },
    });
  }

  originLabel(origin: SourceGroup['origin']): string {
    if (origin === 'scraping') {
      return 'Web Scraping cadastrado';
    }
    return 'Documento interno';
  }

  async removeDocument(document: DocumentItem): Promise<void> {
    if (!await this.dialogs.confirm(`Excluir ${document.original_name}?`)) {
      return;
    }
    this.documentsApi.remove(document.id).subscribe({
      next: () => {
        this.documents.update((items) => items.filter((item) => item.id !== document.id));
        this.selected.update((ids) => ids.filter((id) => id !== document.id));
      },
      error: () => {
        this.errorMessage.set('Não foi possível excluir o documento.');
        void this.dialogs.error('Não foi possível excluir o documento.');
      },
    });
  }

  async removeSource(source: SourceItem): Promise<void> {
    if (!await this.dialogs.confirm(`Excluir ${source.original_name}?`)) {
      return;
    }
    this.sourcesApi.remove(source.id).subscribe({
      next: () => {
        this.selected.update((ids) => ids.filter((id) => id !== source.id));
        this.reload();
      },
      error: () => {
        this.errorMessage.set('Não foi possível excluir a fonte.');
        void this.dialogs.error('Não foi possível excluir a fonte.');
      },
    });
  }

  async removeGroup(group: SourceImport): Promise<void> {
    if (!await this.dialogs.confirm(`Excluir ${group.title}?`)) {
      return;
    }
    const ids = this.readyIds(group);
    this.sourcesApi.removeGroup(group.id).subscribe({
      next: () => {
        this.selected.update((current) => current.filter((id) => !ids.includes(id)));
        this.reload();
      },
      error: () => {
        this.errorMessage.set('Não foi possível excluir a importação.');
        void this.dialogs.error('Não foi possível excluir a importação.');
      },
    });
  }

  private reveal(answer: string, excerpts: ChatExcerpt[]): void {
    const sources = this.groupSources(excerpts);
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this.messages.update((items) => [...items, {
      role: 'assistant',
      content: answer,
      shown: reduced ? answer : '',
      sources,
    }]);
    this.scrollLog();
    if (reduced || answer.length === 0) {
      this.loading.set(false);
      return;
    }
    const tick = 30;
    const duration = Math.min(6000, Math.max(2500, answer.length * 18));
    const pace = Math.max(1, Math.ceil(answer.length / (duration / tick)));
    let shown = 0;
    this.revealTimer = setInterval(() => {
      shown = Math.min(answer.length, shown + pace);
      const visible = answer.slice(0, shown);
      this.messages.update((items) => {
        const next = items.slice();
        const index = next.length - 1;
        const turn = next[index];
        if (!turn || turn.role !== 'assistant') {
          return items;
        }
        next[index] = { ...turn, shown: visible };
        return next;
      });
      this.scrollLog();
      if (shown >= answer.length) {
        this.stopReveal();
        this.loading.set(false);
      }
    }, tick);
  }

  private groupSources(excerpts: ChatExcerpt[]): SourceGroup[] {
    const seen = new Set<string>();
    const groups = new Map<string, SourceGroup>();
    let kept = 0;
    for (const excerpt of excerpts) {
      const origin = excerpt.origin === 'scraping' ? 'scraping' : 'document';
      const piece = `${excerpt.document_id}:${excerpt.chunk_index}`;
      if (seen.has(piece)) {
        continue;
      }
      seen.add(piece);
      if (kept >= 5) {
        break;
      }
      kept += 1;
      const key = `doc:${excerpt.document_id}`;
      const current = groups.get(key) ?? {
        key,
        name: excerpt.original_name,
        url: excerpt.source_url,
        origin,
        snippets: [],
      };
      current.snippets.push({
        chunkIndex: excerpt.chunk_index,
        text: excerpt.excerpt.replace(/\s+/g, ' ').trim().slice(0, 180),
      });
      groups.set(key, current);
    }
    return [...groups.values()];
  }

  private complete(turns: ChatTurn[]): ChatTurn[] {
    return turns.map((turn) => ({ ...turn, shown: turn.content }));
  }

  private stopReveal(): void {
    if (this.revealTimer !== null) {
      clearInterval(this.revealTimer);
      this.revealTimer = null;
    }
  }

  private scrollLog(): void {
    queueMicrotask(() => {
      const element = this.log?.nativeElement;
      if (element) {
        element.scrollTop = element.scrollHeight;
      }
    });
  }

  formatSize(bytes: number): string {
    if (bytes < 1024) {
      return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
      return `${(bytes / 1024).toFixed(0)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  }
}
