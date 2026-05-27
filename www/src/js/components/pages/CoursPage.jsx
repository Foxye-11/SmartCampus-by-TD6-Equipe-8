function CoursPage({ user }) {
  const { useState, useEffect } = React;
  const [cours, setCours]         = useState([]);
  const [semestres, setSem]       = useState([]);
  const [enseignants, setEns]     = useState([]);
  const [departements, setDepts]  = useState([]);
  const [salles, setSalles]       = useState([]);
  const [groupes, setGroupes]     = useState([]);
  const [etudiants, setEtudiants] = useState([]);
  const [loading, setLoading]     = useState(true);
  const [modal, setModal]         = useState(null);
  const [form, setForm]           = useState({});
  const [error, setError]         = useState('');
  // Filtres
  const [filtreSem, setFiltreSem]     = useState('');
  const [filtreDept, setFiltreDept]   = useState('');
  const [filtreGroupe, setFiltreGrp]  = useState('');
  // Recherches locales (salle, étudiant) dans le formulaire de création
  const [salleSearch, setSalleSearch] = useState('');
  const [etuSearch, setEtuSearch]     = useState('');

  const load = async () => {
    setLoading(true);
    const [cRes, sRes, eRes, dRes, salRes, gRes, etuRes] = await Promise.all([
      api.getCours({ semestre_id: filtreSem, departement_id: filtreDept, groupe_td_id: filtreGroupe }),
      api.getSemestres(),
      api.getEnseignants(),
      api.getDepartements(),
      api.getSalles(),
      api.getGroupesTD(),
      api.getEtudiants({}),
    ]);
    setCours(cRes.data || []);
    setSem(sRes.data || []);
    setEns(eRes.data || []);
    setDepts(dRes.data || []);
    setSalles(salRes.data || []);
    setGroupes(gRes.data || []);
    setEtudiants(etuRes.data || []);
    setLoading(false);
  };

  useEffect(() => { load(); }, [filtreSem, filtreDept, filtreGroupe]);

  const heures = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00'];

  const openCreate = () => {
    setForm({ credits: 3, capacite_max: 30, groupes_td: [], etudiants: [] });
    setSalleSearch(''); setEtuSearch(''); setError(''); setModal('create');
  };

  const handleSave = async () => {
    setError('');
    const res = modal === 'create' ? await api.createCours(form) : await api.updateCours(form.id, form);
    if (res.ok && res.data.succes) {
      if (res.data.avertissement) alert(res.data.avertissement);
      setModal(null); load();
    } else {
      setError((res.data.erreurs || [res.data.erreur]).join(', '));
    }
  };

  // Sélection multiple des groupes de TD
  const toggleGroupe = gid => setForm(f => {
    const set = new Set((f.groupes_td || []).map(String));
    set.has(String(gid)) ? set.delete(String(gid)) : set.add(String(gid));
    return { ...f, groupes_td: [...set] };
  });

  // Ajout / retrait d'étudiants individuels
  const addEtudiant = eid => setForm(f => {
    const set = new Set((f.etudiants || []).map(String));
    set.add(String(eid));
    return { ...f, etudiants: [...set] };
  });
  const removeEtudiant = eid => setForm(f => ({
    ...f, etudiants: (f.etudiants || []).filter(x => String(x) !== String(eid)),
  }));

  const statutInscription = c => {
    if (c.inscrits >= c.capacite_max) return <span className="badge badge-danger">Complet</span>;
    if (c.inscrits >= c.capacite_max * 0.8) return <span className="badge badge-warning">Presque complet</span>;
    return <span className="badge badge-success">Disponible</span>;
  };

  const sallesFiltrees = salles.filter(s => {
    const q = salleSearch.toLowerCase();
    return !q || s.nom.toLowerCase().includes(q) || (s.batiment || '').toLowerCase().includes(q);
  });
  const etudiantsFiltres = etudiants.filter(e => {
    const q = etuSearch.toLowerCase();
    if (!q) return false; // on n'affiche la liste qu'après une recherche
    return `${e.prenom} ${e.nom}`.toLowerCase().includes(q)
        || (e.numero_etudiant || '').toLowerCase().includes(q);
  }).slice(0, 8);
  const etudiantParId = id => etudiants.find(e => String(e.id) === String(id));

  const selectStyle = { padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem' };

  return (
    <div className="fade-in">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24, flexWrap: 'wrap', gap: 12 }}>
        <div>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Gestion des cours</h2>
          <p style={{ color: 'var(--text-mid)', fontSize: '.88rem' }}>{cours.length} cours</p>
        </div>
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <select value={filtreSem} onChange={e => setFiltreSem(e.target.value)} style={selectStyle}>
            <option value="">Tous les semestres</option>
            {semestres.map(s => <option key={s.id} value={s.id}>{s.libelle}</option>)}
          </select>
          <select value={filtreDept} onChange={e => setFiltreDept(e.target.value)} style={selectStyle}>
            <option value="">Tous les départements</option>
            {departements.map(d => <option key={d.id} value={d.id}>{d.nom}</option>)}
          </select>
          <select value={filtreGroupe} onChange={e => setFiltreGrp(e.target.value)} style={selectStyle}>
            <option value="">Tous les groupes de TD</option>
            {groupes.map(g => <option key={g.id} value={g.id}>{g.libelle}</option>)}
          </select>
          {user.role === 'admin' && (
            <button className="btn btn-primary" onClick={openCreate}><Icons.Plus />Créer</button>
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
                  <th>Groupes TD</th><th>Crédits</th><th>Inscrits</th><th>Statut</th>
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
                    <td style={{ fontSize: '.82rem' }}>{c.groupes || '—'}</td>
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

            {modal === 'create' && (
              <>
                {/* ----- Planification d'une première séance ----- */}
                <div style={{ borderTop: '1px solid var(--cream-dark)', paddingTop: 12 }}>
                  <strong style={{ color: 'var(--navy)', fontSize: '.92rem' }}>Première séance (optionnel)</strong>
                  <p style={{ color: 'var(--text-light)', fontSize: '.8rem', margin: '4px 0 10px' }}>
                    Salle, date et horaire. Les conflits (salle, enseignant, groupe) sont vérifiés automatiquement.
                  </p>
                  <div className="form-group">
                    <label>Salle</label>
                    <input placeholder="Rechercher une salle (nom, bâtiment)…" value={salleSearch}
                           onChange={e => setSalleSearch(e.target.value)} style={{ marginBottom: 6 }} />
                    <select value={form.salle_id || ''} onChange={e => setForm(f => ({ ...f, salle_id: e.target.value }))}>
                      <option value="">— Aucune salle —</option>
                      {sallesFiltrees.map(s => (
                        <option key={s.id} value={s.id}>{s.nom} · {s.batiment || '—'} · {s.capacite} pl. · {s.type_salle}</option>
                      ))}
                    </select>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
                    <div className="form-group">
                      <label>Date</label>
                      <input type="date" value={form.date_specifique || ''} onChange={e => setForm(f => ({ ...f, date_specifique: e.target.value }))} />
                    </div>
                    <div className="form-group">
                      <label>Heure début</label>
                      <select value={form.heure_debut || ''} onChange={e => setForm(f => ({ ...f, heure_debut: e.target.value }))}>
                        <option value="">—</option>
                        {heures.map(h => <option key={h} value={h}>{h}</option>)}
                      </select>
                    </div>
                    <div className="form-group">
                      <label>Heure fin</label>
                      <select value={form.heure_fin || ''} onChange={e => setForm(f => ({ ...f, heure_fin: e.target.value }))}>
                        <option value="">—</option>
                        {heures.map(h => <option key={h} value={h}>{h}</option>)}
                      </select>
                    </div>
                  </div>
                </div>

                {/* ----- Inscription des groupes de TD ----- */}
                <div style={{ borderTop: '1px solid var(--cream-dark)', paddingTop: 12 }}>
                  <strong style={{ color: 'var(--navy)', fontSize: '.92rem' }}>Groupes de TD inscrits</strong>
                  <p style={{ color: 'var(--text-light)', fontSize: '.8rem', margin: '4px 0 10px' }}>
                    Cochez une ou plusieurs classes : tous leurs étudiants seront inscrits.
                  </p>
                  <div style={{ maxHeight: 140, overflowY: 'auto', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 6, border: '1px solid var(--cream-dark)', borderRadius: 'var(--radius)', padding: 10 }}>
                    {groupes.map(g => {
                      const checked = (form.groupes_td || []).map(String).includes(String(g.id));
                      return (
                        <label key={g.id} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: '.82rem', cursor: 'pointer' }}>
                          <input type="checkbox" checked={checked} onChange={() => toggleGroupe(g.id)} />
                          {g.libelle}
                        </label>
                      );
                    })}
                  </div>
                </div>

                {/* ----- Inscription d'étudiants individuels ----- */}
                <div style={{ borderTop: '1px solid var(--cream-dark)', paddingTop: 12 }}>
                  <strong style={{ color: 'var(--navy)', fontSize: '.92rem' }}>Étudiants individuels</strong>
                  <input placeholder="Rechercher un étudiant (nom, numéro)…" value={etuSearch}
                         onChange={e => setEtuSearch(e.target.value)} style={{ margin: '8px 0 6px' }} />
                  {etudiantsFiltres.length > 0 && (
                    <div style={{ border: '1px solid var(--cream-dark)', borderRadius: 'var(--radius)', overflow: 'hidden', marginBottom: 8 }}>
                      {etudiantsFiltres.map(e => (
                        <div key={e.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '6px 10px', fontSize: '.82rem', borderBottom: '1px solid var(--cream)' }}>
                          <span>{e.prenom} {e.nom} <em style={{ color: 'var(--text-light)' }}>({e.numero_etudiant} · {e.niveau})</em></span>
                          <button type="button" className="btn btn-outline btn-sm" onClick={() => addEtudiant(e.id)}>Ajouter</button>
                        </div>
                      ))}
                    </div>
                  )}
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                    {(form.etudiants || []).map(id => {
                      const e = etudiantParId(id);
                      return (
                        <span key={id} className="badge badge-info" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                          {e ? `${e.prenom} ${e.nom}` : `#${id}`}
                          <span style={{ cursor: 'pointer', fontWeight: 700 }} onClick={() => removeEtudiant(id)}>×</span>
                        </span>
                      );
                    })}
                  </div>
                </div>
              </>
            )}
          </div>
        </Modal>
      )}
    </div>
  );
}
