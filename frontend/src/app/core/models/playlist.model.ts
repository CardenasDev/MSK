import { SongInPlaylist } from './song.model';
export interface Playlist { id: number; name: string; description: string | null; songs_count?: number; created_at: string; updated_at: string; }
export interface PlaylistDetail extends Playlist { songs: SongInPlaylist[]; }
export interface PlaylistSong {
  relation_id: number;
  playlist_id: number;
  song_id: number;
  position: number;
  added_at: string;
  title: string;
  artist: string;
  album: string | null;
  genre?: string | null;
  duration_seconds: number;
}
export interface CreatePlaylistDto { name: string; description?: string; }
export interface AssignSongToPlaylistDto { song_id: number; position?: number; }
