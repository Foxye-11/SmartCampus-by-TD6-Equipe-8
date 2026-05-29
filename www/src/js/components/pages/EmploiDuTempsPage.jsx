function EmploiDuTempsPage({ user }) {
  const { useState, useEffect, useMemo } = React;
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading]   = useState(true);
  const isAdmin = user.role === 'admin';

  // ─── Sélecteur d'année scolaire (seule 2025-2026 disponible pour l'instant) ───
  const [anneesDispo, setAnneesDispo] = useState(['2025-2026']);
  const [annee, setAnnee]             = useState('2025-2026');

  // ─── Navigation calendrier ─────────────────────────────────────────────
  // currentDate = n'importe quelle date dans la semaine/mois affiché.
  const [currentDate, setCurrentDate] = useState(() => {
    const t = new Date();
    // Si on est hors année scolaire, on se positionne au 1er sept 2025 par défaut.
    if (t < new Date(2025, 8, 1) || t > new Date(2026, 5, 30)) {
      return new Date(2025, 8, 1);
    }
    return t;
  });
  const [view, setView] = useState('semaine');  // 'semaine' ou 'mois'

  // ─── Filtres admin ─────────────────────────────────────────────────────
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
    // lundi = début de semaine (FR)
    const day = (d.getDay() + 6) % 7;  // 0=lun ... 6=dim
    const m = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    m.setDate(m.getDate() - day);
    return m;
  };
  const startOfMonth = d => new Date(d.getFullYear(), d.getMonth(), 1);
  const endOfMonth   = d => new Date(d.getFullYear(), d.getMonth()+1, 0);
  const jours  = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
  const joursAbr = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
  const moisFR = ['Janvier','Février','Mars','Avril','Mai','Juin',
                  'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  const heures = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'];

  // ─── Range courant selon la vue ────────────────────────────────────────
  const { dateDebut, dateFin, weekDays, monthGrid, monthLabel, weekLabel } = useMemo(() => {
    const ws = startOfWeek(currentDate);
    const we = addDays(ws, 6);
    const ms = startOfMonth(currentDate);
    const me = endOfMonth(currentDate);

    // 7 jours de la semaine
    const wDays = Array.from({ length: 7 }, (_, i) => addDays(ws, i));

    // Grille mensuelle (semaines complètes incluant débordements sur mois adjacents)
    const gridStart = startOfWeek(ms);
    const gridEnd   = addDays(startOfWeek(me), 6);
    const days = [];
    for (let d = new Date(gridStart); d <= gridEnd; d = addDays(d, 1)) days.push(new Date(d));

    const wLabel = `Semaine du ${ws.getDate()} ${moisFR[ws.getMonth()].toLowerCase()} au ${we.getDate()} ${moisFR[we.getMonth()].toLowerCase()} ${we.getFullYear()}`;
    const mLabel = `${moisFR[currentDate.getMonth()]} ${currentDate.getFullYear()}`;

    return {
      dateDebut: view === 'semaine' ? fmt(ws) : fmt(gridStart),
      dateFin:   view === 'semaine' ? fmt(we) : fmt(gridEnd),
      weekDays:  wDays,
      monthGrid: days,
      monthLabel: mLabel,
      weekLabel: wLabel,
    };
  }, [currentDate, view]);

  // ─── Chargement ────────────────────────────────────────────────────────
  const load = async () => {
    setLoading(true);
    const params = {
      annee_scolaire: annee,
      date_debut: dateDebut,
      date_fin: dateFin,
    };
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

  // Année scolaire disponibles
  useEffect(() => {
    api.getAnneesScolaires?.().then?.(r => {
      if (Array.isArray(r?.data?.annees) && r.data.annees.length > 0) {
        setAnneesDispo(r.data.annees);
        if (r.data.defaut) setAnnee(r.data.defaut);
      }
    }).catch?.(() => {});
  }, []);

  // Référentiels filtre admin
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

  // ─── Navigation ────────────────────────────────────────────────────────
  const goPrev   = () => setCurrentDate(view === 'semaine' ? addDays(currentDate, -7)
                                                            : new Date(currentDate.getFullYear(), currentDate.getMonth()-1, 1));
  const goNext   = () => setCurrentDate(view === 'semaine' ? addDays(currentDate, 7)
                                                            : new Date(currentDate.getFullYear(), currentDate.getMonth()+1, 1));
  const goToday  = () => setCurrentDate(new Date());
  const goStart  = () => setCurrentDate(new Date(2025, 8, 1));   // 1er sept. 2025

  const selectStyle = { padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem' };
  const resetFiltres = () => { setFiltrePers(''); setFiltreMat(''); };

  // ─── Helpers pour les cellules ─────────────────────────────────────────
  // Sessions d'un jour précis (vue semaine) à une heure pivot
  const getCellSessions = (dayDate, heure) => {
    const iso = fmt(dayDate);
    return sessions.filter(s =>
      (s.date || s.date_specifique) === iso &&
      s.heure_debut <= heure && s.heure_fin > heure
    );
  };
  const getDaySessions = (dayDate) => {
    const iso = fmt(dayDate);
    return sessions.filter(s => (s.date || s.date_specifique) === iso)
                   .sort((a,b) => (a.heure_debut || '').localeCompare(b.heure_debut || ''));
  };

  const isToday = (d) => fmt(d) === fmt(new Date());
  const isCurrentMonth = (d) => d.getMonth() === currentDate.getMonth();

  // ─── Rendering ─────────────────────────────────────────────────────────
  return (
    <div className="fade-in">
      {/* Titre + sélecteur année + vue */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16, flexWrap: 'wrap', gap: 12 }}>
        <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Emploi du temps</h2>
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <label style={{ fontSize: '.85rem', color: 'var(--text-mid)' }}>Année scolaire&nbsp;:</label>
          <select value={annee} onChange={e => setAnnee(e.target.value)} style={selectStyle}>
            {anneesDispo.map(a => <option key={a} value={a}>{a}</option>)}
          </select>
          <div className="cal-view-switch" style={{ display: 'inline-flex', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', overflow: 'hidden' }}>
            <button onClick={() => setView('semaine')}
              style={{ padding: '8px 14px', border: 0, background: view === 'semaine' ? 'var(--navy)' : 'var(--white)', color: view === 'semaine' ? '#fff' : 'var(--navy)', fontWeight: 600, cursor: 'pointer' }}>
              Semaine
            </button>
            <button onClick={() => setView('mois')}
              style={{ padding: '8px 14px', border: 0, background: view === 'mois' ? 'var(--navy)' : 'var(--white)', color: view === 'mois' ? '#fff' : 'var(--navy)', fontWeight: 600, cursor: 'pointer' }}>
              Mois
            </button>
          </div>
        </div>
      </div>

      {/* Navigation calendrier */}
      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card-header" style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn btn-outline btn-sm" onClick={goPrev}>← Précédent</button>
            <button className="btn btn-outline btn-sm" onClick={goToday}>Aujourd'hui</button>
            <button className="btn btn-outline btn-sm" onClick={goNext}>Suivant →</button>
            <button className="btn btn-outline btn-sm" onClick={goStart} title="Aller au début de l'année scolaire">Rentrée</button>
          </div>
          <strong style={{ color: 'var(--navy)', fontFamily: 'var(--font-display)' }}>
            {view === 'semaine' ? weekLabel : monthLabel}
          </strong>
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

      {/* Grille */}
      {loading ? <Spinner /> : (
        view === 'semaine' ? (
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
        ) : (
          // Vue mois
          <div className="cal-month">
            <div className="cal-month-grid">
              {jours.map(j => <div key={j} className="edt-header">{j.slice(0,3)}</div>)}
              {monthGrid.map((d, i) => {
                const sess = getDaySessions(d);
                const today = isToday(d);
                const sameMonth = isCurrentMonth(d);
                return (
                  <div key={i} className="cal-day"
                    style={{
                      background: today ? 'rgba(30,95,168,.08)' : (sameMonth ? 'var(--white)' : 'var(--cream)'),
                      opacity: sameMonth ? 1 : .55,
                    }}>
                    <div className="cal-day-num" style={{ fontWeight: today ? 700 : 500, color: today ? 'var(--navy)' : 'var(--text-mid)' }}>
                      {d.getDate()}
                    </div>
                    {sess.slice(0, 3).map((s, k) => (
                      <div key={s.session_id} className="cal-day-event" style={{ background: colors[k % colors.length] }}>
                        <small>{s.heure_debut?.slice(0,5)}</small> {s.cours}
                      </div>
                    ))}
                    {sess.length > 3 && (
                      <div className="cal-day-more">+{sess.length - 3} autre(s)</div>
                    )}
                  </div>
                );
              })}
            </div>
            {sessions.length === 0 && <div style={{ marginTop: 16 }}><EmptyState icon="📅" message="Aucune séance ce mois-ci." /></div>}
          </div>
        )
      )}
    </div>
  );
}
