function EmploiDuTempsPage({ user }) {
  const { useState, useEffect } = React;
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading]   = useState(true);

  useEffect(() => {
    api.getEmploiDuTemps().then(r => { setSessions(r.data || []); setLoading(false); });
  }, []);

  const jours  = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi'];
  const heures = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'];
  const colors = ['#1b2d42','#2a4060','#1e5fa8','#2d7a4f','#c07a1a'];

  const getCellSessions = (jourIdx, heure) =>
    sessions.filter(s => parseInt(s.jour_semaine) - 1 === jourIdx && s.heure_debut <= heure && s.heure_fin > heure);

  if (loading) return <Spinner />;

  return (
    <div className="fade-in">
      <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)', marginBottom: 24 }}>Emploi du temps</h2>
      {sessions.length === 0 ? <EmptyState icon="📅" message="Aucune séance planifiée." /> : (
        <div style={{ overflowX: 'auto' }}>
          <div className="edt-grid">
            <div className="edt-header"></div>
            {jours.map(j => <div key={j} className="edt-header">{j}</div>)}
            {heures.map(h => (
              <React.Fragment key={h}>
                <div className="edt-time-col">{h}</div>
                {jours.map((j, ji) => {
                  const cells = getCellSessions(ji, h);
                  return (
                    <div key={j} className="edt-cell">
                      {cells.map((s, i) => (
                        <div key={s.session_id} className="edt-event" style={{ background: colors[i % colors.length] }}>
                          <strong>{s.cours}</strong>
                          <small>{s.heure_debut}–{s.heure_fin}</small>
                          {s.salle && <small> · {s.salle}</small>}
                          {s.enseignant && <small> · {s.enseignant}</small>}
                        </div>
                      ))}
                    </div>
                  );
                })}
              </React.Fragment>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}