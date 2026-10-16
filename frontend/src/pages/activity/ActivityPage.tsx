import React, { useCallback, useEffect, useState } from 'react';
import { fetchAll } from '../../api/client';
import { ActivityLog } from '../../types';
import { Panel } from '../../components/Common/Panel';
import { LoadingState, EmptyState, ErrorState } from '../../components/Common/States';

export const ActivityPage: React.FC = () => {
  const [logs, setLogs] = useState<ActivityLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');

  const loadLogs = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await fetchAll<ActivityLog>('/admin/activity-logs');
      setLogs(data);
    } catch (err: any) {
      setError(err.message || 'Failed to retrieve activity audit trail.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadLogs();
  }, [loadLogs]);

  const filteredLogs = logs.filter((log) => {
    if (!searchTerm) return true;
    const term = searchTerm.toLowerCase();
    const actionMatch = log.action?.toLowerCase().includes(term);
    const userMatch = log.user?.name?.toLowerCase().includes(term) || log.user?.email?.toLowerCase().includes(term);
    const subjectMatch = log.subject_type?.toLowerCase().includes(term);
    return actionMatch || userMatch || subjectMatch;
  });

  return (
    <div className="activity-page">
      <div className="welcome-block">
        <div>
          <h2>System Activity & Audit Log</h2>
          <p className="muted">
            Track user logins, room adjustments, lease records, payment receipts, and administrative changes.
          </p>
        </div>
        <button type="button" className="button secondary" onClick={loadLogs}>
          Refresh Logs
        </button>
      </div>

      <div className="search-bar">
        <input
          type="search"
          placeholder="Search activity by action, user, or record…"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="search-input"
        />
        {searchTerm && (
          <button
            type="button"
            className="clear-search-btn"
            onClick={() => setSearchTerm('')}
          >
            Clear
          </button>
        )}
      </div>

      {loading ? (
        <LoadingState message="Loading audit trail…" />
      ) : error ? (
        <ErrorState error={error} retry={loadLogs} />
      ) : (
        <Panel
          title={`Recent Events (${filteredLogs.length})`}
          description="Chronological log of system actions and events."
        >
          {filteredLogs.length === 0 ? (
            <EmptyState
              message={
                searchTerm
                  ? `No activity events match "${searchTerm}".`
                  : 'No system activity has been logged yet.'
              }
            />
          ) : (
            <div className="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th>Timestamp</th>
                    <th>User / Account</th>
                    <th>Action</th>
                    <th>Affected Entity</th>
                    <th>Event Details</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredLogs.map((log) => {
                    const subjectSimple = log.subject_type
                      ? `${log.subject_type.split('\\').pop()} #${log.subject_id}`
                      : '—';

                    return (
                      <tr key={log.id}>
                        <td className="timestamp-cell">
                          {new Date(log.created_at).toLocaleString()}
                        </td>
                        <td>
                          <strong>{log.user?.name || 'System / Guest'}</strong>
                          {log.user?.email && (
                            <small className="cell-sub">{log.user.email}</small>
                          )}
                        </td>
                        <td>
                          <span className="action-tag">{log.action}</span>
                        </td>
                        <td>{subjectSimple}</td>
                        <td className="details-cell">
                          {log.properties ? (
                            <code className="json-code">
                              {JSON.stringify(log.properties)}
                            </code>
                          ) : (
                            <span className="muted">—</span>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Panel>
      )}
    </div>
  );
};

