import { HttpErrorResponse, HttpEventType } from '@angular/common/http';
import { Component, inject, OnDestroy, signal } from '@angular/core';
import { DialogService } from '../../services/dialog.service';
import { DocumentItem, DocumentService } from '../../services/document.service';
import { AuthService } from '../../services/auth.service';

interface UploadStage {
  id: string;
  label: string;
}

@Component({
  selector: 'app-upload',
  standalone: true,
  templateUrl: './upload.component.html',
  styleUrl: './upload.component.scss',
})
export class UploadComponent implements OnDestroy {
  private readonly documentsApi = inject(DocumentService);
  private readonly dialogs = inject(DialogService);
  private readonly auth = inject(AuthService);
  private stageTimer: ReturnType<typeof setInterval> | null = null;

  readonly stages: UploadStage[] = [
    { id: 'preparar', label: 'Preparar' },
    { id: 'enviar', label: 'Enviar' },
    { id: 'extrair', label: 'Extrair' },
    { id: 'chunks', label: 'Chunks' },
    { id: 'embeddings', label: 'Embeddings' },
    { id: 'finalizar', label: 'Finalizar' },
  ];

  readonly documents = signal<DocumentItem[]>([]);
  readonly selected = signal<File[]>([]);
  readonly loading = signal(true);
  readonly uploading = signal(false);
  readonly currentName = signal('');
  readonly message = signal('');
  readonly errorMessage = signal('');
  readonly dragging = signal(false);
  readonly progress = signal(0);
  readonly stageIndex = signal(0);
  readonly loadedBytes = signal(0);
  readonly totalBytes = signal(0);

  constructor() {
    this.reload();
  }

  ngOnDestroy(): void {
    this.clearStageTimer();
  }

  reload(): void {
    this.loading.set(true);
    this.documentsApi.list().subscribe({
      next: (documents) => {
        this.documents.set(Array.isArray(documents) ? documents : []);
        this.loading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        this.loading.set(false);
        this.errorMessage.set(error.status === 0
          ? 'Não foi possível conectar ao servidor.'
          : 'Não foi possível carregar os documentos.');
      },
    });
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(true);
  }

  onDragLeave(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(false);
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(false);
    this.addFiles(event.dataTransfer?.files);
  }

  onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.addFiles(input.files);
    input.value = '';
  }

  removeSelected(index: number): void {
    this.selected.update((files) => files.filter((_, position) => position !== index));
  }

  sendSelected(): void {
    const files = this.selected();
    if (files.length === 0 || this.uploading()) {
      return;
    }
    this.uploadNext(files, 0);
  }

  async remove(document: DocumentItem): Promise<void> {
    if (this.uploading()) {
      return;
    }
    if (!await this.dialogs.confirm(`Excluir ${document.original_name}?`)) {
      return;
    }
    this.documentsApi.remove(document.id).subscribe({
      next: () => {
        this.documents.update((items) => items.filter((item) => item.id !== document.id));
        this.errorMessage.set('');
      },
      error: () => {
        this.errorMessage.set('Não foi possível excluir o documento.');
        void this.dialogs.error('Não foi possível excluir o documento.');
      },
    });
  }

  retry(document: DocumentItem): void {
    this.beginProgress(document.original_name, document.size_bytes);
    this.loadedBytes.set(document.size_bytes);
    this.startServerStages();
    this.documentsApi.retry(document.id).subscribe({
      next: (updated) => this.completeProgress(() => this.finish(updated, false)),
      error: (error: HttpErrorResponse) => {
        this.clearStageTimer();
        this.uploading.set(false);
        this.finish(error.error, true, error);
      },
    });
  }

  transferPercent(): number {
    const total = this.totalBytes();
    if (total <= 0) {
      return 0;
    }
    return Math.min(100, Math.round((this.loadedBytes() / total) * 100));
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

  formatSize(bytes: number): string {
    if (bytes < 1024) {
      return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
      return `${(bytes / 1024).toFixed(2)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  }

  formatDate(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('pt-BR');
  }

  private addFiles(list: FileList | null | undefined): void {
    const incoming = Array.from(list ?? []);
    if (incoming.length === 0) {
      return;
    }
    this.selected.update((current) => {
      const next = [...current];
      for (const file of incoming) {
        const exists = next.some((item) => item.name === file.name && item.size === file.size);
        if (!exists) {
          next.push(file);
        }
      }
      return next;
    });
    this.errorMessage.set('');
  }

  private uploadNext(files: File[], index: number): void {
    const file = files[index];
    if (!file) {
      this.uploading.set(false);
      this.currentName.set('');
      this.selected.set([]);
      return;
    }
    this.beginProgress(file.name, file.size);
    this.documentsApi.upload(file).subscribe({
      next: (event) => {
        if (event.type === HttpEventType.UploadProgress) {
          this.onUploadProgress(event.loaded, event.total ?? file.size);
          return;
        }
        if (event.type === HttpEventType.Sent && file.size < 262144) {
          this.loadedBytes.set(file.size);
          this.startServerStages();
          return;
        }
        if (event.type === HttpEventType.Response) {
          this.completeProgress(() => {
            this.finish(event.body ?? undefined, false);
            this.uploadNext(files, index + 1);
          });
        }
      },
      error: (error: HttpErrorResponse) => {
        this.clearStageTimer();
        this.finish(error.error, true, error);
        this.uploadNext(files, index + 1);
      },
    });
  }

  private beginProgress(name: string, size: number): void {
    this.clearStageTimer();
    this.uploading.set(true);
    this.currentName.set(name);
    this.message.set('');
    this.errorMessage.set('');
    this.stageIndex.set(1);
    this.progress.set(8);
    this.loadedBytes.set(0);
    this.totalBytes.set(size);
  }

  private onUploadProgress(loaded: number, total: number): void {
    if (this.stageIndex() >= 2) {
      return;
    }
    this.loadedBytes.set(loaded);
    this.totalBytes.set(total);
    const ratio = total > 0 ? Math.min(1, loaded / total) : 0;
    this.progress.set(Math.max(8, Math.round(8 + ratio * 32)));
    if (ratio >= 1) {
      this.startServerStages();
      return;
    }
    if (this.stageIndex() < 2) {
      this.stageIndex.set(1);
    }
  }

  private startServerStages(): void {
    if (this.stageTimer || this.stageIndex() >= 2) {
      return;
    }
    this.loadedBytes.set(this.totalBytes());
    this.stageIndex.set(2);
    this.progress.set(48);
    const marks = [48, 62, 76, 90];
    this.stageTimer = setInterval(() => {
      const current = this.stageIndex();
      if (current >= 5) {
        this.clearStageTimer();
        return;
      }
      const next = current + 1;
      this.stageIndex.set(next);
      this.progress.set(marks[next - 2] ?? 90);
    }, 700);
  }

  private completeProgress(done: () => void): void {
    this.clearStageTimer();
    this.stageIndex.set(this.stages.length);
    this.progress.set(100);
    this.loadedBytes.set(this.totalBytes());
    setTimeout(done, 450);
  }

  private clearStageTimer(): void {
    if (this.stageTimer) {
      clearInterval(this.stageTimer);
      this.stageTimer = null;
    }
  }

  private finish(payload: unknown, failed: boolean, error?: HttpErrorResponse): void {
    const document = this.asDocument(payload);
    if (document) {
      this.remember(document);
      if (document.status === 'ready') {
        this.errorMessage.set('');
        this.message.set(`${document.original_name} ficou pronto.`);
      } else {
        this.message.set('');
        this.errorMessage.set(document.error_message || 'O documento não ficou pronto.');
      }
      this.reload();
      return;
    }

    if (error?.status === 401) {
      this.errorMessage.set('Sua sessão expirou. Entre de novo.');
      this.auth.logout();
      return;
    }

    this.message.set('');
    this.errorMessage.set(failed ? this.explain(error) : '');
    this.reload();
  }

  private remember(document: DocumentItem): void {
    this.documents.update((current) => [document, ...current.filter((item) => item.id !== document.id)]);
  }

  private asDocument(value: unknown): DocumentItem | undefined {
    const body = this.readBody(value);
    if (!body || typeof body['status'] !== 'string') {
      return undefined;
    }
    const rawId = body['id'];
    const id = typeof rawId === 'number'
      ? rawId
      : typeof rawId === 'string' && /^\d+$/.test(rawId) ? Number(rawId) : 0;
    if (!Number.isInteger(id) || id <= 0) {
      return undefined;
    }

    return { ...(body as unknown as DocumentItem), id };
  }

  private explain(error?: HttpErrorResponse): string {
    const body = this.readBody(error?.error);
    const candidates = [body?.['error'], body?.['error_message'], body?.['detail'], body?.['message']];
    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim() !== '') {
        return candidate;
      }
    }
    if (error?.status === 0) {
      return 'Não foi possível conectar ao servidor.';
    }
    if (error?.status === 413) {
      return 'O arquivo passa de 10 MB.';
    }

    return 'Não foi possível enviar o arquivo.';
  }

  private readBody(value: unknown): Record<string, unknown> | undefined {
    if (typeof value === 'string') {
      const start = value.indexOf('{');
      const end = value.lastIndexOf('}');
      if (start === -1 || end <= start) {
        return undefined;
      }
      try {
        value = JSON.parse(value.slice(start, end + 1)) as unknown;
      } catch {
        return undefined;
      }
    }
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      return undefined;
    }

    return value as Record<string, unknown>;
  }
}
