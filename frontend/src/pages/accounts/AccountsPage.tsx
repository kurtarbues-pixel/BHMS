import React, { useCallback, useEffect, useState } from 'react';
import { api, fetchAll } from '../../api/client';
import { Role, User, UserFormData } from '../../types';
import { Panel } from '../../components/Common/Panel';
import { Field } from '../../components/Common/Field';
import { Modal } from '../../components/Common/Modal';
import { LoadingState, EmptyState, ErrorState } from '../../components/Common/States';

interface AccountsPageProps {
  announce: (message: string, kind?: 'success' | 'error' | 'warning' | 'info') => void;
}

export const AccountsPage: React.FC<AccountsPageProps> = ({ announce }) => {
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Create user modal
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [name, setName] = useState('');
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [role, setRole] = useState<Role>('tenant');
  const [password, setPassword] = useState('');

  // Password modal
  const [passwordUser, setPasswordUser] = useState<User | null>(null);
  const [newPassword, setNewPassword] = useState('');

  // Email modal
  const [emailUser, setEmailUser] = useState<User | null>(null);
  const [newEmail, setNewEmail] = useState('');

  const loadUsers = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await fetchAll<User>('/admin/users');
      setUsers(data);
    } catch (err: any) {
      setError(err.message || 'Failed to load user accounts.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);

  const handleCreateUser = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);

    const payload: UserFormData = {
      name: name.trim(),
      username: username.trim() || null,
      email: email.trim(),
      phone: phone.trim() || null,
      role,
      password,
    };

    try {
      await api('/admin/users', {
        method: 'POST',
        body: payload,
      });
      announce(`User account for ${name} created successfully.`);
      setIsCreateModalOpen(false);
      setName('');
      setUsername('');
      setEmail('');
      setPhone('');
      setPassword('');
      await loadUsers();
    } catch (err: any) {
      announce(err.message || 'Failed to create user account.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const updateUserField = async (account: User, field: string, value: any) => {
    try {
      await api(`/admin/users/${account.id}`, {
        method: 'PATCH',
        body: { [field]: value },
      });
      announce(`Account updated (${field}).`);
      await loadUsers();
    } catch (err: any) {
      announce(err.message || 'Failed to update account.', 'error');
    }
  };

  const handleSavePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!passwordUser) return;
    if (newPassword.length < 8) {
      announce('Password must be at least 8 characters long.', 'warning');
      return;
    }

    setIsSaving(true);
    try {
      await api(`/admin/users/${passwordUser.id}`, {
        method: 'PATCH',
        body: { password: newPassword },
      });
      announce(`Password reset successfully for ${passwordUser.name}.`);
      setPasswordUser(null);
      setNewPassword('');
    } catch (err: any) {
      announce(err.message || 'Failed to update password.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const handleSaveEmail = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!emailUser) return;

    setIsSaving(true);
    try {
      await api(`/admin/users/${emailUser.id}`, {
        method: 'PATCH',
        body: { email: newEmail.trim() },
      });
      announce(`Email address updated for ${emailUser.name}.`);
      setEmailUser(null);
      setNewEmail('');
      await loadUsers();
    } catch (err: any) {
      announce(err.message || 'Failed to update email.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="accounts-page">
      <div className="welcome-block">
        <div>
          <h2>User Accounts & Permissions</h2>
          <p className="muted">
            Manage system access, assign administrative roles, enable or disable accounts, and reset credentials.
          </p>
        </div>
        <div className="actions-cluster">
          <button
            type="button"
            className="button primary"
            onClick={() => setIsCreateModalOpen(true)}
          >
            + Add New User
          </button>
          <button type="button" className="button secondary" onClick={loadUsers}>
            Refresh
          </button>
        </div>
      </div>

      {loading ? (
        <LoadingState message="Loading user accounts…" />
      ) : error ? (
        <ErrorState error={error} retry={loadUsers} />
      ) : (
        <Panel
          title={`Registered Accounts (${users.length})`}
          description="Administrative control over all system logins."
        >
          {users.length === 0 ? (
            <EmptyState message="No user accounts found." />
          ) : (
            <div className="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {users.map((account) => (
                    <tr key={account.id}>
                      <td>
                        <strong>{account.name}</strong>
                      </td>
                      <td>{account.username || <span className="muted">—</span>}</td>
                      <td>{account.email}</td>
                      <td>{account.phone || <span className="muted">—</span>}</td>
                      <td>
                        <select
                          className="table-select"
                          value={account.role}
                          onChange={(e) =>
                            updateUserField(account, 'role', e.target.value as Role)
                          }
                          aria-label={`Role for ${account.name}`}
                        >
                          <option value="admin">Admin</option>
                          <option value="landlord">Landlord</option>
                          <option value="staff">Staff</option>
                          <option value="tenant">Tenant</option>
                        </select>
                      </td>
                      <td>
                        <button
                          type="button"
                          className={`status-pill-button ${account.is_active ? 'active' : 'inactive'}`}
                          onClick={() =>
                            updateUserField(account, 'is_active', !account.is_active)
                          }
                          title="Click to toggle account access"
                        >
                          {account.is_active ? '● Active' : '○ Disabled'}
                        </button>
                      </td>
                      <td>
                        <div className="inline-action-btns">
                          <button
                            type="button"
                            className="button secondary sm"
                            onClick={() => {
                              setEmailUser(account);
                              setNewEmail(account.email);
                            }}
                          >
                            Email
                          </button>
                          <button
                            type="button"
                            className="button secondary sm"
                            onClick={() => {
                              setPasswordUser(account);
                              setNewPassword('');
                            }}
                          >
                            Password
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>
      )}

      {/* Create User Modal */}
      <Modal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        title="Create New User Account"
        maxWidth="600px"
      >
        <form className="form-grid" onSubmit={handleCreateUser}>
          <Field label="Full Name" required>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Maria Santos"
              required
            />
          </Field>

          <Field label="Username (optional)">
            <input
              type="text"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="e.g. msantos"
              maxLength={50}
            />
          </Field>

          <Field label="Email Address" required>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="name@example.com"
              required
            />
          </Field>

          <Field label="Phone Number">
            <input
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="09171234567"
            />
          </Field>

          <Field label="System Role" required>
            <select
              value={role}
              onChange={(e) => setRole(e.target.value as Role)}
              required
            >
              <option value="tenant">Tenant</option>
              <option value="staff">Staff</option>
              <option value="landlord">Landlord</option>
              <option value="admin">Administrator</option>
            </select>
          </Field>

          <Field label="Initial Password" required hint="At least 8 characters">
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              minLength={8}
              required
            />
          </Field>

          <div className="form-actions span-2">
            <button
              type="submit"
              className="button primary"
              disabled={isSaving}
            >
              {isSaving ? 'Creating…' : 'Create Account'}
            </button>
            <button
              type="button"
              className="button secondary"
              onClick={() => setIsCreateModalOpen(false)}
            >
              Cancel
            </button>
          </div>
        </form>
      </Modal>

      {/* Reset Password Modal */}
      <Modal
        isOpen={Boolean(passwordUser)}
        onClose={() => setPasswordUser(null)}
        title={`Set Password for ${passwordUser?.name}`}
        maxWidth="460px"
      >
        {passwordUser && (
          <form className="form-stack" onSubmit={handleSavePassword}>
            <p className="muted">
              Enter a new password for account <strong>{passwordUser.email}</strong>.
            </p>

            <Field label="New Password" required hint="Minimum 8 characters">
              <input
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                minLength={8}
                placeholder="••••••••"
                required
              />
            </Field>

            <div className="form-actions">
              <button
                type="submit"
                className="button primary"
                disabled={isSaving}
              >
                {isSaving ? 'Updating…' : 'Update Password'}
              </button>
              <button
                type="button"
                className="button secondary"
                onClick={() => setPasswordUser(null)}
              >
                Cancel
              </button>
            </div>
          </form>
        )}
      </Modal>

      {/* Change Email Modal */}
      <Modal
        isOpen={Boolean(emailUser)}
        onClose={() => setEmailUser(null)}
        title={`Change Email for ${emailUser?.name}`}
        maxWidth="460px"
      >
        {emailUser && (
          <form className="form-stack" onSubmit={handleSaveEmail}>
            <Field label="New Email Address" required>
              <input
                type="email"
                value={newEmail}
                onChange={(e) => setNewEmail(e.target.value)}
                required
              />
            </Field>

            <div className="form-actions">
              <button
                type="submit"
                className="button primary"
                disabled={isSaving || newEmail === emailUser.email}
              >
                {isSaving ? 'Saving…' : 'Save Email'}
              </button>
              <button
                type="button"
                className="button secondary"
                onClick={() => setEmailUser(null)}
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

