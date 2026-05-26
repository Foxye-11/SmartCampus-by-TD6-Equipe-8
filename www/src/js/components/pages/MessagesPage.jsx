function MessagesPage({ user, onUnreadChange }) {
  const { useState, useEffect } = React;
  const [tab, setTab]        = useState('reception');
  const [messages, setMsgs]  = useState([]);
  const [selected, setSel]   = useState(null);
  const [contacts, setCtcts] = useState([]);
  const [modal, setModal]    = useState(false);
  const [form, setForm]      = useState({});
  const [error, setError]    = useState('');
  const [loading, setLoad]   = useState(true);

  const load = async () => {
    setLoad(true);
    const res = tab === 'reception' ? await api.reception(1) : await api.envoyes(1);
    setMsgs(tab === 'reception' ? (res.data?.messages || []) : (res.data || []));
    setLoad(false);
    if (tab === 'reception' && onUnreadChange) {
      const nr = await api.nonLus();
      onUnreadChange(nr.data?.non_lus || 0);
    }
  };

  useEffect(() => { load(); }, [tab]);
  useEffect(() => { api.contacts().then(r => setCtcts(r.data || [])); }, []);

  const handleOpen = async msg => {
    const res = await api.lireMessage(msg.id);
    setSel(res.data?.message || msg);
    if (!msg.lu && tab === 'reception') load();
  };

  const handleEnvoyer = async () => {
    setError('');
    const res = await api.envoyerMsg(form);
    if (res.ok && res.data.succes) { setModal(false); setForm({}); load(); }
    else setError((res.data.erreurs || [res.data.erreur]).join(', '));
  };

  const initials = nom => nom ? nom.split(' ').map(n => n[0] || '').join('').toUpperCase().slice(0, 2) : '?';
  const formatDate = iso => iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';

  return (
    <div className="fade-in" style={{ display: 'grid', gridTemplateColumns: selected ? '1fr 1fr' : '1fr', gap: 20 }}>
      <div>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
          <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem', color: 'var(--navy)' }}>Messagerie</h2>
          <button className="btn btn-primary btn-sm" onClick={() => { setForm({}); setError(''); setModal(true); }}><Icons.Send />Nouveau</button>
        </div>

        <div style={{ display: 'flex', gap: 0, marginBottom: 16, background: 'var(--cream-dark)', borderRadius: 'var(--radius)', padding: 3, width: 'fit-content' }}>
          {[['reception','Réception'],['envoyes','Envoyés']].map(([k, l]) => (
            <button key={k} className={`btn btn-sm ${tab === k ? 'btn-primary' : ''}`} style={{ borderRadius: 6 }} onClick={() => { setTab(k); setSel(null); }}>{l}</button>
          ))}
        </div>

        <div className="card">
          {loading ? <Spinner /> : messages.length === 0 ? <EmptyState icon="📭" message="Aucun message." /> : (
            <div className="msg-list">
              {messages.map(m => (
                <div key={m.id} className={`msg-item ${!m.lu && tab === 'reception' ? 'unread' : ''} ${selected?.id === m.id ? 'active' : ''}`} onClick={() => handleOpen(m)}>
                  <div className="msg-avatar">{initials(tab === 'reception' ? m.expediteur : m.destinataire)}</div>
                  <div className="msg-meta">
                    <div className="msg-from">{tab === 'reception' ? m.expediteur : m.destinataire}</div>
                    <div className="msg-subject">{m.sujet}</div>
                  </div>
                  <div className="msg-date">{formatDate(m.date_envoi)}</div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {selected && (
        <div className="card fade-in" style={{ height: 'fit-content' }}>
          <div className="card-header">
            <span className="card-title" style={{ fontSize: '.95rem' }}>{selected.sujet}</span>
            <button className="btn btn-outline btn-sm" onClick={() => setSel(null)}>×</button>
          </div>
          <div className="card-body">
            <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginBottom: 16 }}>
              <div className="msg-avatar">{initials(selected.expediteur)}</div>
              <div>
                <div style={{ fontWeight: 600, fontSize: '.9rem' }}>{selected.expediteur}</div>
                <div style={{ fontSize: '.78rem', color: 'var(--text-light)' }}>→ {selected.destinataire} · {formatDate(selected.date_envoi)}</div>
              </div>
            </div>
            <div style={{ background: 'var(--cream)', borderRadius: 'var(--radius)', padding: '14px 16px', fontSize: '.9rem', lineHeight: 1.7, color: 'var(--text-dark)', whiteSpace: 'pre-wrap' }}>
              {selected.contenu}
            </div>
            <div style={{ marginTop: 12 }}>
              <button className="btn btn-outline btn-sm" onClick={() => { setForm({ destinataire_id: selected.expediteur_id, sujet: 'Re: ' + selected.sujet }); setError(''); setModal(true); }}>↩ Répondre</button>
            </div>
          </div>
        </div>
      )}

      {modal && (
        <Modal title="Nouveau message" onClose={() => setModal(false)}
          footer={<><button className="btn btn-outline" onClick={() => setModal(false)}>Annuler</button><button className="btn btn-primary" onClick={handleEnvoyer}><Icons.Send />Envoyer</button></>}>
          {error && <Alert type="error">{error}</Alert>}
          <div style={{ display: 'grid', gap: 14 }}>
            <div className="form-group">
              <label>Destinataire</label>
              <select value={form.destinataire_id || ''} onChange={e => setForm(f => ({ ...f, destinataire_id: e.target.value }))}>
                <option value="">— Choisir —</option>
                {contacts.map(c => <option key={c.id} value={c.id}>{c.nom_complet} ({c.role})</option>)}
              </select>
            </div>
            <div className="form-group"><label>Sujet</label><input value={form.sujet || ''} onChange={e => setForm(f => ({ ...f, sujet: e.target.value }))} /></div>
            <div className="form-group"><label>Message</label><textarea value={form.contenu || ''} onChange={e => setForm(f => ({ ...f, contenu: e.target.value }))} rows={5} /></div>
          </div>
        </Modal>
      )}
    </div>
  );
}