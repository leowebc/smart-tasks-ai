import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, OnDestroy, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DialogService } from '../../services/dialog.service';
import { SourceImport, SourceItem, SourceService } from '../../services/source.service';

@Component({
  selector: 'app-scraping',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './scraping.component.html',
  styleUrl: './scraping.component.scss',
})
export class ScrapingComponent implements OnDestroy {
  private readonly sourcesApi = inject(SourceService);
  private readonly dialogs = inject(DialogService);
  private progressTimer: ReturnType<typeof setInterval> | null = null;

  readonly groups = signal<SourceImport[]>([]);
  readonly pages = signal<SourceItem[]>([]);
  readonly openGroups = signal<number[]>([]);
  readonly queue = signal<string[]>([]);
  readonly loading = signal(true);
  readonly running = signal(false);
  readonly errorMessage = signal('');
  readonly currentUrl = signal('');
  readonly currentIndex = signal(0);
  readonly progress = signal(0);
  readonly elapsed = signal(0);
  url = '';

  constructor() {
    this.reload();
  }

  ngOnDestroy(): void {
    this.clearTimer();
  }

  reload(): void {
    this.loading.set(true);
    this.sourcesApi.list().subscribe({
      next: (catalog) => {
        const groups = catalog?.groups ?? [];
        const covered = new Set(groups.flatMap((group) => group.pages.map((page) => page.id)));
        this.groups.set(groups);
        this.pages.set((catalog?.pages ?? []).filter((page) => !covered.has(page.id)));
        this.loading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.loading.set(false);
        this.errorMessage.set(error.status === 0
          ? 'Não foi possível conectar ao servidor.'
          : 'Não foi possível carregar as fontes.');
      },
    });
  }

  add(): void {
    const url = this.url.trim();
    if (url === '' || this.running()) {
      return;
    }
    if (this.queue().includes(url)) {
      this.errorMessage.set('Essa URL já está na fila.');
      void this.dialogs.error('Essa URL já está na fila.');
      return;
    }
    this.queue.update((items) => [...items, url]);
    this.url = '';
    this.errorMessage.set('');
  }

  removeQueued(index: number): void {
    if (this.running()) {
      return;
    }
    this.queue.update((items) => items.filter((_, position) => position !== index));
  }

  clearQueue(): void {
    if (this.running()) {
      return;
    }
    this.queue.set([]);
  }

  start(): void {
    const urls = this.queue();
    if (urls.length === 0 || this.running()) {
      return;
    }
    this.running.set(true);
    this.errorMessage.set('');
    this.runNext(urls, 0);
  }

  async remove(source: SourceItem): Promise<void> {
    if (this.running()) {
      return;
    }
    if (!await this.dialogs.confirm(`Excluir ${source.original_name}?`)) {
      return;
    }
    this.sourcesApi.remove(source.id).subscribe({
      next: () => {
        this.errorMessage.set('');
        this.reload();
      },
      error: () => {
        this.errorMessage.set('Não foi possível excluir a fonte.');
        void this.dialogs.error('Não foi possível excluir a fonte.');
      },
    });
  }

  async removeGroup(group: SourceImport): Promise<void> {
    if (this.running()) {
      return;
    }
    if (!await this.dialogs.confirm(`Excluir a importação ${group.title} e as ${group.page_count} páginas?`)) {
      return;
    }
    this.sourcesApi.removeGroup(group.id).subscribe({
      next: () => {
        this.errorMessage.set('');
        this.reload();
      },
      error: () => {
        this.errorMessage.set('Não foi possível excluir a importação.');
        void this.dialogs.error('Não foi possível excluir a importação.');
      },
    });
  }

  expanded(id: number): boolean {
    return this.openGroups().includes(id);
  }

  toggleOpen(id: number): void {
    this.openGroups.update((ids) => ids.includes(id) ? ids.filter((item) => item !== id) : [...ids, id]);
  }

  retry(source: SourceItem): void {
    if (this.running()) {
      return;
    }
    this.errorMessage.set('');
    this.sourcesApi.retry(source.id).subscribe({
      next: (updated) => this.note(updated),
      error: (error: HttpErrorResponse) => this.note(error.error, error),
    });
  }

  statusLabel(status: string): string {
    if (status === 'ready') {
      return 'Processado';
    }
    if (status === 'failed') {
      return 'Falhou';
    }
    return 'Processando';
  }

  formatDate(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('pt-BR');
  }

  private runNext(urls: string[], index: number): void {
    const url = urls[index];
    if (!url) {
      this.progress.set(100);
      this.clearTimer();
      setTimeout(() => {
        this.running.set(false);
        this.currentUrl.set('');
        this.queue.set([]);
      }, 400);
      return;
    }
    this.currentIndex.set(index);
    this.currentUrl.set(url);
    this.elapsed.set(0);
    this.progress.set(Math.round((index / urls.length) * 100));
    this.tickToward(index, urls.length);
    this.sourcesApi.add(url, true).subscribe({
      next: (body) => {
        this.note(body);
        this.progress.set(Math.round(((index + 1) / urls.length) * 100));
        this.runNext(urls, index + 1);
      },
      error: (error: HttpErrorResponse) => {
        this.note(error.error, error);
        this.progress.set(Math.round(((index + 1) / urls.length) * 100));
        this.runNext(urls, index + 1);
      },
    });
  }

  private tickToward(index: number, total: number): void {
    this.clearTimer();
    const started = Date.now();
    this.progressTimer = setInterval(() => {
      const seconds = Math.floor((Date.now() - started) / 1000);
      this.elapsed.set(seconds);
      const simulated = Math.min(99, Math.round((seconds / 40) * 100));
      const base = (index / total) * 100;
      this.progress.set(Math.min(99, Math.round(base + simulated / total)));
    }, 400);
  }

  private clearTimer(): void {
    if (this.progressTimer) {
      clearInterval(this.progressTimer);
      this.progressTimer = null;
    }
  }

  private note(payload: unknown, error?: HttpErrorResponse): void {
    if (this.asSource(payload) || this.hasPages(payload)) {
      this.errorMessage.set('');
      this.reload();
      return;
    }
    const body = payload && typeof payload === 'object' ? payload as { error?: unknown } : undefined;
    const text = typeof body?.error === 'string' ? body.error : '';
    const message = text !== ''
      ? text
      : error?.status === 0
        ? 'Não foi possível conectar ao servidor.'
        : 'Não foi possível importar a URL.';
    this.errorMessage.set(message);
    void this.dialogs.error(message);
  }

  private asSource(value: unknown): SourceItem | undefined {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      return undefined;
    }
    const body = value as SourceItem;
    return typeof body.id === 'number' && typeof body.status === 'string' ? body : undefined;
  }

  private hasPages(value: unknown): boolean {
    return !!value && typeof value === 'object' && 'pages' in value && Array.isArray(value.pages);
  }
}
