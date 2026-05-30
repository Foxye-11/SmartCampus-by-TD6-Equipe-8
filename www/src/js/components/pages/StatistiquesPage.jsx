// StatistiquesPage.jsx — Tableau de bord statistique + gestion de l'archivage
// des semestres. Réservé à l'administration.
function StatistiquesPage({ user }) {
  const { useState, useEffect } = React;
  const [stats, setStats]       = useState(null);
  const [semestres, setSems]    = useState([]);
  const [loading, setLoading]   = useState(true);
  const [busy, setBusy]         = useState(null); // id de semestre en cours de bascule

  const load = async () => {
    setLoading(true);
    const [s, sem] = await Promise.all([api.getStatistiques(), api.getSemestres()]);
    setStats(s.data && s.data.succes ? s.data : null);
    setSems(Array.isArray(sem.data) ? sem.data : []);
    setLoading(false);
  };
  useEffect(() => { load(); }, []);

  const toggleArchive = async (sem) => {
    const archiver = !Number(sem.archive);
    const msg = archiver
      ? `Archiver « ${sem.libelle} » ? Les inscriptions à ses cours seront fermées.`
      : `Réactiver « ${sem.libelle} » ? Les inscriptions redeviendront possibles.`;
    if (!confirm(msg)) return;
    setBusy(sem.id);
    const r = await api.archiverSemestre(sem.id, archiver);
    setBusy(null);
    if (r.ok && r.data.succes) {
      setSems(list => list.map(x => x.id === sem.id ? { ...x, archive: r.data.archive } : x));
    } else {
      alert((r.data.erreurs || [r.data.erreur]).join(', '));
    }
  };

  if (loading) return <Spinner />;

  const g = stats?.global || {};
  const fmt = (v, suffix = '') => (v === null || v === undefined) ? '—' : `${v}${suffix}`;

  // Barre de distribution : max pour la mise à l'échelle
  const dist = stats?.distribution || [];
  const maxDist = Math.max(1, ...dist.map(d => d.nb));

  const risque = stats?.etudiants_risque || [];
  const cours  = stats?.par_cours || [];

  const tauxColor = (t, seuil, inverse) => {
    if (t === null || t === undefined) return 'var(--text-light)';
    const bad = inverse ? t > seuil : t < seuil;
    return bad ? 'var(--danger)' : 'var(--success)';
  };

  return (
    <div className="fade-in">
      <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)', marginBottom: 20 }}>
        Statistiques académiques
      </h2>

      {/* ─── Indicateurs globaux ─────────────────────────────── */}
      <div className="stat-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 16, marginBottom: 24 }}>
        <div className="stat-card gold"><div className="stat-label">Moyenne établissement</div><div className="stat-value">{fmt(g.moyenne_etablissement, '/20')}</div></div>
        <div className="stat-card green"><div className="stat-label">Taux de réussite</div><div className="stat-value">{fmt(g.taux_reussite, '%')}</div><div className="stat-sub">moyenne ≥ 10</div></div>
        <div className="stat-card"><div className="stat-label">Taux de présence</div><div className="stat-value">{fmt(g.taux_presence, '%')}</div></div>
        <div className="stat-card"><div className="stat-label">Étudiants actifs</div><div className="stat-value">{fmt(g.nb_etudiants)}</div></div>
        <div className="stat-card"><div className="stat-label">Enseignants</div><div className="stat-value">{fmt(g.nb_enseignants)}</div></div>
        <div className="stat-card"><div className="stat-label">Cours</div><div className="stat-value">{fmt(g.nb_cours)}</div><div className="stat-sub">{fmt(g.nb_inscriptions)} inscriptions</div></div>
      </div>

      {/* ─── Distribution des moyennes ───────────────────────── */}
      <div className="card" style={{ marginBottom: 24 }}>
        <div className="card-header"><span className="card-title">Distribution des moyennes générales</span></div>
        <div style={{ padding: 22 }}>
          {dist.every(d => d.nb === 0) ? (
            <div style={{ color: 'var(--text-light)', fontStyle: 'italic', fontSize: '.88rem' }}>Aucune note saisie pour l'instant.</div>
          ) : (
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 18, height: 180 }}>
              {dist.map((d, i) => (
                <div key={i} style={{ flex: 1, textAlign: 'center', display: 'flex', flexDirection: 'column', justifyContent: 'flex-end', height: '100%' }}>
                  <div style={{ fontSize: '.8rem', fontWeight: 700, color: 'var(--navy)', marginBottom: 4 }}>{d.nb}</div>
                  <div style={{
                    height: `${(d.nb / maxDist) * 100}%`, minHeight: d.nb > 0 ? 4 : 0,
                    background: i < 2 ? 'var(--danger)' : 'var(--navy-light)',
                    borderRadius: '4px 4px 0 0', transition: 'height .4s ease',
                  }} />
                  <div style={{ fontSize: '.74rem', color: 'var(--text-mid)', marginTop: 6 }}>{d.label}</div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* ─── Statistiques par cours ──────────────────────────── */}
      <div className="card" style={{ marginBottom: 24 }}>
        <div className="card-header"><span className="card-title">Performance par cours</span></div>
        <div className="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Cours</th><th>Semestre</th>
                <th style={{ textAlign: 'center' }}>Effectif</th>
                <th style={{ textAlign: 'center' }}>Moyenne</th>
                <th style={{ textAlign: 'center' }}>Réussite</th>
                <th style={{ textAlign: 'center' }}>Absences</th>
              </tr>
            </thead>
            <tbody>
              {cours.length === 0 ? (
                <tr><td colSpan="6" style={{ textAlign: 'center', color: 'var(--text-light)', padding: 18 }}>Aucun cours.</td></tr>
              ) : cours.map((c, i) => (
                <tr key={i}>
                  <td><small style={{ color: 'var(--text-light)' }}>{c.code}</small><br />{c.intitule}{c.archive ? <span className="badge badge-warning" style={{ marginLeft: 6 }}>archivé</span> : null}</td>
                  <td>{c.semestre}</td>
                  <td style={{ textAlign: 'center' }}>{c.effectif}</td>
                  <td style={{ textAlign: 'center', fontWeight: 600 }}>{c.moyenne === null ? '—' : `${c.moyenne}/20`}</td>
                  <td style={{ textAlign: 'center', fontWeight: 600, color: tauxColor(c.taux_reussite, 50, false) }}>{c.taux_reussite === null ? '—' : `${c.taux_reussite}%`}</td>
                  <td style={{ textAlign: 'center', fontWeight: 600, color: tauxColor(c.taux_absence, 30, true) }}>{c.taux_absence === null ? '—' : `${c.taux_absence}%`}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* ─── Étudiants à risque ──────────────────────────────── */}
      <div className="card" style={{ marginBottom: 24, borderLeft: '4px solid var(--danger)' }}>
        <div className="card-header">
          <span className="card-title">⚠️ Étudiants à risque</span>
          <span className="badge badge-danger">{risque.length}</span>
        </div>
        {risque.length === 0 ? (
          <div style={{ padding: 22, color: 'var(--text-light)', fontStyle: 'italic', fontSize: '.88rem' }}>Aucun étudiant en difficulté détecté.</div>
        ) : (
          <div className="table-wrapper">
            <table>
              <thead><tr><th>Étudiant</th><th>N°</th><th style={{ textAlign: 'center' }}>Moyenne</th><th style={{ textAlign: 'center' }}>Absences</th><th>Motif</th></tr></thead>
              <tbody>
                {risque.map((r, i) => (
                  <tr key={i}>
                    <td><strong>{r.nom}</strong></td>
                    <td><code style={{ background: 'var(--cream)', padding: '2px 6px', borderRadius: 4, fontSize: '.82rem' }}>{r.numero_etudiant}</code></td>
                    <td style={{ textAlign: 'center', fontWeight: 600, color: r.moyenne !== null && r.moyenne < 10 ? 'var(--danger)' : 'var(--text-dark)' }}>{r.moyenne === null ? '—' : `${r.moyenne}/20`}</td>
                    <td style={{ textAlign: 'center', fontWeight: 600, color: r.taux_absence > 30 ? 'var(--danger)' : 'var(--text-dark)' }}>{r.taux_absence}%</td>
                    <td><span className="badge badge-danger">{r.raisons}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* ─── Archivage des semestres ─────────────────────────── */}
      <div className="card">
        <div className="card-header"><span className="card-title">Archivage des semestres</span></div>
        <div style={{ padding: '8px 22px 14px', fontSize: '.84rem', color: 'var(--text-light)' }}>
          Un semestre archivé passe en lecture seule : plus aucune nouvelle inscription n'est possible sur ses cours.
        </div>
        <div className="table-wrapper">
          <table>
            <thead><tr><th>Semestre</th><th>Année</th><th style={{ textAlign: 'center' }}>Cours</th><th style={{ textAlign: 'center' }}>État</th><th style={{ textAlign: 'right' }}>Action</th></tr></thead>
            <tbody>
              {semestres.length === 0 ? (
                <tr><td colSpan="5" style={{ textAlign: 'center', color: 'var(--text-light)', padding: 18 }}>Aucun semestre.</td></tr>
              ) : semestres.map(sem => (
                <tr key={sem.id}>
                  <td><strong>{sem.libelle}</strong></td>
                  <td>{sem.annee_scolaire}</td>
                  <td style={{ textAlign: 'center' }}>{sem.nb_cours}</td>
                  <td style={{ textAlign: 'center' }}>
                    <span className={`badge ${Number(sem.archive) ? 'badge-warning' : 'badge-success'}`}>
                      {Number(sem.archive) ? 'Archivé' : 'Actif'}
                    </span>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <button
                      className={`btn btn-sm ${Number(sem.archive) ? 'btn-outline' : 'btn-danger'}`}
                      disabled={busy === sem.id}
                      onClick={() => toggleArchive(sem)}
                    >
                      {busy === sem.id ? '…' : (Number(sem.archive) ? 'Réactiver' : 'Archiver')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
