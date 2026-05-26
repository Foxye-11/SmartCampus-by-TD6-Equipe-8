function Dashboard({ user }) {
  const { useState, useEffect } = React;
  const [stats, setStats]     = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      try {
        if (user.role === 'etudiant') {
          const [coursSuivis, notifs, absences] = await Promise.all([
            api.coursSuivis(user.etudiant_id),
            api.getNotifications(),
            api.resumeAbsences(user.etudiant_id),
          ]);
          setStats({
            type:         'etudiant',
            cours:        coursSuivis.data?.length || 0,
            notifs:       notifs.data?.filter(n => !n.lue).length || 0,
            absences:     absences.data?.reduce((s, c) => s + Number(c.absents || 0), 0) || 0,
            alertes:      absences.data?.filter(c => c.alerte).length || 0,
            recentNotifs: notifs.data?.slice(0, 4) || [],
          });
        } else if (user.role === 'enseignant') {
          const [cours, notifs] = await Promise.all([
            api.coursEnseignant(user.enseignant_id),
            api.getNotifications(),
          ]);
          setStats({
            type:         'enseignant',
            cours:        cours.data?.length || 0,
            inscrits:     cours.data?.reduce((s, c) => s + Number(c.inscrits || 0), 0) || 0,
            notifs:       notifs.data?.filter(n => !n.lue).length || 0,
            recentNotifs: notifs.data?.slice(0, 4) || [],
          });
        } else {
          const [etudiants, enseignants, cours, notifs] = await Promise.all([
            api.getEtudiants(),
            api.getEnseignants(),
            api.getCours(),
            api.getNotifications(),
          ]);
          setStats({
            type:         'admin',
            etudiants:    etudiants.data?.length || 0,
            enseignants:  enseignants.data?.length || 0,
            cours:        cours.data?.length || 0,
            notifs:       notifs.data?.filter(n => !n.lue).length || 0,
            recentNotifs: notifs.data?.slice(0, 4) || [],
          });
        }
      } catch (e) { console.error(e); }
      setLoading(false);
    };
    load();
  }, [user]);

  if (loading) return <Spinner />;
  if (!stats)  return <EmptyState message="Impossible de charger le tableau de bord." />;

  const notifIcons = {
    note_publiee: '🎓', nouveau_message: '✉️',
    alerte_absence: '⚠️', nouveau_inscrit: '📋', default: '🔔'
  };

  return (
    <div className="fade-in">
      <div style={{ marginBottom: 24 }}>
        <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.5rem', color: 'var(--navy)' }}>
          Bonjour, {user.prenom} 👋
        </h2>
        <p style={{ color: 'var(--text-mid)', marginTop: 4 }}>Voici un aperçu de votre espace.</p>
      </div>

      <div className="stat-grid">
        {stats.type === 'etudiant' && <>
          <div className="stat-card gold">
            <div className="stat-label">Cours suivis</div>
            <div className="stat-value">{stats.cours}</div>
          </div>
          <div className="stat-card">
            <div className="stat-label">Absences</div>
            <div className="stat-value">{stats.absences}</div>
          </div>
          <div className={`stat-card ${stats.alertes > 0 ? 'red' : 'green'}`}>
            <div className="stat-label">Alertes absences</div>
            <div className="stat-value">{stats.alertes}</div>
            <div className="stat-sub">cours en alerte</div>
          </div>
          <div className="stat-card">
            <div className="stat-label">Notifications</div>
            <div className="stat-value">{stats.notifs}</div>
            <div className="stat-sub">non lues</div>
          </div>
        </>}

        {stats.type === 'enseignant' && <>
          <div className="stat-card gold">
            <div className="stat-label">Mes cours</div>
            <div className="stat-value">{stats.cours}</div>
          </div>
          <div className="stat-card">
            <div className="stat-label">Étudiants inscrits</div>
            <div className="stat-value">{stats.inscrits}</div>
            <div className="stat-sub">total</div>
          </div>
          <div className="stat-card">
            <div className="stat-label">Notifications</div>
            <div className="stat-value">{stats.notifs}</div>
            <div className="stat-sub">non lues</div>
          </div>
        </>}

        {stats.type === 'admin' && <>
          <div className="stat-card gold">
            <div className="stat-label">Étudiants</div>
            <div className="stat-value">{stats.etudiants}</div>
          </div>
          <div className="stat-card">
            <div className="stat-label">Enseignants</div>
            <div className="stat-value">{stats.enseignants}</div>
          </div>
          <div className="stat-card green">
            <div className="stat-label">Cours actifs</div>
            <div className="stat-value">{stats.cours}</div>
          </div>
          <div className="stat-card">
            <div className="stat-label">Notifications</div>
            <div className="stat-value">{stats.notifs}</div>
            <div className="stat-sub">non lues</div>
          </div>
        </>}
      </div>

      {stats.recentNotifs.length > 0 && (
        <div className="card">
          <div className="card-header">
            <span className="card-title">Notifications récentes</span>
          </div>
          <div>
            {stats.recentNotifs.map(n => (
              <div key={n.id} style={{
                padding: '12px 22px', borderBottom: '1px solid var(--cream-dark)',
                display: 'flex', gap: 12, alignItems: 'flex-start'
              }}>
                <span style={{ fontSize: '1.2rem' }}>{notifIcons[n.type] || notifIcons.default}</span>
                <div>
                  <div style={{ fontSize: '.88rem', color: 'var(--text-dark)' }}>{n.contenu}</div>
                  <div style={{ fontSize: '.75rem', color: 'var(--text-light)', marginTop: 2 }}>
                    {new Date(n.date_creation).toLocaleDateString('fr-FR')}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}