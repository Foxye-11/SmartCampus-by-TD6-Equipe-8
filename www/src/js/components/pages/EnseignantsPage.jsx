function EnseignantsPage() {
  const { useState, useEffect } = React;
  const [enseignants, setEnseignants] = useState([]);
  const [departements, setDepts]      = useState([]);
  const [loading, setLoading]         = useState(true);
  const [modal, setModal]             = useState(null);
  const [form, setForm]               = useState({});
  const [error, setError]             = useState('');

  // Écoles : simple liste fixe (attribut texte, pas de table dédiée)
  const ecoles = ['École 1', 'École 2', 'École 3', 'École 4'];

  const load = async () => {
    setLoading(true);
    const [eRes, dRes] = await Promise.all([api.getEnseignants(), api.getDepartements()]);
    setEnseignants(eRes.data || []);
    setDepts(dRes.data || []);
    setLoading(false);
  };

  useEffect(() => { load(); }, []);

  const handleSave = async () => {
    setError('');
    const res = modal === 'create'
      ? await api.createEnseignant(form)
      : await api.updateEnseignant(form.id, form);
    if (res.ok && res.data.succes) { setModal(null); load(); }
    else setError((res.data.erreurs || [res.data.erreur]).join(', '));
  };

  const grades = ['Professeur','Maître de conférences','Maître assistant','Vacataire','ATER'];

  return (
    <div className="fade-in">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Gestion des enseignants</h2>
          <p style={{ color: 'var(--text-mid)', fontSize: '.88rem' }}>{enseignants.length} enseignant(s)</p>
        </div>
        <button className="btn btn-primary" onClick={() => { setForm({ grade: 'Maître de conférences' }); setError(''); setModal('create'); }}>
          <Icons.Plus />Ajouter
        </button>
      </div>

      <div className="card">
        {loading ? <Spinner /> : enseignants.length === 0 ? <EmptyState icon="👨‍🏫" message="Aucun enseignant." /> : (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr><th>Nom</th><th>Email</th><th>Grade</th><th>École</th><th>Département</th><th>Cours</th><th>Actions</th></tr>
              </thead>
              <tbody>
                {enseignants.map(e => (
                  <tr key={e.id}>
                    <td><strong>{e.prenom} {e.nom}</strong></td>
                    <td style={{ color: 'var(--text-mid)' }}>{e.email}</td>
                    <td><span className="badge badge-navy">{e.grade}</span></td>
                    <td>{e.ecole || '—'}</td>
                    <td>{e.departement || '—'}</td>
                    <td><span className="badge badge-info">{e.nb_cours}</span></td>
                    <td>
                      <div style={{ display: 'flex', gap: 6 }}>
                        <button className="btn btn-outline btn-sm" onClick={() => { setForm(e); setError(''); setModal('edit'); }}><Icons.Edit /></button>
                        <button className="btn btn-danger btn-sm" onClick={async () => { if (confirm('Désactiver ?')) { await api.deleteEnseignant(e.id); load(); } }}><Icons.Trash /></button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {modal && (
        <Modal
          title={modal === 'create' ? 'Ajouter un enseignant' : 'Modifier l\'enseignant'}
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
                <input type="password" value={form.mot_de_passe || ''} onChange={e => setForm(f => ({ ...f, mot_de_passe: e.target.value }))} />
              </div>
            )}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div className="form-group">
                <label>Grade</label>
                <select value={form.grade || ''} onChange={e => setForm(f => ({ ...f, grade: e.target.value }))}>
                  {grades.map(g => <option key={g} value={g}>{g}</option>)}
                </select>
                <small style={{ color: 'var(--text-light)' }}>
                  Rang académique de l'enseignant (statut/titre).
                </small>
              </div>
              <div className="form-group">
                <label>École</label>
                <select value={form.ecole || ''} onChange={e => setForm(f => ({ ...f, ecole: e.target.value }))}>
                  <option value="">— Aucune —</option>
                  {ecoles.map(ec => <option key={ec} value={ec}>{ec}</option>)}
                </select>
              </div>
            </div>
            <div className="form-group">
              <label>Département</label>
              <select value={form.departement_id || ''} onChange={e => setForm(f => ({ ...f, departement_id: e.target.value }))}>
                <option value="">— Aucun —</option>
                {departements.map(d => <option key={d.id} value={d.id}>{d.nom}</option>)}
              </select>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}