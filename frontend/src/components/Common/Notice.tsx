import React from 'react';

export interface NoticeData {
  message: string;
  kind?: 'success' | 'error' | 'warning' | 'info';
}

interface NoticeProps {
  notice: NoticeData | null;
  onDismiss: () => void;
}

export const Notice: React.FC<NoticeProps> = ({ notice, onDismiss }) => {
  if (!notice) return null;

  return (
    <div className={`notice notice-${notice.kind || 'info'}`} role="status">
      <span>{notice.message}</span>
      <button
        type="button"
        className="notice-dismiss"
        onClick={onDismiss}
        aria-label="Dismiss message"
      >
        ×
      </button>
    </div>
  );
};

