import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  const token = localStorage.getItem('token');
  const isApi = request.url.includes('/api/')
    && !request.url.includes('/api/login')
    && !request.url.includes('/api/register');
  const outgoing = token && isApi
    ? request.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    : request;

  return next(outgoing).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401 && isApi) {
        localStorage.removeItem('token');
        sessionStorage.setItem('authNotice', 'Sua sessão expirou. Entre de novo.');
        inject(Router).navigate(['/login']);
      }
      return throwError(() => error);
    }),
  );
};
