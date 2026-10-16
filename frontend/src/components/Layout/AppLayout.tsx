import React, { useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { Notice, NoticeData } from '../Common/Notice';
import { ThemeToggle } from '../Common/ThemeToggle';
import { Role } from '../../types';

export interface NavItem {
  id: string;
  label: string;
  roles: Role[];
  icon?: string;
}

export const NAV_ITEMS: NavItem[] = [
  { id: 'dashboard', label: 'Dashboard', roles: ['admin', 'landlord', 'staff'], icon: '📊' },
  { id: 'rooms', label: 'Rooms & Units', roles: ['admin', 'landlord', 'staff', 'tenant'], icon: '🏢' },
  { id: 'leases', label: 'Tenants & Occupancy', roles: ['admin', 'landlord', 'staff', 'tenant'], icon: '👥' },
  { id: 'inquiries', label: 'Room Requests', roles: ['admin', 'landlord', 'staff', 'tenant'], icon: '📬' },
  { id: 'billing', label: 'Charges & Invoices', roles: ['admin', 'landlord', 'staff', 'tenant'], icon: '💳' },
  { id: 'accounts', label: 'User Accounts', roles: ['admin'], icon: '⚙️' },
  { id: 'activity', label: 'Activity Log', roles: ['admin'], icon: '📜' },
];

interface AppLayoutProps {
  currentPage: string;
  onNavigate: (pageId: string) => void;
  notice: NoticeData | null;
  onDismissNotice: () => void;
  children: React.ReactNode;
}

export const AppLayout: React.FC<AppLayoutProps> = ({
  currentPage,
  onNavigate,
  notice,
  onDismissNotice,
  children,
}) => {
  const { user, logout } = useAuth();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  if (!user) return null;

  const allowedNavItems = NAV_ITEMS.filter((item) => item.roles.includes(user.role));
  const currentItem = allowedNavItems.find((item) => item.id === currentPage) || allowedNavItems[0];
  const userInitials = user.name
    ? user.name.split(' ').map((n) => n[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()
    : user.email.substring(0, 2).toUpperCase();

  const handleNavClick = (id: string) => {
    onNavigate(id);
    setMobileMenuOpen(false);
  };

  return (
    <div className={`app-shell ${mobileMenuOpen ? 'mobile-open' : ''}`}>
      <aside className={`sidebar ${mobileMenuOpen ? 'show' : ''}`}>
        <a
          className="brand"
          href="#dashboard"
          onClick={(e) => {
            e.preventDefault();
            handleNavClick(user.role === 'tenant' ? 'rooms' : 'dashboard');
          }}
        >
          <span className="brand-mark" aria-hidden="true">BH</span>
          <div className="brand-text">
            <strong>Boarding House</strong>
            <small>Management System</small>
          </div>
        </a>

        <nav className="nav-list" aria-label="Main navigation">
          {allowedNavItems.map((item) => (
            <button
              key={item.id}
              type="button"
              className={`nav-link ${currentPage === item.id ? 'active' : ''}`}
              onClick={() => handleNavClick(item.id)}
            >
              <span className="nav-icon" aria-hidden="true">{item.icon}</span>
              <span className="nav-label">{item.label}</span>
            </button>
          ))}
        </nav>

        <div className="sidebar-bottom">
          <div className="sidebar-role-badge">
            <span className="role-chip">{user.role}</span>
          </div>
          <span className="sidebar-note">Decoupled Property Portal</span>
        </div>
      </aside>

      {mobileMenuOpen && (
        <div
          className="sidebar-backdrop"
          onClick={() => setMobileMenuOpen(false)}
          aria-hidden="true"
        />
      )}

      <main className="main-area">
        <header className="topbar">
          <div className="topbar-left">
            <button
              type="button"
              className="mobile-toggle"
              onClick={() => setMobileMenuOpen((prev) => !prev)}
              aria-label="Toggle navigation menu"
            >
              ☰
            </button>
            <div>
              <p className="eyebrow">PORTAL &bull; {user.role.toUpperCase()}</p>
              <h1>{currentItem ? currentItem.label : 'Management'}</h1>
            </div>
          </div>

          <div className="top-actions">
            <ThemeToggle showLabel={false} />

            <div className="account-pill" title={user.email}>
              <div className="avatar-circle" aria-hidden="true">{userInitials}</div>
              <div className="account-info">
                <strong>{user.name}</strong>
                <span>{user.email}</span>
              </div>
            </div>

            <button
              type="button"
              className="button secondary signout-btn"
              onClick={logout}
              title="Sign out of your account"
            >
              Sign out
            </button>
          </div>
        </header>

        <Notice notice={notice} onDismiss={onDismissNotice} />

        <div className="page-content">{children}</div>
      </main>
    </div>
  );
};

