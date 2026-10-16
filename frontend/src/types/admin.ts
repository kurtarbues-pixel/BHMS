import { Role, User } from './auth';

export interface ActivityLog {
  id: number;
  user_id?: number | null;
  action: string;
  subject_type?: string | null;
  subject_id?: number | null;
  properties?: Record<string, unknown> | null;
  ip_address?: string | null;
  user_agent?: string | null;
  created_at: string;
  user?: User;
}

export interface UserFormData {
  name: string;
  username?: string | null;
  email: string;
  phone?: string | null;
  role: Role;
  password?: string;
}

export interface UserUpdateData {
  name?: string;
  username?: string;
  email?: string;
  phone?: string;
  role?: Role;
  is_active?: boolean;
  password?: string;
}

