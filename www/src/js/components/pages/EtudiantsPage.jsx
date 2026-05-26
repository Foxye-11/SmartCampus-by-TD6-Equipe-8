function EtudiantsPage({ user }) {
  const { useState, useEffect } = React;
  const [etudiants, setEtudiants] = useState([]);
  const [loading, setLoading]     = useState(true);
  const [search, setSearch]       = useState('');
  const [modal, setModal]         = useState(null);
  const [form, setForm]           = useState({});
  const [error, setError]         = useState('');
  const [departements, setDepts]  = useState([]);

  const load = async () => {
    setLoading(true);
    const [res, dRes] = await Promise.all([
      api.getEtudiants({ recherche: search }),
      api.getDepartements(),
    ]);
    setEtudiants(res.data || []);
    setDepts(dRes.data || []);
    setLoading(false);
  };

  useEffect(() => { load(); }, []);

  const handleSearch = e => { e.preventDefault(); load(); };
  const openCreate   = () => { setForm({ niveau: 'L1' }); setError(''); setModal('create'); };
  const openEdit     = e  => { setForm(e); setError(''); setModal('edit'); };

  const handleSave = async () => {
    setError('');
    const res = modal === 'create'
      ? await api.createEtudiant(form)
      : await api.updateEtudiant(form.id, form);
    if (res.ok && res.data.succes) { setModal(null); load(); }
    else setError((res.data.erreurs || [res.data.erreur]).join(', '));
  };

  const handleDelete = async id => {
    if (!confirm('Désactiver cet étudiant ?')) return;
    await api.deleteEtudiant(id);
    load();
  };

  const niveaux = ['L1','L2','L3','M1','M2','BUT1','BUT2','BUT3','ING1','ING2','ING3'];

  return (
    <div className="fade-in">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Gestion des étudiants</h2>
          <p style={{ color: 'var(--text-mid)', fontSize: '.88rem' }}>{etudiants.length} étudiant(s)</p>
        </div>
        {user.role === 'admin' && (
          <button className="btn btn-primary" onClick={openCreate}><Icons.Plus />Ajouter</button>
        )}
      </div>

      <div className="card">
        <div className="card-header">
          <form onSubmit={handleSearch} style={{ display: 'flex', gap: 8 }}>
            <div className="search-bar">
              <span className="search-icon"><Icons.Search /></span>
              <input placeholder="Nom, prénom, numéro..." value={search} onChange={e => setSearch(e.target.value)} />
            </div>
            <button type="submit" className="btn btn-outline btn-sm">Rechercher</button>
          </form>
        </div>

        {loading ? <Spinner /> : etudiants.length === 0 ? <EmptyState icon="🎓" message="Aucun étudiant trouvé." /> : (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Numéro</th><th>Nom complet</th><th>Email</th>
                  <th>Niveau</th><th>Département</th><th>Statut</th>
                  {user.role === 'admin' && <th>Actions</th>}
                </tr>
              </thead>
              <tbody>
                {etudiants.map(e => (
                  <tr key={e.id}>
                    <td><code style={{ background: 'var(--cream)', padding: '2px 6px', borderRadius: 4, fontSize: '.82rem' }}>{e.numero_etudiant}</code></td>
                    <td><strong>{e.prenom} {e.nom}</strong></td>
                    <td style={{ color: 'var(--text-mid)' }}>{e.email}</td>
                    <td><span className="badge badge-navy">{e.niveau}</span></td>
                    <td>{e.departement || '—'}</td>
                    <td><span className={`badge ${e.actif ? 'badge-success' : 'badge-danger'}`}>{e.actif ? 'Actif' : 'Inactif'}</span></td>
                    {user.role === 'admin' && (
                      <td>
                        <div style={{ display: 'flex', gap: 6 }}>
                          <button className="btn btn-outline btn-sm" onClick={() => openEdit(e)}><Icons.Edit /></button>
                          <button className="btn btn-danger btn-sm" onClick={() => handleDelete(e.id)}><Icons.Trash /></button>
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
        <Modal
          title={modal === 'create' ? 'Ajouter un étudiant' : 'Modifier l\'étudiant'}
          onClose={() => setModal(null)}
          footer={<>
            <button className="btn btn-outline" onClick={() => setModal(null)}>Annuler</button>
            <button className="btn btn-primary" onClick={handleSave}>Enregistrer</button>
          </>}
        >
          {error && <Alert type="error">{error}</Alert>}
          <div style={{ display: 'grid', gap: 14 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div className="form-group">
                <label>Prénom</label>
                <input value={form.prenom || ''} onChange={e => setForm(f => ({ ...f, prenom: e.target.value }))} />
              </div>
              <div className="form-group">
                <label>Nom</label>
                <input value={form.nom || ''} onChange={e => setForm(f => ({ ...f, nom: e.target.value }))} />
              </div>
            </div>
            <div className="form-group">
              <label>Email</label>
              <input type="email" value={form.email || ''} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} />
            </div>
            {modal === 'create' && (
              <div className="form-group">
                <label>Mot de passe</label>
                <input type="password" value={form.mot_de_passe || ''} onChange={e => setForm(f => ({ ...f, mot_de_passe: e.target.value }))} placeholder="Laisser vide = défaut" />
              </div>
            )}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div className="form-group">
                <label>Niveau</label>
                <select value={form.niveau || 'L1'} onChange={e => setForm(f => ({ ...f, niveau: e.target.value }))}>
                  {niveaux.map(n => <option key={n} value={n}>{n}</option>)}
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
              <label>Année scolaire</label>
              <input value={form.annee_scolaire || ''} onChange={e => setForm(f => ({ ...f, annee_scolaire: e.target.value }))} placeholder="ex: 2025-2026" />
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}