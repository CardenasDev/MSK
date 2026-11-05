import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ApiResponse } from '../models/api-response.model';
import { BulkSongImportResult, BulkSongImportRow, CreateSongDto, Song, SongFilterParams } from '../models/song.model';

@Injectable({ providedIn: 'root' })
export class SongApiService {
  private readonly http = inject(HttpClient);
  getSongs(filters: SongFilterParams): Observable<ApiResponse<Song[]>> {
    let params = new HttpParams().set('page', filters.page).set('limit', filters.limit);
    if (filters.search?.trim()) params = params.set('search', filters.search.trim());
    if (filters.artist?.trim()) params = params.set('artist', filters.artist.trim());
    return this.http.get<ApiResponse<Song[]>>('/api/songs', { params });
  }
  getById(id: number) { return this.http.get<ApiResponse<Song>>(`/api/songs/${id}`); }
  create(dto: CreateSongDto) { return this.http.post<ApiResponse<Song>>('/api/songs', dto); }
  update(id: number, dto: Partial<CreateSongDto>) { return this.http.put<ApiResponse<Song>>(`/api/songs/${id}`, dto); }
  remove(id: number) { return this.http.delete<ApiResponse<unknown>>(`/api/songs/${id}`); }
  bulkImport(rows: BulkSongImportRow[]) {
    return this.http.post<ApiResponse<BulkSongImportResult>>('/api/songs/bulk-import', { rows });
  }
}
