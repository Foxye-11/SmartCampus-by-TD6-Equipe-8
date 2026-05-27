function EmploiDuTempsPage({ user }) {
  const { useState, useEffect } = React;
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading]   = useState(true);
  const isAdmin = user.role === 'admin';

  // Listes pour les filtres (admin uniquement)
  const [enseignants, setEns] = useState([]);
  const [etudiants, setEtus]  = useState([]);
  const [coursList, setCours] = useState([]);

  // Type de filtre par personne : 'prof' ou 'eleve'
  const [typePersonne, setTypePersonne] = useState('prof');
  const [filtrePersonne, setFiltrePers] = useState(''); // id enseignant ou étudiant
  const [filtreCours, setFiltreCours]   = useState(''); // matière

  const load = async () => {
    setLoading(true);
    const params = {};
    if (isAdmin) {
      if (filtreCours) params.cours_id = filtreCours;
      if (filtrePersonne) {
        if (typePersonne === 'prof') params.enseignant_id = filtrePersonne;
        else params.etudiant_id = filtrePersonne;
      }
    }
    const r = await api.getEmploiDuTemps(params);
    setSessions(r.data || []);
    setLoading(false);
  };

  // Chargement initial des référentiels de filtre (admin)
  useEffect(() => {
    if (!isAdmin) return;
    Promise.all([api.getEnseignants(), api.getEtudiants({}), api.getCours({})]).then(([e, et, c]) => {
      setEns(e.data || []); setEtus(et.data || []); setCours(c.data || []);
    });
  }, []);

  useEffect(() => { load(); }, [filtreCours, filtrePersonne, typePersonne]);

  const jours  = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi'];
  const heures = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'];
  const colors = ['#1b2d42','#2a4060','#1e5fa8','#2d7a4f','#c07a1a'];

  const getCellSessions = (jourIdx, heure) =>
    sessions.filter(s => parseInt(s.jour_semaine) - 1 === jourIdx && s.heure_debut <= heure && s.heure_fin > heure);

  const selectStyle = { padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem' };

  const resetFiltres = () => { setFiltrePers(''); setFiltreCours(''); };

  return (
    <div className="fade-in">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 16, flexWrap: 'wrap', gap: 12 }}>
        <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Emploi du temps</h2>
      </div>

      {isAdmin && (
        <div className="card" style={{ marginBottom: 16 }}>
          <div className="card-header" style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
            {/* Filtre par personne : prof ou élève */}
            <select value={typePersonne} onChange={e => { setTypePersonne(e.target.value); setFiltrePers(''); }} style={selectStyle}>
              <option value="prof">Filtrer par enseignant</option>
              <option value="eleve">Filtrer par étudiant</option>
            </select>
            {typePersonne === 'prof' ? (
              <select value={filtrePersonne} onChange={e => setFiltrePers(e.target.value)} style={selectStyle}>
                <option value="">Tous les enseignants</option>
                {enseignants.map(e => <option key={e.id} value={e.id}>{e.prenom} {e.nom}</option>)}
              </select>
            ) : (
              <select value={filtrePersonne} onChange={e => setFiltrePers(e.target.value)} style={selectStyle}>
                <option value="">Tous les étudiants</option>
                {etudiants.map(e => <option key={e.id} value={e.id}>{e.prenom} {e.nom} ({e.numero_etudiant})</option>)}
              </select>
            )}
            {/* Filtre par matière */}
            <select value={filtreCours} onChange={e => setFiltreCours(e.target.value)} style={selectStyle}>
              <option value="">Toutes les matières</option>
              {coursList.map(c => <option key={c.id} value={c.id}>{c.code} — {c.intitule}</option>)}
            </select>
            {(filtrePersonne || filtreCours) && (
              <button className="btn btn-outline btn-sm" onClick={resetFiltres}>Réinitialiser</button>
            )}
          </div>
        </div>
      )}

      {loading ? <Spinner /> : sessions.length === 0 ? <EmptyState icon="📅" message="Aucune séance planifiée." /> : (
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
