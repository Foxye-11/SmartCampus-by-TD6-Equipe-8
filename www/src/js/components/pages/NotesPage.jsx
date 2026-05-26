function NotesPage({ user }) {
  const { useState, useEffect } = React;
  const [semestres, setSem]     = useState([]);
  const [semId, setSemId]       = useState('');
  const [bulletin, setBulletin] = useState(null);
  const [cours, setCours]       = useState([]);
  const [selCours, setSelCours] = useState('');
  const [notesCours, setNotesC] = useState([]);
  const [inscrits, setInscrits] = useState([]);
  const [modal, setModal]       = useState(null);
  const [form, setForm]         = useState({});
  const [error, setError]       = useState('');
  const [loading, setLoading]   = useState(false);

  useEffect(() => {
    api.getSemestres().then(r => {
      const sem = r.data || [];
      setSem(sem);
      if (sem.length) setSemId(String(sem[0].id));
    });
    if (user.role !== 'etudiant') {
      const req = user.role === 'enseignant' ? api.coursEnseignant(user.enseignant_id) : api.getCours();
      req.then(r => setCours(r.data || []));
    }
  }, []);

  useEffect(() => {
    if (user.role === 'etudiant' && semId) {
      setLoading(true);
      api.bulletin(user.etudiant_id, semId).then(r => { setBulletin(r.data); setLoading(false); });
    }
  }, [semId]);

  useEffect(() => {
    if (selCours) {
      api.notesDuCours(selCours).then(r => setNotesC(r.data || []));
      api.etudiantsDuCours(selCours).then(r => setInscrits(r.data || []));
    }
  }, [selCours]);

  const getMentionBadge = m => ({ 'Très Bien': 'badge-success', 'Bien': 'badge-success', 'Assez Bien': 'badge-info', 'Passable': 'badge-warning', 'Insuffisant': 'badge-danger', '-': 'badge-navy' }[m] || 'badge-navy');

  const handleSaisir = async () => {
    setError('');
    const res = modal?.id ? await api.modifierNote(modal.id, form) : await api.saisirNote(form);
    if (res.ok && res.data.succes) {
      setModal(null);
      if (selCours) api.notesDuCours(selCours).then(r => setNotesC(r.data || []));
    } else setError((res.data.erreurs || [res.data.erreur]).join(', '));
  };

  const typesEval = ['examen','partiel','tp','projet','controle'];

  return (
    <div className="fade-in">
      <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)', marginBottom: 24 }}>Notes</h2>

      {user.role === 'etudiant' && (
        <>
          <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginBottom: 20, flexWrap: 'wrap' }}>
            <label style={{ fontSize: '.85rem', fontWeight: 600, color: 'var(--text-mid)' }}>Semestre :</label>
            <select value={semId} onChange={e => setSemId(e.target.value)}
              style={{ padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem' }}>
              {semestres.map(s => <option key={s.id} value={s.id}>{s.libelle}</option>)}
            </select>
            {semId && bulletin && (
              <button className="btn btn-outline btn-sm" onClick={() => api.relevePDF(user.etudiant_id, semId)}>
                <Icons.Download />Relevé PDF
              </button>
            )}
          </div>
          {loading ? <Spinner /> : !bulletin ? null : (
            <>
              <div className="bulletin-moyenne">
                <div className="value">{bulletin.moyenne_generale ?? '—'}/20</div>
                <div className="label">Moyenne générale du semestre</div>
                {bulletin.moyenne_generale !== null && (
                  <span className={`badge ${getMentionBadge(bulletin.mention_generale)} bulletin-mention`}>{bulletin.mention_generale}</span>
                )}
              </div>
              <div className="card">
                <div className="table-wrapper">
                  <table>
                    <thead><tr><th>Code</th><th>Cours</th><th>Crédits</th><th>Moyenne</th><th>Mention</th></tr></thead>
                    <tbody>
                      {(bulletin.cours || []).map(c => (
                        <tr key={c.cours_id}>
                          <td><code style={{ background: 'var(--cream)', padding: '2px 6px', borderRadius: 4, fontSize: '.82rem' }}>{c.code}</code></td>
                          <td><strong>{c.intitule}</strong></td>
                          <td><span className="badge badge-navy">{c.credits}</span></td>
                          <td><strong>{c.moyenne ?? '—'}/20</strong></td>
                          <td><span className={`badge ${getMentionBadge(c.mention)}`}>{c.mention}</span></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </>
          )}
        </>
      )}

      {(user.role === 'enseignant' || user.role === 'admin') && (
        <>
          <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginBottom: 20, flexWrap: 'wrap' }}>
            <select value={selCours} onChange={e => setSelCours(e.target.value)}
              style={{ padding: '8px 12px', border: '1.5px solid var(--cream-dark)', borderRadius: 'var(--radius)', fontFamily: 'var(--font-body)', fontSize: '.88rem', minWidth: 260 }}>
              <option value="">— Sélectionner un cours —</option>
              {cours.map(c => <option key={c.id} value={c.id}>{c.code} — {c.intitule}</option>)}
            </select>
            {selCours && (
              <button className="btn btn-primary btn-sm" onClick={() => { setForm({ cours_id: selCours }); setError(''); setModal({}); }}>
                <Icons.Plus />Saisir une note
              </button>
            )}
            {selCours && user.role === 'admin' && (
              <button className="btn btn-outline btn-sm" onClick={async () => { if (confirm('Verrouiller définitivement ?')) await api.verrouillerNotes(selCours); }}>
                🔒 Verrouiller
              </button>
            )}
          </div>
          {selCours && (
            <div className="card">
              {notesCours.length === 0 ? <EmptyState icon="📝" message="Aucune note saisie." /> : (
                <div className="table-wrapper">
                  <table>
                    <thead><tr><th>Étudiant</th><th>Type</th><th>Note</th><th>Coefficient</th><th>Commentaire</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                      {notesCours.map(n => (
                        <tr key={n.id}>
                          <td><strong>{n.etudiant}</strong><br /><small style={{ color: 'var(--text-light)' }}>{n.numero_etudiant}</small></td>
                          <td><span className="badge badge-navy">{n.type_evaluation}</span></td>
                          <td><strong>{n.valeur}/20</strong></td>
                          <td>×{n.coefficient}</td>
                          <td style={{ color: 'var(--text-mid)', maxWidth: 160 }}>{n.commentaire || '—'}</td>
                          <td style={{ color: 'var(--text-light)', fontSize: '.8rem' }}>{new Date(n.date_saisie).toLocaleDateString('fr-FR')}</td>
                          <td>
                            <div style={{ display: 'flex', gap: 6 }}>
                              <button className="btn btn-outline btn-sm" onClick={() => { setForm({ ...n, inscription_id: n.inscription_id }); setError(''); setModal({ id: n.id }); }}><Icons.Edit /></button>
                              <button className="btn btn-danger btn-sm" onClick={async () => { if (confirm('Supprimer ?')) { await api.supprimerNote(n.id); api.notesDuCours(selCours).then(r => setNotesC(r.data || [])); } }}><Icons.Trash /></button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}
        </>
      )}

      {modal !== null && (
        <Modal title={modal?.id ? 'Modifier la note' : 'Saisir une note'} onClose={() => setModal(null)}
          footer={<><button className="btn btn-outline" onClick={() => setModal(null)}>Annuler</button><button className="btn btn-primary" onClick={handleSaisir}>Enregistrer</button></>}>
          {error && <Alert type="error">{error}</Alert>}
          <div style={{ display: 'grid', gap: 14 }}>
            {!modal?.id && (
              <div className="form-group">
                <label>Étudiant</label>
                <select value={form.inscription_id || ''} onChange={e => setForm(f => ({ ...f, inscription_id: e.target.value }))}>
                  <option value="">— Choisir —</option>
                  {inscrits.map(i => <option key={i.inscription_id} value={i.inscription_id}>{i.etudiant}</option>)}
                </select>
              </div>
            )}
            <div className="form-group">
              <label>Type d'évaluation</label>
              <select value={form.type_evaluation || ''} onChange={e => setForm(f => ({ ...f, type_evaluation: e.target.value }))}>
                <option value="">— Choisir —</option>
                {typesEval.map(t => <option key={t} value={t}>{t}</option>)}
              </select>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 12 }}>
              <div className="form-group"><label>Note (/20)</label><input type="number" min="0" max="20" step="0.5" value={form.valeur || ''} onChange={e => setForm(f => ({ ...f, valeur: e.target.value }))} /></div>
              <div className="form-group"><label>Coefficient</label><input type="number" min="0.5" step="0.5" value={form.coefficient || 1} onChange={e => setForm(f => ({ ...f, coefficient: e.target.value }))} /></div>
            </div>
            <div className="form-group"><label>Commentaire</label><textarea value={form.commentaire || ''} onChange={e => setForm(f => ({ ...f, commentaire: e.target.value }))} rows={2} /></div>
          </div>
        </Modal>
      )}
    </div>
  );
}