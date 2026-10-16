import React from 'react';

interface FieldProps {
  label: string;
  children: React.ReactNode;
  hint?: string;
  error?: string;
  required?: boolean;
}

export const Field: React.FC<FieldProps> = ({ label, children, hint, error, required }) => {
  return (
    <label className="field">
      <span className="field-label">
        {label}
        {required && <span className="field-required" aria-hidden="true"> *</span>}
      </span>
      {children}
      {hint && !error && <small className="field-hint">{hint}</small>}
      {error && <small className="field-error" role="alert">{error}</small>}
    </label>
  );
};

