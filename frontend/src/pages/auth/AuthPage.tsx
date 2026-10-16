import React, { useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { Field } from '../../components/Common/Field';
import { ThemeToggle } from '../../components/Common/ThemeToggle';

interface AuthPageProps {
  onSuccessNotice?: (message: string) => void;
}

export const AuthPage: React.FC<AuthPageProps> = ({ onSuccessNotice }) => {
  const { login, register } = useAuth();
  const [isRegistering, setIsRegistering] = useState(false);
  const [error, setError] = useState<string>('');
  const [busy, setBusy] = useState(false);

  // Form states
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [identifier, setIdentifier] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setBusy(true);

    try {
      if (isRegistering) {
        await register({
          name: name.trim(),
          email: email.trim(),
          phone: phone.trim() || undefined,
          password,
          password_confirmation: password,
          role: 'tenant',
        });
        onSuccessNotice?.('Account successfully created! You are now logged in.');
      } else {
        await login({
          identifier: identifier.trim(),
          password,
        });
        onSuccessNotice?.('Signed in successfully.');
      }
    } catch (err: any) {
      setError(err.message || 'Authentication failed. Please check your credentials.');
    } finally {
      setBusy(false);
    }
  };

  const toggleMode = () => {
    setIsRegistering((prev) => !prev);
    setError('');
  };

  return (
    <main className="auth-page">
      <div className="auth-theme-wrapper">
        <ThemeToggle showLabel={true} />
      </div>

      <section className="auth-card" aria-labelledby="auth-heading">
        <div className="brand-mark auth-mark" aria-hidden="true">
          BH
        </div>
        <p className="eyebrow">BOARDING HOUSE MANAGEMENT</p>
        <h1 id="auth-heading">
          {isRegistering ? 'Create your tenant account' : 'Welcome back'}
        </h1>
        <p className="muted">
          {isRegistering
            ? 'Register to browse rooms, send rental requests, and track your invoices.'
            : 'Sign in to access your boarding house account.'}
        </p>

        {error && (
          <div className="notice notice-error" role="alert">
            <span>{error}</span>
          </div>
        )}

        <form className="form-stack" onSubmit={handleSubmit}>
          {isRegistering ? (
            <>
              <Field label="Full name" required>
                <input
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  autoComplete="name"
                  placeholder="e.g. Juan dela Cruz"
                  required
                  maxLength={255}
                />
              </Field>

              <Field label="Phone number">
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  autoComplete="tel"
                  placeholder="e.g. 09171234567"
                  maxLength={25}
                />
              </Field>

              <Field label="Email address" required>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  autoComplete="email"
                  placeholder="name@example.com"
                  required
                />
              </Field>
            </>
          ) : (
            <Field label="Email address or username" required>
              <input
                type="text"
                value={identifier}
                onChange={(e) => setIdentifier(e.target.value)}
                autoComplete="username"
                placeholder="Enter email or username"
                required
              />
            </Field>
          )}

          <Field
            label="Password"
            hint={isRegistering ? 'Must be at least 8 characters' : undefined}
            required
          >
            <div className="password-input-wrap">
              <input
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete={isRegistering ? 'new-password' : 'current-password'}
                minLength={8}
                placeholder="Enter password"
                required
              />
              <button
                type="button"
                className="password-toggle-btn"
                onClick={() => setShowPassword((prev) => !prev)}
                title={showPassword ? 'Hide password' : 'Show password'}
                aria-label={showPassword ? 'Hide password' : 'Show password'}
              >
                {showPassword ? (
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                    <line x1="2" x2="22" y1="2" y2="22" />
                  </svg>
                ) : (
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                )}
              </button>
            </div>
          </Field>

          <button
            type="submit"
            className="button primary full"
            disabled={busy}
          >
            {busy ? 'Please wait…' : isRegistering ? 'Create Account' : 'Sign In'}
          </button>
        </form>

        <p className="auth-switch">
          {isRegistering ? 'Already registered?' : 'Tenant without an account?'}{' '}
          <button type="button" onClick={toggleMode} className="switch-btn">
            {isRegistering ? 'Sign in here' : 'Create tenant account'}
          </button>
        </p>
      </section>
    </main>
  );
};

