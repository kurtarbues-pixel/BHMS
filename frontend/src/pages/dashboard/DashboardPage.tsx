import React, { useCallback, useEffect, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { api, fetchAll, formatMoney } from '../../api/client';
import { LandlordDashboardData, Room, Lease, Invoice, Inquiry } from '../../types';
import { StatCard } from '../../components/Common/StatCard';
import { Panel } from '../../components/Common/Panel';
import { StatusBadge } from '../../components/Common/StatusBadge';
import { LoadingState, ErrorState } from '../../components/Common/States';

interface DashboardPageProps {
  onNavigate: (pageId: string) => void;
  announce?: (message: string, kind?: 'success' | 'error' | 'warning' | 'info') => void;
}

interface TenantDashboardData {
  rooms: Room[];
  leases: Lease[];
  invoices: Invoice[];
  inquiries: Inquiry[];
}

export const DashboardPage: React.FC<DashboardPageProps> = ({ onNavigate, announce }) => {
  const { user } = useAuth();
  const isTenant = user?.role === 'tenant';

  const [landlordData, setLandlordData] = useState<LandlordDashboardData | null>(null);
  const [tenantData, setTenantData] = useState<TenantDashboardData | null>(null);
  const [recentInquiries, setRecentInquiries] = useState<Inquiry[]>([]);
  const [activeLeasesList, setActiveLeasesList] = useState<Lease[]>([]);
  const [recentInvoices, setRecentInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [processingInquiryId, setProcessingInquiryId] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      if (isTenant) {
        const [rooms, leases, invoices, inquiries] = await Promise.all([
          fetchAll<Room>('/rooms?per_page=50'),
          fetchAll<Lease>('/leases'),
          fetchAll<Invoice>('/invoices'),
          fetchAll<Inquiry>('/inquiries'),
        ]);
        setTenantData({ rooms, leases, invoices, inquiries });
      } else {
        const [dashboard, inquiries, leases, invoices] = await Promise.all([
          api<LandlordDashboardData>('/landlord/dashboard'),
          fetchAll<Inquiry>('/inquiries'),
          fetchAll<Lease>('/leases'),
          fetchAll<Invoice>('/invoices'),
        ]);
        setLandlordData(dashboard);
        setRecentInquiries(inquiries.slice(0, 5));
        setActiveLeasesList(leases.filter((l) => l.status === 'active').slice(0, 5));
        setRecentInvoices(invoices.slice(0, 5));
      }
    } catch (err: any) {
      setError(err.message || 'Failed to load dashboard data.');
    } finally {
      setLoading(false);
    }
  }, [isTenant]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleApproveInquiry = async (inquiry: Inquiry) => {
    if (!window.confirm(`Approve request from ${inquiry.tenant?.name || 'tenant'} for Room ${inquiry.room?.room_number}? This will automatically create an active lease!`)) {
      return;
    }

    setProcessingInquiryId(inquiry.id);
    try {
      await api(`/inquiries/${inquiry.id}/approve`, { method: 'POST' });
      announce?.('Request approved! Lease created and room marked occupied.', 'success');
      await loadData();
    } catch (err: any) {
      announce?.(err.message || 'Action could not be completed.', 'error');
    } finally {
      setProcessingInquiryId(null);
    }
  };

  if (loading) return <LoadingState message="Loading dashboard overview…" />;
  if (error) return <ErrorState error={error} retry={loadData} />;

  /* =======================================
     Tenant View
     ======================================= */
  if (isTenant && tenantData) {
    const activeLease = tenantData.leases.find((lease) => lease.status === 'active');
    const availableRoomsCount = tenantData.rooms.filter((r) => r.status === 'available').length;
    const unpaidInvoices = tenantData.invoices.filter((inv) => inv.status.toLowerCase() !== 'paid');
    const totalBalance = unpaidInvoices.reduce((sum, inv) => sum + Number(inv.balance || 0), 0);
    const myInquiries = tenantData.inquiries.slice(0, 5);
    const myInvoices = tenantData.invoices.slice(0, 5);

    return (
      <div className="dashboard-content">
        <div className="welcome-block">
          <div>
            <h2>Hello, {user?.name}!</h2>
            <p className="muted">Welcome to your tenant portal. Track your room, lease, and invoices here.</p>
          </div>
          <button type="button" className="button secondary" onClick={loadData}>
            Refresh
          </button>
        </div>

        <div className="stats-grid">
          <StatCard
            label="My Lease Status"
            value={activeLease ? `Room ${activeLease.room?.room_number}` : 'No active lease'}
            detail={activeLease ? `${formatMoney(activeLease.monthly_rent)} / month` : undefined}
          />
          <StatCard
            label="Available Rooms"
            value={availableRoomsCount}
            detail="Rooms open for rental"
          />
          <StatCard
            label="Unpaid Invoices"
            value={unpaidInvoices.length}
            detail={totalBalance > 0 ? `Total due: ${formatMoney(totalBalance)}` : 'All caught up!'}
          />
        </div>

        <div className="dashboard-tables-grid">
          {/* Active Lease / Room Info */}
          <Panel
            title="My Rental Lease"
            action={
              <button
                type="button"
                className="action-link-btn"
                onClick={() => onNavigate('rooms')}
              >
                Explore Rooms →
              </button>
            }
          >
            {activeLease ? (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Room</th>
                      <th>Type</th>
                      <th>Monthly Rent</th>
                      <th>Start Date</th>
                      <th>End Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>
                        <strong>Room {activeLease.room?.room_number}</strong>
                        <span className="cell-sub">{activeLease.room?.title}</span>
                      </td>
                      <td>{activeLease.room?.type || 'Standard'}</td>
                      <td>{formatMoney(activeLease.monthly_rent)}</td>
                      <td>{activeLease.start_date}</td>
                      <td>{activeLease.end_date || 'Open-ended'}</td>
                      <td>
                        <StatusBadge status={activeLease.status} />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            ) : (
              <div style={{ textAlign: 'center', padding: '24px 0', color: 'var(--muted)' }}>
                <p style={{ margin: '0 0 12px' }}>You do not have an active lease right now.</p>
                <button
                  type="button"
                  className="button primary sm"
                  onClick={() => onNavigate('rooms')}
                >
                  Browse Available Rooms
                </button>
              </div>
            )}
          </Panel>

          {/* Pending / Recent Inquiries */}
          <Panel
            title="My Room Requests"
            action={
              <button
                type="button"
                className="action-link-btn"
                onClick={() => onNavigate('inquiries')}
              >
                View All Requests →
              </button>
            }
          >
            {myInquiries.length > 0 ? (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Room</th>
                      <th>Date Submitted</th>
                      <th>Message</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {myInquiries.map((inq) => (
                      <tr key={inq.id}>
                        <td>
                          <strong>Room {inq.room?.room_number}</strong>
                          <span className="cell-sub">{inq.room?.title}</span>
                        </td>
                        <td>{inq.created_at ? new Date(inq.created_at).toLocaleDateString() : '—'}</td>
                        <td>{inq.message || '—'}</td>
                        <td>
                          <StatusBadge status={inq.status} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="muted" style={{ margin: 0, padding: '12px 0' }}>
                No room requests found. Browse rooms to submit one!
              </p>
            )}
          </Panel>

          {/* Recent Invoices */}
          <Panel
            title="Recent Billing & Invoices"
            action={
              <button
                type="button"
                className="action-link-btn"
                onClick={() => onNavigate('billing')}
              >
                View Full Billing Statement →
              </button>
            }
          >
            {myInvoices.length > 0 ? (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Period</th>
                      <th>Due Date</th>
                      <th>Total Due</th>
                      <th>Balance</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {myInvoices.map((inv) => (
                      <tr key={inv.id}>
                        <td>
                          <strong>{inv.billing_period}</strong>
                        </td>
                        <td>{inv.due_date}</td>
                        <td>{formatMoney(inv.total_amount_due)}</td>
                        <td>
                          <strong style={{ color: Number(inv.balance) > 0 ? 'var(--danger)' : 'var(--success)' }}>
                            {formatMoney(inv.balance)}
                          </strong>
                        </td>
                        <td>
                          <StatusBadge status={inv.status} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="muted" style={{ margin: 0, padding: '12px 0' }}>
                No billing statements generated yet.
              </p>
            )}
          </Panel>
        </div>

        <Panel title="Quick Actions" className="spaced-panel">
          <div className="quick-grid">
            <button
              type="button"
              className="quick-card"
              onClick={() => onNavigate('rooms')}
            >
              <strong>Browse Rooms</strong>
              <span>Explore available rooms and submit rental requests</span>
            </button>

            <button
              type="button"
              className="quick-card"
              onClick={() => onNavigate('inquiries')}
            >
              <strong>My Room Requests</strong>
              <span>Check status of pending or approved requests</span>
            </button>

            <button
              type="button"
              className="quick-card"
              onClick={() => onNavigate('billing')}
            >
              <strong>Invoices & Payments</strong>
              <span>View your rent billing statements and balances</span>
            </button>
          </div>
        </Panel>
      </div>
    );
  }

  /* =======================================
     Landlord / Admin View
     ======================================= */
  if (landlordData) {
    const occPercent = Math.min(100, Math.max(0, landlordData.occupancy.percent));

    return (
      <div className="dashboard-content">
        <div className="welcome-block">
          <div>
            <h2>Good day, {user?.name}!</h2>
            <p className="muted">Here is the real-time operational overview of your boarding house properties.</p>
          </div>
          <button type="button" className="button secondary" onClick={loadData}>
            Refresh
          </button>
        </div>

        {/* Visual Occupancy Rate Banner */}
        <div className="occupancy-card">
          <div className="occupancy-header">
            <div className="occupancy-title">
              <h3>Occupancy Rate</h3>
              <span className="occupancy-rate-pill">{occPercent}%</span>
            </div>
            <div className="occupancy-stats-pills">
              <span className="occupancy-pills-item">
                <span className="dot occupied" />
                {landlordData.occupancy.occupied} Occupied
              </span>
              <span className="occupancy-pills-item">
                <span className="dot available" />
                {landlordData.available_rooms} Available
              </span>
              <span>Total: {landlordData.occupancy.total} Units</span>
            </div>
          </div>
          <div className="occupancy-progress-bg">
            <div
              className="occupancy-progress-fill"
              style={{ width: `${occPercent}%` }}
              role="progressbar"
              aria-valuenow={occPercent}
              aria-valuemin={0}
              aria-valuemax={100}
            />
          </div>
        </div>

        {/* Stats Grid */}
        <div className="stats-grid">
          <StatCard
            label="Rooms Occupied"
            value={`${landlordData.occupancy.occupied} / ${landlordData.occupancy.total}`}
            detail={`${landlordData.occupancy.percent}% occupancy rate`}
          />
          <StatCard
            label="Available Rooms"
            value={landlordData.available_rooms}
            detail="Ready for tenancy"
          />
          <StatCard
            label="Active Leases"
            value={landlordData.active_leases}
            detail="Current occupied units"
          />
          <StatCard
            label="Registered Tenants"
            value={landlordData.tenant_count}
            detail="Total registered tenants"
          />
          <StatCard
            label="Pending Inquiries"
            value={landlordData.pending_inquiries}
            detail="Requests waiting for review"
          />
          <StatCard
            label="Revenue This Month"
            value={formatMoney(landlordData.monthly_revenue)}
            detail="Payments collected"
          />
        </div>

        {/* Operational Data Tables */}
        <div className="dashboard-tables-grid two-cols">
          {/* Recent Inquiries Table */}
          <Panel
            title="Pending & Recent Requests"
            action={
              <button
                type="button"
                className="action-link-btn"
                onClick={() => onNavigate('inquiries')}
              >
                View Inquiries Page →
              </button>
            }
          >
            {recentInquiries.length > 0 ? (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Applicant</th>
                      <th>Room</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {recentInquiries.map((inq) => (
                      <tr key={inq.id}>
                        <td>
                          <strong>{inq.tenant?.name || 'Applicant'}</strong>
                          <span className="cell-sub">{inq.tenant?.email}</span>
                        </td>
                        <td>
                          <strong>Room {inq.room?.room_number}</strong>
                          <span className="cell-sub">{inq.room ? formatMoney(inq.room.price) : ''}</span>
                        </td>
                        <td>
                          <StatusBadge status={inq.status} />
                        </td>
                        <td>
                          {inq.status === 'pending' ? (
                            <button
                              type="button"
                              className="button primary sm"
                              disabled={processingInquiryId === inq.id}
                              onClick={() => handleApproveInquiry(inq)}
                            >
                              {processingInquiryId === inq.id ? 'Approving…' : 'Approve'}
                            </button>
                          ) : (
                            <button
                              type="button"
                              className="button secondary sm"
                              onClick={() => onNavigate('inquiries')}
                            >
                              Review
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="muted" style={{ margin: 0, padding: '12px 0' }}>
                No inquiries submitted yet.
              </p>
            )}
          </Panel>

          {/* Active Leases Table */}
          <Panel
            title="Active Leases"
            action={
              <button
                type="button"
                className="action-link-btn"
                onClick={() => onNavigate('leases')}
              >
                Manage All Leases →
              </button>
            }
          >
            {activeLeasesList.length > 0 ? (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Unit</th>
                      <th>Tenant</th>
                      <th>Rent</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {activeLeasesList.map((lease) => (
                      <tr key={lease.id}>
                        <td>
                          <strong>Room {lease.room?.room_number}</strong>
                          <span className="cell-sub">{lease.room?.title}</span>
                        </td>
                        <td>
                          <strong>{lease.tenant?.name || 'Tenant'}</strong>
                          <span className="cell-sub">{lease.tenant?.email}</span>
                        </td>
                        <td>{formatMoney(lease.monthly_rent)}/mo</td>
                        <td>
                          <StatusBadge status={lease.status} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="muted" style={{ margin: 0, padding: '12px 0' }}>
                No active leases recorded.
              </p>
            )}
          </Panel>
        </div>

        {/* Latest Invoices Table */}
        <div className="dashboard-tables-grid">
          <Panel
            title="Latest Invoices & Balances"
            action={
              <button
                type="button"
                className="action-link-btn"
                onClick={() => onNavigate('billing')}
              >
                Go to Billing Hub →
              </button>
            }
          >
            {recentInvoices.length > 0 ? (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Period</th>
                      <th>Tenant / Room</th>
                      <th>Total Due</th>
                      <th>Balance</th>
                      <th>Due Date</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {recentInvoices.map((inv) => (
                      <tr key={inv.id}>
                        <td>
                          <strong>{inv.billing_period}</strong>
                        </td>
                        <td>
                          <strong>{inv.lease?.tenant?.name || 'Tenant'}</strong>
                          <span className="cell-sub">Room {inv.lease?.room?.room_number}</span>
                        </td>
                        <td>{formatMoney(inv.total_amount_due)}</td>
                        <td>
                          <strong style={{ color: Number(inv.balance) > 0 ? 'var(--danger)' : 'var(--success)' }}>
                            {formatMoney(inv.balance)}
                          </strong>
                        </td>
                        <td>{inv.due_date}</td>
                        <td>
                          <StatusBadge status={inv.status} />
                        </td>
                        <td>
                          <button
                            type="button"
                            className="button secondary sm"
                            onClick={() => onNavigate('billing')}
                          >
                            View
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <p className="muted" style={{ margin: 0, padding: '12px 0' }}>
                No invoices generated yet.
              </p>
            )}
          </Panel>
        </div>

        {/* Shortcuts */}
        <Panel title="Property Management Shortcuts" className="spaced-panel">
          <div className="quick-grid">
            <button
              type="button"
              className="quick-card"
              onClick={() => onNavigate('rooms')}
            >
              <strong>Manage Rooms</strong>
              <span>Add, edit, or toggle room availability</span>
            </button>

            <button
              type="button"
              className="quick-card"
              onClick={() => onNavigate('leases')}
            >
              <strong>Create Lease</strong>
              <span>Assign a tenant to an available room</span>
            </button>

            <button
              type="button"
              className="quick-card"
              onClick={() => onNavigate('billing')}
            >
              <strong>Generate Invoices</strong>
              <span>Process monthly invoices and record payments</span>
            </button>

            {user?.role === 'admin' && (
              <button
                type="button"
                className="quick-card"
                onClick={() => onNavigate('accounts')}
              >
                <strong>User Accounts</strong>
                <span>Manage staff and tenant access credentials</span>
              </button>
            )}
          </div>
        </Panel>
      </div>
    );
  }

  return null;
};
