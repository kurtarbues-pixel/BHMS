export type Role = 'admin' | 'landlord' | 'staff' | 'tenant';

export interface User {
  id: number;
  name: string;
  username?: string | null;
  email: string;
  phone?: string | null;
  role: Role;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface LoginCredentials {
  identifier?: string;
  email?: string;
  password?: string;
}

export interface RegisterCredentials {
  name: string;
  email: string;
  phone?: string;
  role?: 'tenant';
  password?: string;
  password_confirmation?: string;
}

export interface AuthResponse {
  token: string;
  user: User;
  message?: string;
}

