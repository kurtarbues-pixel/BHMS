import { Room } from './room';
import { User } from './auth';
import { Charge } from './billing';

export type LeaseStatus = 'active' | 'ended' | 'terminated';

export interface Lease {
  id: number;
  room_id: number;
  tenant_id: number;
  monthly_rent: number | string;
  start_date: string;
  end_date?: string | null;
  status: LeaseStatus;
  created_at?: string;
  updated_at?: string;
  tenant?: User;
  room?: Room;
  charges?: Charge[];
}

export interface LeaseFormData {
  room_id: number;
  tenant_id: number;
  start_date: string;
  end_date?: string | null;
  monthly_rent?: number | null;
}

