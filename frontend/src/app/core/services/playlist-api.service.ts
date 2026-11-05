import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ApiResponse } from '../models/api-response.model';
import { CreatePlaylistDto, Playlist, PlaylistDetail } from '../models/playlist.model';

@Injectable({ providedIn: 'root' })
export class PlaylistApiService {
  private readonly http = inject(HttpClient);
  list() { return this.http.get<ApiResponse<Playlist[]>>('/api/playlists'); }
  getById(id: number) { return this.http.get<ApiResponse<PlaylistDetail>>(`/api/playlists/${id}`); }
  create(dto: CreatePlaylistDto) { return this.http.post<ApiResponse<Playlist>>('/api/playlists', dto); }
  update(id: number, dto: CreatePlaylistDto) { return this.http.put<ApiResponse<Playlist>>(`/api/playlists/${id}`, dto); }
  remove(id: number) { return this.http.delete<ApiResponse<unknown>>(`/api/playlists/${id}`); }
  addSong(id: number, song_id: number, position?: number) { return this.http.post<ApiResponse<unknown>>(`/api/playlists/${id}/songs`, { song_id, position }); }
  removeSong(id: number, songId: number) { return this.http.delete<ApiResponse<unknown>>(`/api/playlists/${id}/songs/${songId}`); }
  reorderSong(id: number, songId: number, position: number) { return this.http.put<ApiResponse<unknown>>(`/api/playlists/${id}/songs/${songId}/reorder`, { position }); }
}
