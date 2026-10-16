import React, { useCallback, useState } from 'react';
import { useAuth } from './context/AuthContext';
import { AppLayout, NAV_ITEMS } from './components/Layout/AppLayout';
import { NoticeData } from './components/Common/Notice';
import { AuthPage } from './pages/auth/AuthPage';
import { DashboardPage } from './pages/dashboard/DashboardPage';
import { RoomsPage } from './pages/rooms/RoomsPage';
import { LeasesPage } from './pages/leases/LeasesPage';
import { InquiriesPage } from './pages/inquiries/InquiriesPage';
import { BillingPage } from './pages/billing/BillingPage';
import { AccountsPage } from './pages/accounts/AccountsPage';
import { ActivityPage } from './pages/activity/ActivityPage';

export const App: React.FC = () => {
  const { user, isLoading } = useAuth();
  const [currentPage, setCurrentPage] = useState<string>('dashboard');
  const [notice, setNotice] = useState<NoticeData | null>(null);

  const announce = useCallback(
    (message: string, kind: 'success' | 'error' | 'warning' | 'info' = 'success') => {
      setNotice({ message, kind });
      window.setTimeout(() => {
        setNotice(null);
      }, 5000);
    },
    []
  );

  if (isLoading) {
    return (
      <div className="loading-state" style={{ minHeight: '100vh' }}>
        <div className="spinner" aria-hidden="true"></div>
        <p>Connecting to Boarding House Management System…</p>
      </div>
    );
  }

  if (!user) {
    return <AuthPage onSuccessNotice={(msg) => announce(msg, 'success')} />;
  }

  const allowedNav = NAV_ITEMS.filter((item) => item.roles.includes(user.role));
  const activePageId = allowedNav.some((item) => item.id === currentPage)
    ? currentPage
    : user.role === 'tenant'
    ? 'rooms'
    : 'dashboard';

  return (
    <AppLayout
      currentPage={activePageId}
      onNavigate={setCurrentPage}
      notice={notice}
      onDismissNotice={() => setNotice(null)}
    >
      {activePageId === 'dashboard' && <DashboardPage onNavigate={setCurrentPage} announce={announce} />}
      {activePageId === 'rooms' && <RoomsPage announce={announce} />}
      {activePageId === 'leases' && <LeasesPage announce={announce} />}
      {activePageId === 'inquiries' && <InquiriesPage announce={announce} />}
      {activePageId === 'billing' && <BillingPage announce={announce} />}
      {activePageId === 'accounts' && <AccountsPage announce={announce} />}
      {activePageId === 'activity' && <ActivityPage />}
    </AppLayout>
  );
};

