function Topbar({ title, unreadNotifs, notifs, onMarkAllRead }) {
  const { useState, useEffect, useRef } = React;
  const [showNotifs, setShowNotifs] = useState(false);
  const ref = useRef();

  useEffect(() => {
    const handler = e => {
      if (ref.current && !ref.current.contains(e.target)) setShowNotifs(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const formatDate = iso => iso
    ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
    : '';

  return (
    <div className="topbar">
      <span className="topbar-title">{title}</span>
      <div className="topbar-actions">
        <div style={{ position: 'relative' }} ref={ref}>
          <button
            className="btn btn-outline btn-sm"
            onClick={() => setShowNotifs(s => !s)}
            style={{ position: 'relative' }}
          >
            <Icons.Bell />
            {unreadNotifs > 0 && (
              <span style={{
                position: 'absolute', top: -6, right: -6,
                background: 'var(--danger)', color: 'white',
                borderRadius: '50%', width: 18, height: 18,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 11, fontWeight: 700
              }}>
                {unreadNotifs > 9 ? '9+' : unreadNotifs}
              </span>
            )}
          </button>

          {showNotifs && (
            <div className="notif-panel">
              <div style={{
                padding: '10px 16px',
                borderBottom: '1px solid var(--cream-dark)',
                display: 'flex', justifyContent: 'space-between', alignItems: 'center'
              }}>
                <strong style={{ fontSize: '.85rem' }}>Notifications</strong>
                {unreadNotifs > 0 && (
                  <button
                    className="btn btn-sm"
                    style={{ fontSize: '.75rem', padding: '3px 8px' }}
                    onClick={() => { onMarkAllRead(); setShowNotifs(false); }}
                  >
                    Tout lire
                  </button>
                )}
              </div>
              {notifs.length === 0
                ? <div style={{ padding: '20px', textAlign: 'center', color: 'var(--text-light)', fontSize: '.85rem' }}>
                    Aucune notification
                  </div>
                : notifs.slice(0, 10).map(n => (
                  <div key={n.id} className={`notif-item ${!n.lue ? 'unread' : ''}`}>
                    <div className="notif-type">{n.type.replace(/_/g, ' ')}</div>
                    <div className="notif-content">{n.contenu}</div>
                    <div className="notif-date">{formatDate(n.date_creation)}</div>
                  </div>
                ))
              }
            </div>
          )}
        </div>
      </div>
    </div>
  );
}