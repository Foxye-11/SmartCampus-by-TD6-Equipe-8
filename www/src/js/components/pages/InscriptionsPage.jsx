function InscriptionsPage({ user }) {
  const { useState, useEffect } = React;
  const [suivis, setSuivis]     = useState([]);
  const [disponibles, setDispo] = useState([]);
  const [loading, setLoading]   = useState(true);
  const [tab, setTab]           = useState('suivis');
  const [msg, setMsg]           = useState(null);

  const load = async () => {
    setLoading(true);
    const [sRes, dRes] = await Promise.all([
      api.coursSuivis(user.etudiant_id),
      api.coursDisponibles(user.etudiant_id),
    ]);
    setSuivis(sRes.data || []);
    setDispo(dRes.data || []);
    setLoading(false);
  };

  useEffect(() => { load(); }, []);

  const showMsg = (type, text) => { setMsg({ type, text }); setTimeout(() => setMsg(null), 4000); };

  const handleInscrire = async coursId => {
    const res = await api.inscrire(user.etudiant_id, coursId);
    showMsg(res.ok && res.data.succes ? 'success' : 'error', res.data.message || res.data.erreur);
    if (res.ok && res.data.succes) load();
  };

  const handleAnnuler = async inscriptionId => {
    if (!confirm('Annuler cette inscription ?')) return;
    const res = await api.annulerInscription(inscriptionId);
    showMsg(res.ok && res.data.succes ? 'success' : 'error', res.data.message || res.data.erreur);
    if (res.ok && res.data.succes) load();
  };

  return (
    <div className="fade-in">
      <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)', marginBottom: 24 }}>Mes inscriptions</h2>
      {msg && <Alert type={msg.type}>{msg.text}</Alert>}

      <div style={{ display: 'flex', gap: 0, marginBottom: 20, background: 'var(--cream-dark)', borderRadius: 'var(--radius)', padding: 3, width: 'fit-content' }}>
        {[['suivis', 'Cours suivis'], ['disponibles', 'Cours disponibles']].map(([k, l]) => (
          <button key={k} className={`btn btn-sm ${tab === k ? 'btn-primary' : ''}`} style={{ borderRadius: 6 }} onClick={() => setTab(k)}>{l}</button>
        ))}
      </div>

      {loading ? <Spinner /> : tab === 'suivis' ? (
        <div className="card">
          {suivis.length === 0 ? <EmptyState icon="📋" message="Vous n'êtes inscrit à aucun cours." /> : (
            <div className="table-wrapper">
              <table>
                <thead><tr><th>Code</th><th>Cours</th><th>Jour & heure</th><th>Enseignant</th><th>Crédits</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                  {suivis.map(c => (
                    <tr key={c.inscription_id}>
                      <td><code style={{ background: 'var(--cream)', padding: '2px 6px', borderRadius: 4, fontSize: '.82rem' }}>{c.code}</code></td>
                      <td><strong>{c.intitule}</strong></td>
                      <td>{c.creneau || <em style={{ color: 'var(--text-light)' }}>Non planifié</em>}</td>
                      <td>{c.enseignant || '—'}</td>
                      <td><span className="badge badge-navy">{c.credits}</span></td>
                      <td><span className={`badge ${c.statut === 'active' ? 'badge-success' : 'badge-warning'}`}>{c.statut}</span></td>
                      <td>{c.statut === 'active' && <button className="btn btn-danger btn-sm" onClick={() => handleAnnuler(c.inscription_id)}>Annuler</button>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      ) : (
        <div className="card">
          {disponibles.length === 0 ? <EmptyState icon="✅" message="Vous êtes inscrit à tous les cours disponibles." /> : (
            <div className="table-wrapper">
              <table>
                <thead><tr><th>Code</th><th>Cours</th><th>Jour & heure</th><th>Enseignant</th><th>Places</th><th>Action</th></tr></thead>
                <tbody>
                  {disponibles.map(c => (
                    <tr key={c.id}>
                      <td><code style={{ background: 'var(--cream)', padding: '2px 6px', borderRadius: 4, fontSize: '.82rem' }}>{c.code}</code></td>
                      <td><strong>{c.intitule}</strong></td>
                      <td>{c.creneau || <em style={{ color: 'var(--text-light)' }}>Non planifié</em>}</td>
                      <td>{c.enseignant || '—'}</td>
                      <td>{c.capacite_max - c.inscrits > 0 ? <span className="badge badge-success">{c.capacite_max - c.inscrits} place(s)</span> : <span className="badge badge-danger">Complet</span>}</td>
                      <td><button className="btn btn-gold btn-sm" onClick={() => handleInscrire(c.id)} disabled={c.inscrits >= c.capacite_max}>S'inscrire</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  );
}