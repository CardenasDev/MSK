import { HttpInterceptorFn } from '@angular/common/http';
import { catchError, throwError } from 'rxjs';
import { inject } from '@angular/core';
import { ApiErrorService } from '../services/api-error.service';

export const apiErrorInterceptor: HttpInterceptorFn = (req, next) => {
  const errors = inject(ApiErrorService);
  return next(req).pipe(catchError((error) => {
    errors.show(error);
    return throwError(() => error);
  }));
};
