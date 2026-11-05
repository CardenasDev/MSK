export interface ApiResponse<T> {
  status: 'success' | 'error';
  data: T;
  meta?: PaginationMeta;
  message?: string;
  errors?: ApiValidationError[];
}
export interface PaginationMeta { page: number; limit: number; total: number; totalPages: number; }
export interface ApiValidationError { field: string; rule?: string; message: string; }
