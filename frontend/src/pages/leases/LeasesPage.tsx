import React, { useCallback, useEffect, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { api, fetchAll, formatMoney, getTodayDateString, listData } from '../../api/client';
import { Lease, LeaseFormData, Room, User } from '../../types';
import { Panel } from '../../components/Common/Panel';
import { Field } from '../../components/Common/Field';
import { Modal } from '../../components/Common/Modal';
import { StatusBadge } from '../../components/Common/StatusBadge';
import { LoadingState, EmptyState, ErrorState } from '../../components/Common/States';

interface LeasesPageProps {
  announce: (message: string, kind?: 'success' | 'error' | 'warning' | 'info') => void;
}

export const LeasesPage: React.FC<LeasesPageProps> = ({ announce }) => {
  const { user } = useAuth();
  const canManage = user?.role !== 'tenant';

  const [leases, setLeases] = useState<Lease[]>([]);
  const [rooms, setRooms] = useState<Room[]>([]);
  const [tenants, setTenants] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Create lease modal state
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [selectedTenantId, setSelectedTenantId] = useState<string>('');
  const [selectedRoomId, setSelectedRoomId] = useState<string>('');
  const [startDate, setStartDate] = useState(getTodayDateString());
  const [endDate, setEndDate] = useState('');
  const [rentOverride, setRentOverride] = useState('');

  const loadData = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [leaseRows, roomRows] = await Promise.all([
        fetchAll<Lease>('/leases'),
        fetchAll<Room>('/rooms?per_page=50'),
      ]);
      setLeases(leaseRows);
      setRooms(roomRows);

      if (canManage) {
        const tenantRes = await api<{ data: User[] }>('/tenants');
        setTenants(listData<User>(tenantRes));
      }
    } catch (err: any) {
      setError(err.message || 'Failed to load lease records.');
    } finally {
      setLoading(false);
    }
  }, [canManage]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const openCreateModal = () => {
    setSelectedTenantId('');
    setSelectedRoomId('');
    setStartDate(getTodayDateString());
    setEndDate('');
    setRentOverride('');
    setIsModalOpen(true);
  };

  const handleCreateLease = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTenantId || !selectedRoomId) {
      announce('Please select both a tenant and a room.', 'warning');
      return;
    }

    setIsSaving(true);
    const payload: LeaseFormData = {
      tenant_id: Number(selectedTenantId),
      room_id: Number(selectedRoomId),
      start_date: startDate,
      end_date: endDate.trim() ? endDate : null,
      monthly_rent: rentOverride.trim() ? Number(rentOverride) : null,
    };

    try {
      await api('/leases', {
        method: 'POST',
        body: payload,
      });
      announce('Lease created successfully! The room is now marked as occupied.');
      setIsModalOpen(false);
      await loadData();
    } catch (err: any) {
      announce(err.message || 'Failed to create lease.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const handleEndLease = async (lease: Lease) => {
    const tenantName = lease.tenant?.name || 'this tenant';
    const roomNumber = lease.room?.room_number || 'this room';

    if (!window.confirm(`Are you sure you want to end the lease for ${tenantName} in Room ${roomNumber}? This will mark the room as available.`)) {
      return;
    }

    try {
      await api(`/leases/${lease.id}/end`, { method: 'POST' });
      announce('Lease has been ended and the room is now available.');
      await loadData();
    } catch (err: any) {
      announce(err.message || 'Failed to terminate lease.', 'error');
    }
  };

  const availableRooms = rooms.filter((r) => r.status === 'available');
  const activeLeases = leases.filter((l) => l.status === 'active');
  const pastLeases = leases.filter((l) => l.status !== 'active');

  return (
    <div className="leases-page">
      <div className="welcome-block">
        <div>
          <h2>{canManage ? 'Tenants & Occupancy' : 'My Lease Information'}</h2>
          <p className="muted">
            {canManage
              ? 'Track active tenants, room assignments, contract dates, and lease history.'
              : 'Review your current tenancy agreement and contract terms.'}
          </p>
        </div>
        <div className="actions-cluster">
          {canManage && (
            <button type="button" className="button primary" onClick={openCreateModal}>
              + Record New Lease
            </button>
          )}
          <button type="button" className="button secondary" onClick={loadData}>
            Refresh
          </button>
        </div>
      </div>

      {loading ? (
        <LoadingState message="Loading lease agreements…" />
      ) : error ? (
        <ErrorState error={error} retry={loadData} />
      ) : (
        <>
          <Panel
            title={`Active Leases (${activeLeases.length})`}
            description="Currently ongoing tenant contracts and occupied room units."
          >
            {activeLeases.length === 0 ? (
              <EmptyState message="No active leases found." />
            ) : (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Tenant</th>
                      <th>Room</th>
                      <th>Monthly Rent</th>
                      <th>Start Date</th>
                      <th>End Date</th>
                      <th>Status</th>
                      {canManage && <th>Actions</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {activeLeases.map((lease) => (
                      <tr key={lease.id}>
                        <td>
                          <strong>{lease.tenant?.name || 'Tenant'}</strong>
                          <small className="cell-sub">{lease.tenant?.email}</small>
                        </td>
                        <td>
                          Room {lease.room?.room_number}
                          <small className="cell-sub">{lease.room?.title}</small>
                        </td>
                        <td>
                          <strong>{formatMoney(lease.monthly_rent)}</strong>
                        </td>
                        <td>{lease.start_date}</td>
                        <td>{lease.end_date || <span className="muted">Ongoing</span>}</td>
                        <td>
                          <StatusBadge status={lease.status} />
                        </td>
                        {canManage && (
                          <td>
                            <button
                              type="button"
                              className="button danger-outline sm"
                              onClick={() => handleEndLease(lease)}
                            >
                              End Lease
                            </button>
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>

          <Panel
            title={`Past Leases (${pastLeases.length})`}
            description="Historical records of terminated and completed leases."
            className="spaced-panel"
          >
            {pastLeases.length === 0 ? (
              <EmptyState message="No past leases recorded." />
            ) : (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Tenant</th>
                      <th>Room</th>
                      <th>Monthly Rent</th>
                      <th>Start Date</th>
                      <th>End Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pastLeases.map((lease) => (
                      <tr key={lease.id}>
                        <td>
                          <strong>{lease.tenant?.name || 'Tenant'}</strong>
                          <small className="cell-sub">{lease.tenant?.email}</small>
                        </td>
                        <td>
                          Room {lease.room?.room_number} · {lease.room?.title}
                        </td>
                        <td>{formatMoney(lease.monthly_rent)}</td>
                        <td>{lease.start_date}</td>
                        <td>{lease.end_date || 'Terminated'}</td>
                        <td>
                          <StatusBadge status={lease.status} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>
        </>
      )}

      {/* Record Lease Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title="Record New Lease"
        maxWidth="600px"
      >
        <form className="form-grid" onSubmit={handleCreateLease}>
          <Field label="Tenant" required hint="Select an active registered tenant">
            <select
              value={selectedTenantId}
              onChange={(e) => setSelectedTenantId(e.target.value)}
              required
            >
              <option value="" disabled>
                -- Select a Tenant --
              </option>
              {tenants.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name} ({t.email})
                </option>
              ))}
            </select>
          </Field>

          <Field label="Available Room" required hint="Only rooms currently 'available' are listed">
            <select
              value={selectedRoomId}
              onChange={(e) => {
                setSelectedRoomId(e.target.value);
                const selected = availableRooms.find((r) => String(r.id) === e.target.value);
                if (selected && !rentOverride) {
                  setRentOverride(String(selected.price));
                }
              }}
              required
            >
              <option value="" disabled>
                -- Select an Available Room --
              </option>
              {availableRooms.map((r) => (
                <option key={r.id} value={r.id}>
                  Room {r.room_number} · {r.title} ({formatMoney(r.price)}/mo)
                </option>
              ))}
            </select>
          </Field>

          <Field label="Lease Start Date" required>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              required
            />
          </Field>

          <Field label="Lease End Date (optional)" hint="Leave blank for open-ended / month-to-month">
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
            />
          </Field>

          <Field
            label="Monthly Rent (₱)"
            hint="Leave blank to use the standard room price"
          >
            <input
              type="number"
              min="0.01"
              step="0.01"
              value={rentOverride}
              onChange={(e) => setRentOverride(e.target.value)}
              placeholder="e.g. 5000.00"
            />
          </Field>

          <div className="form-actions">
            <button
              type="submit"
              className="button primary"
              disabled={isSaving || !tenants.length || !availableRooms.length}
            >
              {isSaving ? 'Saving…' : 'Create & Activate Lease'}
            </button>
            <button
              type="button"
              className="button secondary"
              onClick={() => setIsModalOpen(false)}
            >
              Cancel
            </button>
          </div>

          {!availableRooms.length && (
            <p className="notice notice-warning">
              ⚠️ There are no rooms marked as 'Available'. Please add or adjust room status first.
            </p>
          )}
        </form>
      </Modal>
    </div>
  );
};

