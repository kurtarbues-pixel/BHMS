import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { api, getToken, saveToken } from '../api/client';
import { AuthResponse, LoginCredentials, RegisterCredentials, Role, User } from '../types';

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  login: (credentials: LoginCredentials) => Promise<void>;
  register: (credentials: RegisterCredentials) => Promise<void>;
  logout: () => Promise<void>;
  hasRole: (...roles: Role[]) => boolean;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(Boolean(getToken()));

  const refreshUser = useCallback(async () => {
    if (!getToken()) {
      setUser(null);
      setIsLoading(false);
      return;
    }

    try {
      const data = await api<{ user: User }>('/me');
      setUser(data.user);
    } catch {
      setUser(null);
      saveToken(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    const handleUnauthorized = () => {
      setUser(null);
      saveToken(null);
    };

    window.addEventListener('bhms:unauthorized', handleUnauthorized);
    refreshUser();

    return () => {
      window.removeEventListener('bhms:unauthorized', handleUnauthorized);
    };
  }, [refreshUser]);

  const login = async (credentials: LoginCredentials) => {
    const data = await api<AuthResponse>('/login', {
      method: 'POST',
      body: credentials,
    });
    saveToken(data.token);
    setUser(data.user);
  };

  const register = async (credentials: RegisterCredentials) => {
    const data = await api<AuthResponse>('/register', {
      method: 'POST',
      body: credentials,
    });
    saveToken(data.token);
    setUser(data.user);
  };

  const logout = async () => {
    try {
      await api('/logout', { method: 'POST' });
    } catch {
      // Clear token locally even if server returns error or is unreachable
    } finally {
      saveToken(null);
      setUser(null);
    }
  };

  const hasRole = (...roles: Role[]): boolean => {
    if (!user) return false;
    return roles.includes(user.role);
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        login,
        register,
        logout,
        hasRole,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

