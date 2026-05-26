function RegisterPage({ onRegistered, onSwitchToLogin }) {
  const { useState, useEffect } = React;

  const [role, setRole]             = useState('etudiant');
  const [nom, setNom]               = useState('');
  const [prenom, setPrenom]         = useState('');
  const [email, setEmail]           = useState('');
  const [mdp, setMdp]               = useState('');
  const [mdpConfirm, setMdpConfirm] = useState('');
  const [niveau, setNiveau]         = useState('L1');
  const [grade, setGrade]           = useState('Maître de conférences');
  const [departementId, setDepId]   = useState('');
  const [departements, setDeps]     = useState([]);
  const [error, setError]           = useState('');
  const [success, setSuccess]       = useState('');
  const [loading, setLoading]       = useState(false);

  // Charger la liste des départements pour le select
  useEffect(() => {
    (async () => {
      try {
        const { ok, data } = await api.getDepartements();
        if (ok && Array.isArray(data)) setDeps(data);
      } catch (_) { /* silencieux */ }
    })();
  }, []);

  const handleSubmit = async e => {
    e.preventDefault();
    setError('');
    setSuccess('');

    if (mdp !== mdpConfirm) {
      setError('Les deux mots de passe ne correspondent pas.');
      return;
    }
    if (mdp.length < 8) {
      setError('Le mot de passe doit contenir au moins 8 caractères.');
      return;
    }

    const payload = {
      role,
      nom: nom.trim(),
      prenom: prenom.trim(),
      email: email.trim().toLowerCase(),
      mot_de_passe: mdp,
      departement_id: departementId || null,
    };
    if (role === 'etudiant')   payload.niveau = niveau;
    if (role === 'enseignant') payload.grade  = grade;

    setLoading(true);
    const { ok, data } = await api.register(payload);
    setLoading(false);

    if (ok && data.succes) {
      setSuccess('Compte créé avec succès ! Vous pouvez maintenant vous connecter.');
      if (onRegistered) onRegistered(data);
    } else {
      const msg = data.erreurs ? data.erreurs.join(' ')
                : (data.erreur || 'Inscription impossible.');
      setError(msg);
    }
  };

  return (
    <div className="login-page">
      <div className="login-left">
        <div className="login-logo">Smart<span>Campus</span></div>
        <div className="login-tagline">Rejoignez la plateforme</div>
        <h1 className="login-headline">
          Créer votre compte<br /><em>en quelques secondes</em>
        </h1>
        <p className="login-desc">
          Étudiants et enseignants peuvent s'inscrire directement pour accéder
          à leurs cours, leur emploi du temps et leurs notes.
        </p>
      </div>
      <div className="login-right">
        <h2>Inscription</h2>
        <p className="sub">Renseignez vos informations pour créer un compte.</p>

        {error   && <Alert type="error">{error}</Alert>}
        {success && <Alert type="success">{success}</Alert>}

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>Je suis</label>
            <div style={{ display: 'flex', gap: 8 }}>
              <button
                type="button"
                className={`btn ${role === 'etudiant' ? 'btn-primary' : 'btn-secondary'}`}
                onClick={() => setRole('etudiant')}
                style={{ flex: 1 }}
              >Étudiant</button>
              <button
                type="button"
                className={`btn ${role === 'enseignant' ? 'btn-primary' : 'btn-secondary'}`}
                onClick={() => setRole('enseignant')}
                style={{ flex: 1 }}
              >Enseignant</button>
            </div>
          </div>

          <div style={{ display: 'flex', gap: 12 }}>
            <div className="form-group" style={{ flex: 1 }}>
              <label>Prénom</label>
              <input type="text" value={prenom}
                     onChange={e => setPrenom(e.target.value)}
                     placeholder="Prénom" required />
            </div>
            <div className="form-group" style={{ flex: 1 }}>
              <label>Nom</label>
              <input type="text" value={nom}
                     onChange={e => setNom(e.target.value)}
                     placeholder="Nom" required />
            </div>
          </div>

          <div className="form-group">
            <label>Adresse email</label>
            <input type="email" value={email}
                   onChange={e => setEmail(e.target.value)}
                   placeholder="prenom.nom@univ.fr" required />
          </div>

          <div className="form-group">
            <label>Département (optionnel)</label>
            <select value={departementId}
                    onChange={e => setDepId(e.target.value)}>
              <option value="">— Aucun —</option>
              {departements.map(d => (
                <option key={d.id} value={d.id}>{d.nom}</option>
              ))}
            </select>
          </div>

          {role === 'etudiant' ? (
            <div className="form-group">
              <label>Niveau</label>
              <select value={niveau} onChange={e => setNiveau(e.target.value)}>
                <option value="L1">L1</option>
                <option value="L2">L2</option>
                <option value="L3">L3</option>
                <option value="M1">M1</option>
                <option value="M2">M2</option>
                <option value="ING1">ING1</option>
                <option value="ING2">ING2</option>
                <option value="ING3">ING3</option>
              </select>
            </div>
          ) : (
            <div className="form-group">
              <label>Grade</label>
              <select value={grade} onChange={e => setGrade(e.target.value)}>
                <option>Maître de conférences</option>
                <option>Professeur</option>
                <option>Vacataire</option>
                <option>ATER</option>
                <option>Doctorant</option>
              </select>
            </div>
          )}

          <div style={{ display: 'flex', gap: 12 }}>
            <div className="form-group" style={{ flex: 1 }}>
              <label>Mot de passe</label>
              <input type="password" value={mdp}
                     onChange={e => setMdp(e.target.value)}
                     placeholder="8 caractères min." required minLength={8} />
            </div>
            <div className="form-group" style={{ flex: 1 }}>
              <label>Confirmation</label>
              <input type="password" value={mdpConfirm}
                     onChange={e => setMdpConfirm(e.target.value)}
                     placeholder="••••••••" required minLength={8} />
            </div>
          </div>

          <button type="submit" className="btn btn-primary btn-full"
                  disabled={loading} style={{ marginTop: 8 }}>
            {loading ? 'Création du compte…' : 'Créer mon compte'}
          </button>

          <p style={{ textAlign: 'center', marginTop: 16, fontSize: 14 }}>
            Déjà inscrit ?{' '}
            <a href="#" onClick={e => { e.preventDefault(); onSwitchToLogin && onSwitchToLogin(); }}
               style={{ color: 'var(--color-primary, #1f4e79)', fontWeight: 600 }}>
              Se connecter
            </a>
          </p>
        </form>
      </div>
    </div>
  );
}
