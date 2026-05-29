function PresencesPage({ user }) {
  const { useState, useEffect } = React;
  const [resume, setResume]       = useState([]);
  const [matieres, setMat]        = useState([]);
  const [selMatiere, setSelMat]   = useState('');
  const [sessions, setSessions]   = useState([]);
  const [session, setSession]     = useState('');
  const [presences, setPres]      = useState([]);
  const [loading, setLoading]     = useState(false);

  useEffect(() => {
    if (user.role === 'etudiant') {
      api.resumeAbsences(user.etudiant_id).then(r => setResume(r.data || []));
    } else {
      api.getMatieres().then(r => setMat(r.data || []));
    }
  }, []);

  useEffect(() => {
    setSession('');
    setPres([]);
    if (selMatiere) {
      api.getSessionsParMatiere(selMatiere).then(r => {
        const list = Array.isArray(r.data) ? r.data : [];
        // Tri par date décroissante (séance la plus récente en premier),
        // puis par heure de début. Les séances sans date passent en dernier.
        list.sort((a, b) => {
          const da = a.date_specifique || '';
          const db = b.date_specifique || '';
          if (da !== db) return db.localeCompare(da);
          return (a.heure_debut || '').localeCompare(b.heure_debut || '');
        });
        setSessions(list);
      });
    } else {
      setSessions([]);
    }
  }, [selMatiere]);

  useEffect(() => {
    if (!session) { setPres([]); return; }
    setLoading(true);
    api.presencesSession(session).then(r => {
      // r.data peut être {} (erreur PHP -> JSON vide) — toujours retomber sur []
      setPres(Array.isArray(r.data) ? r.data : []);
      setLoading(false);
    }).catch(() => {
      setPres([]);
      setLoading(false);
    });
  }, [session]);

  const handleStatut = async (presenceId, inscriptionId, statut) => {
    if (presenceId) await api.modifierPresence(presenceId, statut);
    else await api.enregistrerPresences({ session_id: parseInt(session), presences: [{ inscription_id: inscriptionId, statut }] });
    api.presencesSession(session).then(r => setPres(Array.isArray(r.data) ? r.data : []));
  };

  const statutColors = { present: 'badge-success', absent: 'badge-danger', retard: 'badge-warning', excuse: 'badge-info' };
  const joursAbr = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

  // Formatage compact d'une date YYYY-MM-DD -> "Lun. 25/05/2026"
  const formatDate = (iso) => {
    if (!iso) return '';
    const [y, m, d] = iso.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    return `${joursAbr[(dt.getDay() + 6) % 7]}. ${String(d).padStart(2,'0')}/${String(m).padStart(2,'0')}/${y}`;
  };

  if (user.role === 'etudiant') return (
    <div className="fade-in">
      <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)', marginBottom: 24 }}>Mes présences</h2>
      {resume.length === 0 ? <EmptyState icon="✅" message="Aucune donnée de présence." /> : (
        <div className="card">
          <div className="table-wrapper">
            <table>
              <thead><tr><th>Cours</th><th>Séances</th><th>Présent</th><th>Absent</th><th>Taux absence</th><th>Alerte</th></tr></thead>
              <tbody>
                {resume.map((r, i) => (
                  <tr key={i}>
                    <td><strong>{r.intitule}</strong><br /><small style={{ color: 'var(--text-light)' }}>{r.code}</small></td>
                    <td>{r.total_seances}</td>
                    <td><span className="badge badge-success">{r.presents}</span></td>
                    <td><span className="badge badge-danger">{r.absents}</span></td>
                    <td>
                      <div>{r.taux_absence}%</div>
                      <div className="presence-bar">
                        <div className={`presence-bar-fill ${r.alerte ? 'alert' : 'ok'}`} style={{ width: `${Math.min(r.taux_absence, 100)}%` }}></div>
                      </div>
                    </td>
                    <td>{r.alerte ? <span className="badge badge-danger">⚠️ Alerte</span> : <span className="badge badge-success">OK</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );

  return (
    <div className="fade-in">
      <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)', marginBottom: 24 }}>Gestion des présences</h2>
      <div style={{ display: 'flex', gap: 12, marginBottom: 20, flexWrap: 'wrap' }}>
        <select value={selMatiere} onChange={e => setSelMat(e.target.value)}
          style={{ padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem', minWidth: 260 }}>
          <option value="">— Sélectionner une matière —</option>
          {matieres.map(m => <option key={m.matiere} value={m.matiere}>{m.matiere}</option>)}
        </select>
        {sessions.length > 0 && (
          <select value={session} onChange={e => setSession(e.target.value)}
            style={{ padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem', minWidth: 380 }}>
            <option value="">— Sélectionner une séance ({sessions.length}) —</option>
            {sessions.map(s => (
              <option key={s.id} value={s.id}>
                {s.date_specifique ? formatDate(s.date_specifique) : joursAbr[s.jour_semaine - 1]}
                {' · '}{s.heure_debut?.slice(0,5)}–{s.heure_fin?.slice(0,5)}
                {' · '}{s.cours_code}
                {s.salle_nom ? ` · ${s.salle_nom}` : ''}
              </option>
            ))}
          </select>
        )}
      </div>
      {session && (loading ? <Spinner /> : (
        <div className="card">
          {presences.length === 0 ? <EmptyState icon="👤" message="Aucun étudiant inscrit." /> : (
            <div className="table-wrapper">
              <table>
                <thead><tr><th>Étudiant</th><th>N° Étudiant</th><th>Statut actuel</th><th>Marquer comme</th></tr></thead>
                <tbody>
                  {presences.map(p => (
                    <tr key={p.inscription_id}>
                      <td><strong>{p.etudiant}</strong></td>
                      <td><code style={{ background: 'var(--cream)', padding: '2px 6px', borderRadius: 4, fontSize: '.82rem' }}>{p.numero_etudiant}</code></td>
                      <td>{p.statut ? <span className={`badge ${statutColors[p.statut] || 'badge-navy'}`}>{p.statut}</span> : <em style={{ color: 'var(--text-light)' }}>Non renseigné</em>}</td>
                      <td>
                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                          {['present','absent','retard','excuse'].map(s => (
                            <button key={s} className={`btn btn-sm ${p.statut === s ? 'btn-primary' : 'btn-outline'}`}
                              onClick={() => handleStatut(p.presence_id, p.inscription_id, s)}
                              style={{ fontSize: '.75rem', padding: '4px 10px', textTransform: 'capitalize' }}>
                              {s}
                            </button>
                          ))}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}