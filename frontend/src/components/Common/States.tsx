import React from 'react';

interface LoadingStateProps {
  message?: string;
}

export const LoadingState: React.FC<LoadingStateProps> = ({ message = 'Loading information…' }) => {
  return (
    <div className="loading-state" role="status">
      <div className="spinner" aria-hidden="true"></div>
      <p>{message}</p>
    </div>
  );
};

interface EmptyStateProps {
  title?: string;
  message?: string;
  action?: React.ReactNode;
  children?: React.ReactNode;
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  title,
  message = 'Nothing to show yet.',
  action,
  children,
}) => {
  return (
    <div className="empty-state">
      {title && <h3>{title}</h3>}
      {children || <p>{message}</p>}
      {action && <div className="empty-action">{action}</div>}
    </div>
  );
};

interface ErrorStateProps {
  error: string;
  retry?: () => void;
}

export const ErrorState: React.FC<ErrorStateProps> = ({ error, retry }) => {
  return (
    <div className="error-state" role="alert">
      <p>{error}</p>
      {retry && (
        <button type="button" className="button secondary" onClick={retry}>
          Try again
        </button>
      )}
    </div>
  );
};

