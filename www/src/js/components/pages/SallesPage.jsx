function SallesPage() {
  const { useState, useEffect } = React;
  const [salles, setSalles] = useState([]);
  const [loading, setLoad]  = useState(true);
  const [modal, setModal]   = useState(null);
  const [form, setForm]     = useState({});
  const [error, setError]   = useState('');

  const load = async () => { setLoad(true); const r = await api.getSalles(); setSalles(r.data || []); setLoad(false); };
  useEffect(() => { load(); }, []);

  const handleSave = async () => {
    setError('');
    const res = modal === 'create' ? await api.creerSalle(form) : await api.modifierSalle(form.id, form);
    if (res.ok && res.data.succes) { setModal(null); load(); }
    else setError((res.data.erreurs || [res.data.erreur]).join(', '));
  };

  const types = ['cours','tp','amphi','seminaire'];

  return (
    <div className="fade-in">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Gestion des salles</h2>
        <button className="btn btn-primary" onClick={() => { setForm({ type_salle: 'cours', capacite: 30, disponible: 1 }); setError(''); setModal('create'); }}><Icons.Plus />Ajouter</button>
      </div>
      <div className="card">
        {loading ? <Spinner /> : salles.length === 0 ? <EmptyState icon="🏫" message="Aucune salle." /> : (
          <div className="table-wrapper">
            <table>
              <thead><tr><th>Nom</th><th>Bâtiment</th><th>Type</th><th>Capacité</th><th>Disponible</th><th>Actions</th></tr></thead>
              <tbody>
                {salles.map(s => (
                  <tr key={s.id}>
                    <td><strong>{s.nom}</strong></td>
                    <td>{s.batiment || '—'}</td>
                    <td><span className="badge badge-navy">{s.type_salle}</span></td>
                    <td>{s.capacite}</td>
                    <td><span className={`badge ${s.disponible ? 'badge-success' : 'badge-danger'}`}>{s.disponible ? 'Oui' : 'Non'}</span></td>
                    <td>
                      <div style={{ display: 'flex', gap: 6 }}>
                        <button className="btn btn-outline btn-sm" onClick={() => { setForm(s); setError(''); setModal('edit'); }}><Icons.Edit /></button>
                        <button className="btn btn-danger btn-sm" onClick={async () => { if (confirm('Supprimer ?')) { const r = await api.supprimerSalle(s.id); if (!r.ok) alert(r.data.erreurs?.join(', ')); else load(); } }}><Icons.Trash /></button>
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
        <Modal title={modal === 'create' ? 'Ajouter une salle' : 'Modifier la salle'} onClose={() => setModal(null)}
          footer={<><button className="btn btn-outline" onClick={() => setModal(null)}>Annuler</button><button className="btn btn-primary" onClick={handleSave}>Enregistrer</button></>}>
          {error && <Alert type="error">{error}</Alert>}
          <div style={{ display: 'grid', gap: 14 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 12 }}>
              <div className="form-group"><label>Nom</label><input value={form.nom || ''} onChange={e => setForm(f => ({ ...f, nom: e.target.value }))} placeholder="Salle B204" /></div>
              <div className="form-group"><label>Capacité</label><input type="number" min="1" value={form.capacite || 30} onChange={e => setForm(f => ({ ...f, capacite: e.target.value }))} /></div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div className="form-group"><label>Bâtiment</label><input value={form.batiment || ''} onChange={e => setForm(f => ({ ...f, batiment: e.target.value }))} /></div>
              <div className="form-group">
                <label>Type</label>
                <select value={form.type_salle || 'cours'} onChange={e => setForm(f => ({ ...f, type_salle: e.target.value }))}>
                  {types.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
            </div>
            <div className="form-group">
              <label>Disponible</label>
              <select value={form.disponible} onChange={e => setForm(f => ({ ...f, disponible: parseInt(e.target.value) }))}>
                <option value={1}>Oui</option><option value={0}>Non</option>
              </select>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}