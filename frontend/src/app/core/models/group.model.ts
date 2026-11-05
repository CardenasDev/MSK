import { Playlist } from './playlist.model';
export interface Group { id: number; name: string; color_hex: string | null; description: string | null; playlists_count?: number; created_at: string; updated_at: string; }
export interface GroupDetail extends Group { playlists: (Playlist & { position: number; assigned_at: string })[]; }
export interface CreateGroupDto { name: string; color_hex?: string; description?: string; }
