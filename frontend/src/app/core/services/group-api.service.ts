import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ApiResponse } from '../models/api-response.model';
import { CreateGroupDto, Group, GroupDetail } from '../models/group.model';

@Injectable({ providedIn: 'root' })
export class GroupApiService {
  private readonly http = inject(HttpClient);
  list() { return this.http.get<ApiResponse<Group[]>>('/api/groups'); }
  getById(id: number) { return this.http.get<ApiResponse<GroupDetail>>(`/api/groups/${id}`); }
  create(dto: CreateGroupDto) { return this.http.post<ApiResponse<Group>>('/api/groups', dto); }
  update(id: number, dto: CreateGroupDto) { return this.http.put<ApiResponse<Group>>(`/api/groups/${id}`, dto); }
  remove(id: number) { return this.http.delete<ApiResponse<unknown>>(`/api/groups/${id}`); }
  addPlaylist(id: number, playlist_id: number, position?: number) { return this.http.post<ApiResponse<unknown>>(`/api/groups/${id}/playlists`, { playlist_id, position }); }
  removePlaylist(id: number, playlistId: number) { return this.http.delete<ApiResponse<unknown>>(`/api/groups/${id}/playlists/${playlistId}`); }
  reorderPlaylist(id: number, playlistId: number, position: number) { return this.http.put<ApiResponse<unknown>>(`/api/groups/${id}/playlists/${playlistId}/reorder`, { position }); }
}
