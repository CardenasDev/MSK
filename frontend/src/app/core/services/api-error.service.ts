import { Injectable, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';

@Injectable({ providedIn: 'root' })
export class ApiErrorService {
  readonly message = signal<string | null>(null);
  show(error: unknown): void {
    const response = error as HttpErrorResponse;
    this.message.set(response?.error?.message || 'No se pudo completar la solicitud. Comprueba que la API esté disponible.');
  }
  clear(): void { this.message.set(null); }
}
