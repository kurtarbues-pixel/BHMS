import React from 'react';

interface PanelProps {
  title: string;
  action?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
  description?: string;
}

export const Panel: React.FC<PanelProps> = ({
  title,
  action,
  children,
  className = '',
  description,
}) => {
  return (
    <section className={`panel ${className}`}>
      <div className="panel-heading">
        <div>
          <h2>{title}</h2>
          {description && <p className="panel-desc">{description}</p>}
        </div>
        {action && <div className="panel-actions">{action}</div>}
      </div>
      <div className="panel-body">{children}</div>
    </section>
  );
};

