import React, { useCallback, useEffect, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { api, fetchAll, formatMoney } from '../../api/client';
import { Inquiry } from '../../types';
import { StatusBadge } from '../../components/Common/StatusBadge';
import { LoadingState, EmptyState, ErrorState } from '../../components/Common/States';

interface InquiriesPageProps {
  announce: (message: string, kind?: 'success' | 'error' | 'warning' | 'info') => void;
}

export const InquiriesPage: React.FC<InquiriesPageProps> = ({ announce }) => {
  const { user } = useAuth();
  const canManage = user?.role !== 'tenant';

  const [inquiries, setInquiries] = useState<Inquiry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [processingId, setProcessingId] = useState<number | null>(null);

  const loadInquiries = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await fetchAll<Inquiry>('/inquiries');
      setInquiries(data);
    } catch (err: any) {
      setError(err.message || 'Failed to load room requests.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadInquiries();
  }, [loadInquiries]);

  const handleDecision = async (inquiry: Inquiry, action: 'approve' | 'reject') => {
    const isApprove = action === 'approve';
    const confirmMessage = isApprove
      ? `Approve request from ${inquiry.tenant?.name || 'tenant'} for Room ${inquiry.room?.room_number}? This will automatically create an active lease!`
      : `Decline request from ${inquiry.tenant?.name || 'tenant'}?`;

    if (!window.confirm(confirmMessage)) return;

    setProcessingId(inquiry.id);
    try {
      await api(`/inquiries/${inquiry.id}/${action}`, { method: 'POST' });
      announce(
        isApprove
          ? 'Request approved! Lease created and room marked occupied.'
          : 'Request has been declined.'
      );
      await loadInquiries();
    } catch (err: any) {
      announce(err.message || 'Action could not be completed.', 'error');
    } finally {
      setProcessingId(null);
    }
  };

  return (
    <div className="inquiries-page">
      <div className="welcome-block">
        <div>
          <h2>{canManage ? 'Room Requests' : 'My Rental Requests'}</h2>
          <p className="muted">
            {canManage
              ? 'Review inquiries from prospective tenants. Approving a request instantly initiates an active lease.'
              : 'Track the status and landlord responses to your submitted room rental inquiries.'}
          </p>
        </div>
        <button type="button" className="button secondary" onClick={loadInquiries}>
          Refresh
        </button>
      </div>

      {loading ? (
        <LoadingState message="Loading requests…" />
      ) : error ? (
        <ErrorState error={error} retry={loadInquiries} />
      ) : inquiries.length === 0 ? (
        <EmptyState
          title="No room requests"
          message={
            canManage
              ? 'There are currently no incoming rental requests from tenants.'
              : "You haven't submitted any room requests yet. Check the Rooms page to find an available room!"
          }
        />
      ) : (
        <div className="request-list">
          {inquiries.map((inquiry) => {
            const isPending = inquiry.status.toLowerCase() === 'pending';
            const isProcessing = processingId === inquiry.id;

            return (
              <article key={inquiry.id} className="request-card">
                <div className="request-card-info">
                  <div className="row-heading">
                    <h3>
                      {inquiry.room?.title || `Room ${inquiry.room?.room_number}`}
                    </h3>
                    <StatusBadge status={inquiry.status} />
                  </div>

                  <p className="muted">
                    Room {inquiry.room?.room_number} · {inquiry.room?.location} ·{' '}
                    <strong>{formatMoney(inquiry.room?.price)}</strong> / month
                  </p>

                  {inquiry.message && (
                    <div className="inquiry-message">
                      <p>"{inquiry.message}"</p>
                    </div>
                  )}

                  <div className="inquiry-meta">
                    <small>
                      <strong>Tenant:</strong> {inquiry.tenant?.name} ({inquiry.tenant?.email}
                      {inquiry.tenant?.phone ? ` · ${inquiry.tenant.phone}` : ''})
                    </small>
                    {inquiry.created_at && (
                      <small className="date-sub">
                        Submitted: {new Date(inquiry.created_at).toLocaleDateString()}
                      </small>
                    )}
                  </div>
                </div>

                {canManage && isPending && (
                  <div className="inline-actions">
                    <button
                      type="button"
                      className="button primary"
                      disabled={isProcessing}
                      onClick={() => handleDecision(inquiry, 'approve')}
                    >
                      {isProcessing ? 'Processing…' : 'Approve & Create Lease'}
                    </button>
                    <button
                      type="button"
                      className="button secondary"
                      disabled={isProcessing}
                      onClick={() => handleDecision(inquiry, 'reject')}
                    >
                      Decline
                    </button>
                  </div>
                )}
              </article>
            );
          })}
        </div>
      )}
    </div>
  );
};

