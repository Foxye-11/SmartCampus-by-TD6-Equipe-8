function EtudiantsPage({ user }) {
  const { useState, useEffect } = React;
  const [etudiants, setEtudiants] = useState([]);
  const [loading, setLoading]     = useState(true);
  const [search, setSearch]       = useState('');
  const [modal, setModal]         = useState(null);
  const [form, setForm]           = useState({});
  const [error, setError]         = useState('');
  const [departements, setDepts]  = useState([]);
  const [groupes, setGroupes]     = useState([]);
  // Filtres recherche
  const [filtreNiveau, setFNiv]   = useState('');
  const [filtreGroupe, setFGrp]   = useState('');
  const [filtreEcole, setFEco]    = useState('');
  const [filtreDept, setFDept]    = useState('');
  // Création rapide d'un groupe de TD depuis le formulaire
  const [showNewGrp, setShowNewGrp] = useState(false);
  const [newGrpNom, setNewGrpNom]   = useState('');
  const [grpError, setGrpError]     = useState('');

  // Écoles : simple liste fixe (attribut texte, pas de table dédiée)
  const ecoles = ['École 1', 'École 2', 'École 3', 'École 4'];

  const reloadGroupes = async () => { const g = await api.getGroupesTD(); setGroupes(g.data || []); };

  const handleCreerGroupe = async () => {
    setGrpError('');
    const res = await api.creerGroupeTD({
      niveau:         form.niveau || 'L1',
      nom:            newGrpNom,
      annee_scolaire: form.annee_scolaire || anneeDefaut,
    });
    if (res.ok && res.data.succes) {
      await reloadGroupes();
      setForm(f => ({ ...f, groupe_td_id: res.data.id }));
      setNewGrpNom(''); setShowNewGrp(false);
    } else {
      setGrpError((res.data.erreurs || [res.data.erreur]).join(', '));
    }
  };

  const asArray = x => Array.isArray(x) ? x : [];

  const load = async () => {
    setLoading(true);
    try {
      const [res, dRes, gRes] = await Promise.all([
        api.getEtudiants({
          recherche:      search,
          niveau:         filtreNiveau,
          groupe_td_id:   filtreGroupe,
          ecole:          filtreEcole,
          departement_id: filtreDept,
        }),
        api.getDepartements(),
        api.getGroupesTD(),
      ]);
      setEtudiants(asArray(res.data));
      setDepts(asArray(dRes.data));
      setGroupes(asArray(gRes.data));
    } catch (err) {
      console.error('Chargement étudiants : ', err);
      setEtudiants([]); setDepts([]); setGroupes([]);
    } finally {
      setLoading(false);
    }
  };

  // Rechargement à chaque changement de filtre déroulant (la recherche texte reste manuelle)
  useEffect(() => { load(); }, [filtreNiveau, filtreGroupe, filtreEcole, filtreDept]);

  const resetFiltres = () => { setSearch(''); setFNiv(''); setFGrp(''); setFEco(''); setFDept(''); };
  // Le groupe de TD doit correspondre au niveau choisi : on le réinitialise.
  const openCreate   = () => { setForm({ niveau: 'L1', annee_scolaire: anneeDefaut }); setError(''); setModal('create'); };
  const openEdit     = e  => { setForm(e); setError(''); setModal('edit'); };

  // Années scolaires proposées (année courante ± 1)
  const anneeBase   = new Date().getMonth() >= 7 ? new Date().getFullYear() : new Date().getFullYear() - 1;
  const annees      = [anneeBase - 1, anneeBase, anneeBase + 1].map(a => `${a}-${a + 1}`);
  const anneeDefaut = `${anneeBase}-${anneeBase + 1}`;
  // Groupes de TD filtrés selon le niveau sélectionné dans le formulaire
  const groupesDuNiveau = groupes.filter(g => g.niveau === form.niveau);

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
  const selectStyle = { padding: '6px 10px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.84rem' };

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
        <div className="card-header" style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
          <div className="search-bar">
            <span className="search-icon"><Icons.Search /></span>
            <input placeholder="Nom, prénom, numéro..." value={search}
                   onChange={e => setSearch(e.target.value)}
                   onKeyDown={e => { if (e.key === 'Enter') load(); }} />
          </div>
          <button type="button" className="btn btn-outline btn-sm" onClick={() => load()}>Rechercher</button>
          <select value={filtreNiveau} onChange={e => { setFNiv(e.target.value); setFGrp(''); }} style={selectStyle}>
            <option value="">Tous niveaux</option>
            {niveaux.map(n => <option key={n} value={n}>{n}</option>)}
          </select>
          <select value={filtreGroupe} onChange={e => setFGrp(e.target.value)} style={selectStyle}>
            <option value="">Tous groupes TD</option>
            {groupes.filter(g => !filtreNiveau || g.niveau === filtreNiveau).map(g => <option key={g.id} value={g.id}>{g.libelle}</option>)}
          </select>
          <select value={filtreEcole} onChange={e => setFEco(e.target.value)} style={selectStyle}>
            <option value="">Toutes écoles</option>
            {ecoles.map(ec => <option key={ec} value={ec}>{ec}</option>)}
          </select>
          <select value={filtreDept} onChange={e => setFDept(e.target.value)} style={selectStyle}>
            <option value="">Tous départements</option>
            {departements.map(d => <option key={d.id} value={d.id}>{d.nom}</option>)}
          </select>
          {(search || filtreNiveau || filtreGroupe || filtreEcole || filtreDept) && (
            <button type="button" className="btn btn-outline btn-sm" onClick={resetFiltres}>Réinitialiser</button>
          )}
        </div>

        {loading ? <Spinner /> : etudiants.length === 0 ? <EmptyState icon="🎓" message="Aucun étudiant trouvé." /> : (
          <div className="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Numéro</th><th>Nom complet</th><th>Email</th>
                  <th>Niveau</th><th>Groupe TD</th><th>École</th><th>Département</th><th>Statut</th>
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
                    <td>{e.groupe_td || '—'}</td>
                    <td>{e.ecole || '—'}</td>
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
                <select value={form.niveau || 'L1'}
                        onChange={e => setForm(f => ({ ...f, niveau: e.target.value, groupe_td_id: '' }))}>
                  {niveaux.map(n => <option key={n} value={n}>{n}</option>)}
                </select>
              </div>
              <div className="form-group">
                <label>Groupe de TD</label>
                <select value={form.groupe_td_id || ''} onChange={e => setForm(f => ({ ...f, groupe_td_id: e.target.value }))}>
                  <option value="">— Aucun —</option>
                  {groupesDuNiveau.map(g => <option key={g.id} value={g.id}>{g.libelle}</option>)}
                </select>
                {!showNewGrp ? (
                  <button type="button" className="btn btn-outline btn-sm" style={{ marginTop: 6 }}
                          onClick={() => { setGrpError(''); setShowNewGrp(true); }}>
                    + Nouveau groupe
                  </button>
                ) : (
                  <div style={{ marginTop: 6, padding: 8, border: '1px solid var(--cream-dark)', borderRadius: 'var(--radius)' }}>
                    <small style={{ color: 'var(--text-mid)' }}>Pour {form.niveau || 'L1'} · {form.annee_scolaire || anneeDefaut}</small>
                    <div style={{ display: 'flex', gap: 6, marginTop: 4 }}>
                      <input placeholder="ex: TD05" value={newGrpNom} onChange={e => setNewGrpNom(e.target.value)} />
                      <button type="button" className="btn btn-primary btn-sm" onClick={handleCreerGroupe}>Créer</button>
                      <button type="button" className="btn btn-outline btn-sm" onClick={() => { setShowNewGrp(false); setNewGrpNom(''); setGrpError(''); }}>×</button>
                    </div>
                    {grpError && <small style={{ color: 'var(--danger, #c0392b)' }}>{grpError}</small>}
                  </div>
                )}
              </div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <div className="form-group">
                <label>École</label>
                <select value={form.ecole || ''} onChange={e => setForm(f => ({ ...f, ecole: e.target.value }))}>
                  <option value="">— Aucune —</option>
                  {ecoles.map(ec => <option key={ec} value={ec}>{ec}</option>)}
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
              <select value={form.annee_scolaire || anneeDefaut} onChange={e => setForm(f => ({ ...f, annee_scolaire: e.target.value }))}>
                {annees.map(a => <option key={a} value={a}>{a}</option>)}
              </select>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}