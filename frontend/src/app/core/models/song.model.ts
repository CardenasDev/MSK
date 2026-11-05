export interface Song { id: number; title: string; artist: string; album: string | null; discovery_date: string | null; duration_seconds: number; notes: string | null; created_at: string; updated_at: string; }
export interface SongInPlaylist extends Song { position: number; added_at: string; }
export interface SongFilterParams { search?: string; artist?: string; page: number; limit: number; }
export interface CreateSongDto { title: string; artist: string; album?: string; discovery_date?: string; notes?: string; }
