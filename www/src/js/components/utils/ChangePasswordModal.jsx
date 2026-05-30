// ChangePasswordModal.jsx — Changement de mot de passe en libre-service.
// Démontre une validation côté client (miroir des règles serveur) avant envoi.
function ChangePasswordModal({ onClose }) {
  const { useState, useMemo } = React;
  const [ancien, setAncien]             = useState('');
  const [nouveau, setNouveau]           = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [touched, setTouched]           = useState(false);
  const [error, setError]               = useState('');
  const [success, setSuccess]           = useState('');
  const [loading, setLoading]           = useState(false);

  // ── Règles de robustesse, évaluées à chaque frappe (feedback immédiat) ──
  const regles = useMemo(() => ([
    { ok: nouveau.length >= 8,            label: 'Au moins 8 caractères' },
    { ok: /[A-Za-z]/.test(nouveau),       label: 'Au moins une lettre' },
    { ok: /\d/.test(nouveau),             label: 'Au moins un chiffre' },
    { ok: nouveau !== '' && nouveau !== ancien, label: 'Différent de l\'actuel' },
    { ok: confirmation !== '' && nouveau === confirmation, label: 'Confirmation identique' },
  ]), [nouveau, confirmation, ancien]);

  const toutValide = ancien !== '' && regles.every(r => r.ok);

  const handleSave = async () => {
    setTouched(true);
    setError('');
    setSuccess('');
    if (!toutValide) {
      setError('Veuillez corriger les champs en rouge avant de valider.');
      return;
    }
    setLoading(true);
    const res = await api.changerMotDePasse(ancien, nouveau, confirmation);
    setLoading(false);
    if (res.ok && res.data.succes) {
      setSuccess('Mot de passe modifié avec succès.');
      setAncien(''); setNouveau(''); setConfirmation(''); setTouched(false);
      setTimeout(onClose, 1200);
    } else {
      setError(res.data.erreur || 'Échec de la modification.');
    }
  };

  return (
    <Modal
      title="Changer mon mot de passe"
      onClose={onClose}
      footer={<>
        <button className="btn btn-outline" onClick={onClose}>Fermer</button>
        <button className="btn btn-primary" onClick={handleSave} disabled={loading || !toutValide}>
          {loading ? 'Modification…' : 'Modifier'}
        </button>
      </>}
    >
      {error && <Alert type="error">{error}</Alert>}
      {success && <Alert type="success">{success}</Alert>}
      <div style={{ display: 'grid', gap: 14 }}>
        <div className="form-group">
          <label>Mot de passe actuel</label>
          <input type="password" value={ancien} autoComplete="current-password"
                 onChange={e => setAncien(e.target.value)}
                 onBlur={() => setTouched(true)}
                 style={touched && ancien === '' ? { borderColor: 'var(--danger)' } : undefined} />
        </div>
        <div className="form-group">
          <label>Nouveau mot de passe</label>
          <input type="password" value={nouveau} autoComplete="new-password"
                 onChange={e => setNouveau(e.target.value)} />
        </div>
        <div className="form-group">
          <label>Confirmer le nouveau mot de passe</label>
          <input type="password" value={confirmation} autoComplete="new-password"
                 onChange={e => setConfirmation(e.target.value)} />
        </div>

        {/* Checklist en direct des règles de robustesse */}
        <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: 4 }}>
          {regles.map((r, i) => (
            <li key={i} style={{
              fontSize: '.82rem',
              color: r.ok ? 'var(--success)' : 'var(--text-light)',
              display: 'flex', alignItems: 'center', gap: 6,
            }}>
              <span>{r.ok ? '✓' : '○'}</span> {r.label}
            </li>
          ))}
        </ul>
      </div>
    </Modal>
  );
}
