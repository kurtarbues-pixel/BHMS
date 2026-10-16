import React, { useCallback, useEffect, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { api, fetchAll, formatMoney, getCurrentMonthString } from '../../api/client';
import { Charge, ChargeCategory, Invoice, Lease } from '../../types';
import { Panel } from '../../components/Common/Panel';
import { Field } from '../../components/Common/Field';
import { Modal } from '../../components/Common/Modal';
import { StatusBadge } from '../../components/Common/StatusBadge';
import { LoadingState, EmptyState, ErrorState } from '../../components/Common/States';

interface BillingPageProps {
  announce: (message: string, kind?: 'success' | 'error' | 'warning' | 'info') => void;
}

export const BillingPage: React.FC<BillingPageProps> = ({ announce }) => {
  const { user } = useAuth();
  const canManage = user?.role !== 'tenant';

  const [leases, setLeases] = useState<Lease[]>([]);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Generate invoices state
  const [billingPeriod, setBillingPeriod] = useState<string>(getCurrentMonthString());
  const [generating, setGenerating] = useState(false);

  // Add charge form state
  const [chargeLeaseId, setChargeLeaseId] = useState<string>('');
  const [chargeCategory, setChargeCategory] = useState<ChargeCategory>('CANTEEN');
  const [chargeDescription, setChargeDescription] = useState('');
  const [chargeAmount, setChargeAmount] = useState('');
  const [isAddingCharge, setIsAddingCharge] = useState(false);

  // Record payment modal state
  const [paymentInvoice, setPaymentInvoice] = useState<Invoice | null>(null);
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('cash');
  const [paymentNotes, setPaymentNotes] = useState('');
  const [isRecordingPayment, setIsRecordingPayment] = useState(false);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [leasesData, invoicesData] = await Promise.all([
        fetchAll<Lease>('/leases'),
        fetchAll<Invoice>('/invoices'),
      ]);
      setLeases(leasesData);
      setInvoices(invoicesData);
    } catch (err: any) {
      setError(err.message || 'Failed to load billing records.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleGenerateInvoices = async () => {
    if (!billingPeriod) {
      announce('Please choose a billing month.', 'warning');
      return;
    }

    setGenerating(true);
    try {
      const result = await api<{ period: string; created: number; updated: number }>(
        '/invoices/generate',
        {
          method: 'POST',
          body: { period: billingPeriod },
        }
      );
      announce(
        `Invoices generated for ${result.period}: ${result.created} created, ${result.updated} updated.`
      );
      await loadData();
    } catch (err: any) {
      announce(err.message || 'Invoice generation failed.', 'error');
    } finally {
      setGenerating(false);
    }
  };

  const handleAddCharge = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!chargeLeaseId) {
      announce('Please select an active lease.', 'warning');
      return;
    }

    setIsAddingCharge(true);
    try {
      await api(`/leases/${chargeLeaseId}/charges`, {
        method: 'POST',
        body: {
          category: chargeCategory,
          description: chargeDescription.trim() || undefined,
          amount: Number(chargeAmount),
        },
      });
      announce('Additional charge added to pending ledger.');
      setChargeDescription('');
      setChargeAmount('');
      await loadData();
    } catch (err: any) {
      announce(err.message || 'Failed to record charge.', 'error');
    } finally {
      setIsAddingCharge(false);
    }
  };

  const handleVoidCharge = async (charge: Charge) => {
    if (!window.confirm(`Are you sure you want to remove this pending charge of ${formatMoney(charge.amount)}?`)) {
      return;
    }

    try {
      await api(`/charges/${charge.id}/void`, { method: 'POST' });
      announce('Pending charge removed.');
      await loadData();
    } catch (err: any) {
      announce(err.message || 'Could not void charge.', 'error');
    }
  };

  const openPaymentModal = (invoice: Invoice) => {
    setPaymentInvoice(invoice);
    setPaymentAmount(String(invoice.balance));
    setPaymentMethod('cash');
    setPaymentNotes('');
  };

  const handleRecordPayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!paymentInvoice) return;

    setIsRecordingPayment(true);
    try {
      await api(`/invoices/${paymentInvoice.id}/payments`, {
        method: 'POST',
        body: {
          amount: Number(paymentAmount),
          method: paymentMethod,
          notes: paymentNotes.trim() || undefined,
        },
      });
      announce('Payment recorded successfully!');
      setPaymentInvoice(null);
      await loadData();
    } catch (err: any) {
      announce(err.message || 'Failed to record payment.', 'error');
    } finally {
      setIsRecordingPayment(false);
    }
  };

  const activeLeases = leases.filter((l) => l.status === 'active');

  const pendingCharges = leases.flatMap((lease) =>
    (lease.charges || [])
      .filter((charge) => charge.status?.toLowerCase() === 'pending')
      .map((charge) => ({ ...charge, lease }))
  );

  return (
    <div className="billing-page">
      <div className="welcome-block">
        <div>
          <h2>Charges & Invoices</h2>
          <p className="muted">
            {canManage
              ? 'Record incidentals, generate monthly rent invoices, and track payments.'
              : 'Review your monthly rent statements, itemized utility charges, and payment receipts.'}
          </p>
        </div>
        <button type="button" className="button secondary" onClick={loadData}>
          Refresh
        </button>
      </div>

      {canManage && (
        <div className="billing-tools-grid">
          {/* Add Pending Charge Panel */}
          <Panel
            title="Record Pending Charge"
            description="Charges added here will automatically roll into the tenant's next generated invoice."
          >
            <form className="form-grid" onSubmit={handleAddCharge}>
              <Field label="Active Lease" required>
                <select
                  value={chargeLeaseId}
                  onChange={(e) => setChargeLeaseId(e.target.value)}
                  required
                >
                  <option value="" disabled>
                    -- Select Lease / Tenant --
                  </option>
                  {activeLeases.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.tenant?.name} · Room {l.room?.room_number}
                    </option>
                  ))}
                </select>
              </Field>

              <Field label="Charge Category" required>
                <select
                  value={chargeCategory}
                  onChange={(e) => setChargeCategory(e.target.value as ChargeCategory)}
                >
                  <option value="CANTEEN">Canteen / Food</option>
                  <option value="APPLIANCE">Appliance / Electricity</option>
                  <option value="OTHER">Other Miscellaneous</option>
                </select>
              </Field>

              <Field label="Description (optional)">
                <input
                  type="text"
                  value={chargeDescription}
                  onChange={(e) => setChargeDescription(e.target.value)}
                  placeholder="e.g. Rice meal or Rice cooker usage"
                  maxLength={255}
                />
              </Field>

              <Field label="Amount (₱)" required>
                <input
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={chargeAmount}
                  onChange={(e) => setChargeAmount(e.target.value)}
                  placeholder="0.00"
                  required
                />
              </Field>

              <div className="form-actions span-2">
                <button
                  type="submit"
                  className="button primary"
                  disabled={isAddingCharge || !activeLeases.length}
                >
                  {isAddingCharge ? 'Saving…' : 'Record Charge'}
                </button>
              </div>
            </form>
          </Panel>

          {/* Generate Invoices Tool */}
          <Panel
            title="Generate Monthly Invoices"
            description="Create or update billing statements for all active leases for a given month."
          >
            <div className="generate-tool-box">
              <Field label="Billing Month" required>
                <input
                  type="month"
                  value={billingPeriod}
                  onChange={(e) => setBillingPeriod(e.target.value)}
                />
              </Field>
              <button
                type="button"
                className="button primary"
                disabled={generating || !billingPeriod}
                onClick={handleGenerateInvoices}
              >
                {generating ? 'Processing…' : 'Generate Statements'}
              </button>
            </div>
            <p className="hint-text">
              Any pending canteen or appliance charges for active leases will be attached into this month's invoice.
            </p>
          </Panel>
        </div>
      )}

      {loading ? (
        <LoadingState message="Loading invoices and billing statements…" />
      ) : error ? (
        <ErrorState error={error} retry={loadData} />
      ) : (
        <>
          {canManage && (
            <Panel
              title={`Pending Unbilled Charges (${pendingCharges.length})`}
              description="Charges awaiting inclusion in the next generated monthly statement."
            >
              {pendingCharges.length === 0 ? (
                <EmptyState message="No pending unbilled charges." />
              ) : (
                <div className="table-scroll">
                  <table>
                    <thead>
                      <tr>
                        <th>Tenant</th>
                        <th>Room</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {pendingCharges.map((charge) => (
                        <tr key={charge.id}>
                          <td>
                            <strong>{charge.lease?.tenant?.name}</strong>
                          </td>
                          <td>Room {charge.lease?.room?.room_number}</td>
                          <td>
                            <span className="category-tag">{charge.category}</span>
                          </td>
                          <td>{charge.description || 'Additional charge'}</td>
                          <td>
                            <strong>{formatMoney(charge.amount)}</strong>
                          </td>
                          <td>
                            <button
                              type="button"
                              className="button danger-outline sm"
                              onClick={() => handleVoidCharge(charge)}
                            >
                              Void
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Panel>
          )}

          <Panel
            title={`Billing Statements & Invoices (${invoices.length})`}
            description="Invoices issued to tenants with tracking of balances and payment statuses."
            className="spaced-panel"
          >
            {invoices.length === 0 ? (
              <EmptyState message="No invoices generated yet." />
            ) : (
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Tenant</th>
                      <th>Room</th>
                      <th>Billing Period</th>
                      <th>Base Rent</th>
                      <th>Other Charges</th>
                      <th>Total Due</th>
                      <th>Paid</th>
                      <th>Balance</th>
                      <th>Due Date</th>
                      <th>Status</th>
                      {canManage && <th>Payment</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {invoices.map((invoice) => {
                      const isPaid = invoice.status.toLowerCase() === 'paid';
                      return (
                        <tr key={invoice.id}>
                          <td>
                            <strong>{invoice.lease?.tenant?.name || 'Tenant'}</strong>
                          </td>
                          <td>Room {invoice.lease?.room?.room_number}</td>
                          <td>
                            <span className="period-badge">{invoice.billing_period}</span>
                          </td>
                          <td>{formatMoney(invoice.base_rent)}</td>
                          <td>{formatMoney(invoice.additional_charges_total)}</td>
                          <td>
                            <strong>{formatMoney(invoice.total_amount_due)}</strong>
                          </td>
                          <td className="text-success">{formatMoney(invoice.amount_paid)}</td>
                          <td>
                            <strong className={Number(invoice.balance) > 0 ? 'text-danger' : 'text-success'}>
                              {formatMoney(invoice.balance)}
                            </strong>
                          </td>
                          <td>{invoice.due_date}</td>
                          <td>
                            <StatusBadge status={invoice.status} />
                          </td>
                          {canManage && (
                            <td>
                              {!isPaid ? (
                                <button
                                  type="button"
                                  className="button secondary sm"
                                  onClick={() => openPaymentModal(invoice)}
                                >
                                  Record Payment
                                </button>
                              ) : (
                                <span className="paid-check">✓ Settled</span>
                              )}
                            </td>
                          )}
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>
        </>
      )}

      {/* Record Payment Modal */}
      <Modal
        isOpen={Boolean(paymentInvoice)}
        onClose={() => setPaymentInvoice(null)}
        title="Record Payment"
        maxWidth="500px"
      >
        {paymentInvoice && (
          <form className="form-stack" onSubmit={handleRecordPayment}>
            <div className="payment-summary-box">
              <p>
                <strong>Tenant:</strong> {paymentInvoice.lease?.tenant?.name}
              </p>
              <p>
                <strong>Room:</strong> Room {paymentInvoice.lease?.room?.room_number}
              </p>
              <p>
                <strong>Period:</strong> {paymentInvoice.billing_period}
              </p>
              <p>
                <strong>Outstanding Balance:</strong>{' '}
                <span className="balance-highlight">{formatMoney(paymentInvoice.balance)}</span>
              </p>
            </div>

            <Field label="Payment Amount (₱)" required>
              <input
                type="number"
                min="0.01"
                max={Number(paymentInvoice.balance)}
                step="0.01"
                value={paymentAmount}
                onChange={(e) => setPaymentAmount(e.target.value)}
                required
              />
            </Field>

            <Field label="Payment Method" required>
              <select
                value={paymentMethod}
                onChange={(e) => setPaymentMethod(e.target.value)}
              >
                <option value="cash">Cash</option>
                <option value="bank transfer">Bank Transfer</option>
                <option value="e-wallet">GCash / Maya / E-Wallet</option>
                <option value="other">Other</option>
              </select>
            </Field>

            <Field label="Reference / Notes">
              <input
                type="text"
                value={paymentNotes}
                onChange={(e) => setPaymentNotes(e.target.value)}
                placeholder="Receipt #, transaction ref, or notes"
                maxLength={255}
              />
            </Field>

            <div className="form-actions">
              <button
                type="submit"
                className="button primary"
                disabled={isRecordingPayment}
              >
                {isRecordingPayment ? 'Saving…' : 'Confirm & Save Payment'}
              </button>
              <button
                type="button"
                className="button secondary"
                onClick={() => setPaymentInvoice(null)}
              >
                Cancel
              </button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
};

