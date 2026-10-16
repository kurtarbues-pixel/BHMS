export type RoomStatus = 'available' | 'occupied' | 'maintenance';

export interface RoomImage {
  id: number;
  room_id: number;
  url: string;
  is_primary?: boolean;
}

export interface Room {
  id: number;
  room_number: string;
  title: string;
  type?: string | null;
  description?: string | null;
  location: string;
  price: number | string;
  status: RoomStatus;
  amenities: string[];
  images?: RoomImage[];
  landlord_id?: number;
  created_at?: string;
  updated_at?: string;
}

export interface RoomFormData {
  room_number: string;
  title: string;
  type?: string | null;
  description?: string | null;
  location: string;
  price: number;
  status: RoomStatus;
  amenities: string[];
}

