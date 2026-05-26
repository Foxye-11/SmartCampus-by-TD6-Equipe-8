function CoursPage({ user }) {
  const { useState, useEffect } = React;
  const [cours, setCours]         = useState([]);
  const [semestres, setSem]       = useState([]);
  const [enseignants, setEns]     = useState([]);
  const [departements, setDepts]  = useState([]);
  const [loading, setLoading]     = useState(true);
  const [modal, setModal]         = useState(null);
  const [form, setForm]           = useState({});
  const [error, setError]         = useState('');
  const [filtreSem, setFiltreSem] = useState('');

  const load = async () => {
    setLoading(true);
    const [cRes, sRes, eRes, dRes] = await Promise.all([
      api.getCours({ semestre_id: filtreSem }),
      api.getSemestres(),
      api.getEnseignants(),
      api.getDepartements(),
    ]);
    setCours(cRes.data || []);
    setSem(sRes.data || []);
    setEns(eRes.data || []);
    setDepts(dRes.data || []);
    setLoading(false);
  };

  useEffect(() => { load(); }, [filtreSem]);

  const handleSave = async () => {
    setError('');
    const res = modal === 'create' ? await api.createCours(form) : await api.updateCours(form.id, form);
    if (res.ok && res.data.succes) { setModal(null); load(); }
    else setError((res.data.erreurs || [res.data.erreur]).join(', '));
  };

  const statutInscription = c => {
    if (c.inscrits >= c.capacite_max) return <span className="badge badge-danger">Complet</span>;
    if (c.inscrits >= c.capacite_max * 0.8) return <span className="badge badge-warning">Presque complet</span>;
    return <span className="badge badge-success">Disponible</span>;
  };

  return (
    <div className="fade-in">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24, flexWrap: 'wrap', gap: 12 }}>
        <div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Gestion des cours</h2>
          <p style={{ color: 'var(--text-mid)', fontSize: '.88rem' }}>{cours.length} cours</p>
        </div>
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <select value={filtreSem} onChange={e => setFiltreSem(e.target.value)}
            style={{ padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem' }}>
            <option value="">Tous les semestres</option>
            {semestres.map(s => <option key={s.id} value={s.id}>{s.libelle}</option>)}
          </select>
          {user.role === 'admin' && (
            <button className="btn btn-primary" onClick={() => { setForm({ credits: 3, capacite_max: 30 }); setError(''); setModal('create'); }}>
              <Icons.Plus />Créer
            </button>
          )}
        </div>
      </div>

      <div className="card">
        {loading ? <Spinner /> : cours.length === 0 ? <EmptyState icon="📚" message="Aucun cours." /> : (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Code</th><th>Intitulé</th><th>Semestre</th><th>Enseignant</th>
                  <th>Crédits</th><th>Inscrits</th><th>Statut</th>
                  {user.role === 'admin' && <th>Actions</th>}
                </tr>
              </thead>
              <tbody>
                {cours.map(c => (
                  <tr key={c.id}>
                    <td><code style={{ background: 'var(--cream)', padding: '2px 6px', borderRadius: 4, fontSize: '.82rem' }}>{c.code}</code></td>
                    <td>
                      <strong>{c.intitule}</strong>
                      {c.notes_verrouillees == 1 && <span className="badge badge-warning" style={{ marginLeft: 8 }}>🔒</span>}
                    </td>
                    <td>{c.semestre}</td>
                    <td>{c.enseignant || <em style={{ color: 'var(--text-light)' }}>Non assigné</em>}</td>
                    <td><span className="badge badge-navy">{c.credits} ECTS</span></td>
                    <td>{c.inscrits}/{c.capacite_max}</td>
                    <td>{statutInscription(c)}</td>
                    {user.role === 'admin' && (
                      <td>
                        <div style={{ display: 'flex', gap: 6 }}>
                          <button className="btn btn-outline btn-sm" onClick={() => { setForm({ ...c, semestre_id: c.semestre_id, enseignant_id: c.enseignant_id }); setError(''); setModal('edit'); }}><Icons.Edit /></button>
                          <button className="btn btn-danger btn-sm" onClick={async () => { if (confirm('Supprimer ?')) { const r = await api.deleteCours(c.id); if (!r.ok) alert(r.data.erreurs?.join(', ')); else load(); } }}><Icons.Trash /></button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {modal && (
        <Modal title={modal === 'create' ? 'Créer un cours' : 'Modifier le cours'} onClose={() => setModal(null)}
          footer={<><button className="btn btn-outline" onClick={() => setModal(null)}>Annuler</button><button className="btn btn-primary" onClick={handleSave}>Enregistrer</button></>}>
          {error && <Alert type="error">{error}</Alert>}
          <div style={{ display: 'grid', gap: 14 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 12 }}>
              <div className="form-group"><label>Code</label><input value={form.code || ''} onChange={e => setForm(f => ({ ...f, code: e.target.value }))} placeholder="INFO101" /></div>
              <div className="form-group"><label>Intitulé</label><input value={form.intitule || ''} onChange={e => setForm(f => ({ ...f, intitule: e.target.value }))} /></div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div className="form-group"><label>Crédits ECTS</label><input type="number" min="1" max="30" value={form.credits || 3} onChange={e => setForm(f => ({ ...f, credits: e.target.value }))} /></div>
              <div className="form-group"><label>Capacité max</label><input type="number" min="1" value={form.capacite_max || 30} onChange={e => setForm(f => ({ ...f, capacite_max: e.target.value }))} /></div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div className="form-group">
                <label>Semestre</label>
                <select value={form.semestre_id || ''} onChange={e => setForm(f => ({ ...f, semestre_id: e.target.value }))}>
                  <option value="">— Choisir —</option>
                  {semestres.map(s => <option key={s.id} value={s.id}>{s.libelle}</option>)}
                </select>
              </div>
              <div className="form-group">
                <label>Département</label>
                <select value={form.departement_id || ''} onChange={e => setForm(f => ({ ...f, departement_id: e.target.value }))}>
                  <option value="">— Aucun —</option>
                  {departements.map(d => <option key={d.id} value={d.id}>{d.nom}</option>)}
                </select>
              </div>
            </div>
            <div className="form-group">
              <label>Enseignant responsable</label>
              <select value={form.enseignant_id || ''} onChange={e => setForm(f => ({ ...f, enseignant_id: e.target.value }))}>
                <option value="">— Non assigné —</option>
                {enseignants.map(e => <option key={e.id} value={e.id}>{e.prenom} {e.nom}</option>)}
              </select>
            </div>
            <div className="form-group"><label>Description</label><textarea value={form.description || ''} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} /></div>
          </div>
        </Modal>
      )}
    </div>
  );
}