function EmploiDuTempsPage({ user }) {
  const { useState, useEffect, useMemo } = React;
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading]   = useState(true);
  const isAdmin = user.role === 'admin';

  // ─── Sélecteur d'année scolaire (seule 2025-2026 disponible) ─────────
  const [anneesDispo, setAnneesDispo] = useState(['2025-2026']);
  const [annee, setAnnee]             = useState('2025-2026');

  // ─── Date pivot (n'importe quelle date dans la semaine affichée) ──────
  const [currentDate, setCurrentDate] = useState(() => {
    const t = new Date();
    if (t < new Date(2025, 8, 1) || t > new Date(2026, 5, 30)) {
      return new Date(2025, 8, 1);
    }
    return t;
  });

  // ─── Filtres admin ────────────────────────────────────────────────────
  const [enseignants, setEns] = useState([]);
  const [etudiants, setEtus]  = useState([]);
  const [matieres, setMat]    = useState([]);
  const [typePersonne, setTypePersonne] = useState('prof');
  const [filtrePersonne, setFiltrePers] = useState('');
  const [filtreMatiere, setFiltreMat]   = useState('');

  // ─── Helpers date ──────────────────────────────────────────────────────
  const pad = n => String(n).padStart(2, '0');
  const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  const addDays = (d, n) => { const r = new Date(d); r.setDate(r.getDate()+n); return r; };
  const startOfWeek = d => {
    const day = (d.getDay() + 6) % 7;  // 0=lun ... 6=dim
    const m = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    m.setDate(m.getDate() - day);
    return m;
  };
  const jours    = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
  const moisFR   = ['janvier','février','mars','avril','mai','juin',
                    'juillet','août','septembre','octobre','novembre','décembre'];
  const heures   = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'];

  // Bornes de l'année scolaire (1er sept N → 30 juin N+1)
  const [yearStart, yearEnd] = useMemo(() => {
    const [y1] = annee.split('-').map(Number);
    return [new Date(y1, 8, 1), new Date(y1 + 1, 5, 30)];
  }, [annee]);

  // ─── Toutes les semaines de l'année scolaire (pour le sélecteur) ──────
  const semainesDispo = useMemo(() => {
    const list = [];
    let ws = startOfWeek(yearStart);
    while (ws <= yearEnd) {
      const we = addDays(ws, 6);
      list.push({
        value: fmt(ws),
        debut: new Date(ws),
        fin:   we,
        label: `Sem. du ${pad(ws.getDate())}/${pad(ws.getMonth()+1)} → ${pad(we.getDate())}/${pad(we.getMonth()+1)}/${we.getFullYear()}`,
      });
      ws = addDays(ws, 7);
    }
    return list;
  }, [yearStart, yearEnd]);

  // ─── Plage courante ───────────────────────────────────────────────────
  const { dateDebut, dateFin, weekDays, weekLabel } = useMemo(() => {
    const ws = startOfWeek(currentDate);
    const we = addDays(ws, 6);
    const wDays = Array.from({ length: 7 }, (_, i) => addDays(ws, i));
    const wLabel = `Semaine du ${ws.getDate()} ${moisFR[ws.getMonth()]} au ${we.getDate()} ${moisFR[we.getMonth()]} ${we.getFullYear()}`;
    return { dateDebut: fmt(ws), dateFin: fmt(we), weekDays: wDays, weekLabel: wLabel };
  }, [currentDate]);

  // ─── Chargement ───────────────────────────────────────────────────────
  const load = async () => {
    setLoading(true);
    const params = { annee_scolaire: annee, date_debut: dateDebut, date_fin: dateFin };
    if (isAdmin) {
      if (filtreMatiere) params.matiere = filtreMatiere;
      if (filtrePersonne) {
        if (typePersonne === 'prof') params.enseignant_id = filtrePersonne;
        else params.etudiant_id = filtrePersonne;
      }
    }
    const r = await api.getEmploiDuTemps(params);
    setSessions(Array.isArray(r.data) ? r.data : []);
    setLoading(false);
  };

  useEffect(() => {
    api.getAnneesScolaires?.().then?.(r => {
      if (Array.isArray(r?.data?.annees) && r.data.annees.length > 0) {
        setAnneesDispo(r.data.annees);
        if (r.data.defaut) setAnnee(r.data.defaut);
      }
    }).catch?.(() => {});
  }, []);

  useEffect(() => {
    if (!isAdmin) return;
    Promise.all([api.getEnseignants(), api.getEtudiants({}), api.getMatieres()]).then(([e, et, m]) => {
      setEns(Array.isArray(e.data) ? e.data : []);
      setEtus(Array.isArray(et.data) ? et.data : []);
      setMat(Array.isArray(m.data) ? m.data : []);
    });
  }, []);

  useEffect(() => { load(); }, [annee, dateDebut, dateFin, filtreMatiere, filtrePersonne, typePersonne]);

  const colors = ['#1b2d42','#2a4060','#1e5fa8','#2d7a4f','#c07a1a'];

  // ─── Navigation ───────────────────────────────────────────────────────
  const goPrev   = () => setCurrentDate(addDays(currentDate, -7));
  const goNext   = () => setCurrentDate(addDays(currentDate, 7));
  const goToday  = () => setCurrentDate(new Date());
  const goStart  = () => setCurrentDate(new Date(2025, 8, 1));
  const goSemaine = (iso) => {
    const [y, m, d] = iso.split('-').map(Number);
    setCurrentDate(new Date(y, m - 1, d));
  };

  const selectStyle = { padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem' };
  const resetFiltres = () => { setFiltrePers(''); setFiltreMat(''); };

  const getCellSessions = (dayDate, heure) => {
    const iso = fmt(dayDate);
    return sessions.filter(s =>
      (s.date || s.date_specifique) === iso &&
      s.heure_debut <= heure && s.heure_fin > heure
    );
  };
  const isToday = (d) => fmt(d) === fmt(new Date());

  // Valeur courante du selecteur de semaine = lundi de la semaine en cours
  const currentWeekISO = fmt(startOfWeek(currentDate));

  return (
    <div className="fade-in">
      {/* Titre + sélecteur d'année */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16, flexWrap: 'wrap', gap: 12 }}>
        <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Emploi du temps</h2>
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <label style={{ fontSize: '.85rem', color: 'var(--text-mid)' }}>Année scolaire&nbsp;:</label>
          <select value={annee} onChange={e => setAnnee(e.target.value)} style={selectStyle}>
            {anneesDispo.map(a => <option key={a} value={a}>{a}</option>)}
          </select>
        </div>
      </div>

      {/* Navigation calendrier : boutons + sélecteur de semaine */}
      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card-header" style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <button className="btn btn-outline btn-sm" onClick={goPrev}>← Précédent</button>
            <button className="btn btn-outline btn-sm" onClick={goToday}>Aujourd'hui</button>
            <button className="btn btn-outline btn-sm" onClick={goNext}>Suivant →</button>
            <button className="btn btn-outline btn-sm" onClick={goStart} title="Aller au début de l'année scolaire">Rentrée</button>
          </div>
          <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            <label style={{ fontSize: '.85rem', color: 'var(--text-mid)' }}>Aller à la semaine&nbsp;:</label>
            <select value={currentWeekISO} onChange={e => goSemaine(e.target.value)}
                    style={{ ...selectStyle, minWidth: 300 }}>
              {semainesDispo.map(w => (
                <option key={w.value} value={w.value}>{w.label}</option>
              ))}
            </select>
          </div>
        </div>
        <div style={{ padding: '10px 16px', color: 'var(--navy)', fontFamily: 'var(--font-display)', fontWeight: 600, borderTop: '1px solid var(--cream-dark)' }}>
          {weekLabel}
        </div>
      </div>

      {/* Filtres admin */}
      {isAdmin && (
        <div className="card" style={{ marginBottom: 16 }}>
          <div className="card-header" style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
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
            <select value={filtreMatiere} onChange={e => setFiltreMat(e.target.value)} style={selectStyle}>
              <option value="">Toutes les matières</option>
              {matieres.map(m => <option key={m.matiere} value={m.matiere}>{m.matiere}</option>)}
            </select>
            {(filtrePersonne || filtreMatiere) && (
              <button className="btn btn-outline btn-sm" onClick={resetFiltres}>Réinitialiser</button>
            )}
          </div>
        </div>
      )}

      {/* Grille semaine */}
      {loading ? <Spinner /> : (
        <div style={{ overflowX: 'auto' }}>
          <div className="edt-grid">
            <div className="edt-header"></div>
            {weekDays.slice(0, 5).map((d, i) => (
              <div key={i} className="edt-header" style={isToday(d) ? { background: 'var(--navy-light)' } : undefined}>
                {jours[i]}<br/>
                <small style={{ fontSize: '.7rem', opacity: .8 }}>{pad(d.getDate())}/{pad(d.getMonth()+1)}</small>
              </div>
            ))}
            {heures.map(h => (
              <React.Fragment key={h}>
                <div className="edt-time-col">{h}</div>
                {weekDays.slice(0, 5).map((d, di) => {
                  const cells = getCellSessions(d, h);
                  return (
                    <div key={di} className="edt-cell">
                      {cells.map((s, i) => (
                        <div key={s.session_id} className="edt-event" style={{ background: colors[i % colors.length] }}>
                          <strong>{s.cours}</strong>
                          <small>{s.heure_debut?.slice(0,5)}–{s.heure_fin?.slice(0,5)}</small>
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
          {sessions.length === 0 && <div style={{ marginTop: 16 }}><EmptyState icon="📅" message="Aucune séance cette semaine." /></div>}
        </div>
      )}
    </div>
  );
}
