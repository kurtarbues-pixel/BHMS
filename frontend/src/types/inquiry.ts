import { Room } from './room';
import { User } from './auth';

export type InquiryStatus = 'pending' | 'approved' | 'rejected';

export interface Inquiry {
  id: number;
  room_id: number;
  tenant_id: number;
  message?: string | null;
  status: InquiryStatus;
  created_at?: string;
  updated_at?: string;
  room?: Room;
  tenant?: User;
}

