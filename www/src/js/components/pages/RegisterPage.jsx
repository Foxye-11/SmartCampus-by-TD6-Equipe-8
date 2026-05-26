function RegisterPage(props) {
  const { useState, useEffect } = React;
  const onRegistered    = props.onRegistered;
  const onSwitchToLogin = props.onSwitchToLogin;

  const [role, setRole]             = useState('etudiant');
  const [nom, setNom]               = useState('');
  const [prenom, setPrenom]         = useState('');
  const [email, setEmail]           = useState('');
  const [mdp, setMdp]               = useState('');
  const [mdpConfirm, setMdpConfirm] = useState('');
  const [niveau, setNiveau]         = useState('L1');
  const [grade, setGrade]           = useState('Maitre de conferences');
  const [departementId, setDepId]   = useState('');
  const [departements, setDeps]     = useState([]);
  const [error, setError]           = useState('');
  const [success, setSuccess]       = useState('');
  const [loading, setLoading]       = useState(false);

  // Calcule l'URL d'un endpoint API en s'adaptant au contexte WAMP
  // (sous-dossier dans htdocs) ou racine.
  function apiUrl(endpoint) {
    let p = window.location.pathname; // ex: /SmartCampus.../www/src/index.html
    // On retire le nom de fichier final
    const lastSlash = p.lastIndexOf('/');
    if (lastSlash >= 0) p = p.substring(0, lastSlash + 1);
    // Si on est dans .../src/ on remonte d'un cran pour arriver dans .../www/
    if (p.endsWith('/src/')) p = p.substring(0, p.length - 4);
    return p + 'api/' + endpoint;
  }

  // Charge la liste des departements (silencieux si endpoint protege)
  useEffect(() => {
    (async () => {
      try {
        if (api && typeof api.getDepartements === 'function') {
          const res = await api.getDepartements();
          if (res && res.ok && Array.isArray(res.data)) setDeps(res.data);
        } else {
          // Fallback fetch direct
          const url = apiUrl('cours.php?action=departements');
          const r = await fetch(url, { credentials: 'include' });
          if (r.ok) {
            const d = await r.json();
            if (Array.isArray(d)) setDeps(d);
          }
        }
      } catch (_) { /* champ optionnel */ }
    })();
  }, []);

  // Inscription : essaie api.register, sinon fetch direct avec URL calculee
  async function callRegister(payload) {
    if (api && typeof api.register === 'function') {
      return await api.register(payload);
    }
    const url = apiUrl('register.php');
    console.log('[register] POST URL =', url);
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload),
    });
    let data = {};
    try { data = await res.json(); } catch (_) { data = {}; }
    return { ok: res.ok, status: res.status, data };
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSuccess('');

    if (mdp !== mdpConfirm) {
      setError('Les deux mots de passe ne correspondent pas.');
      return;
    }
    if (mdp.length < 8) {
      setError('Le mot de passe doit contenir au moins 8 caracteres.');
      return;
    }

    const payload = {
      role: role,
      nom: nom.trim(),
      prenom: prenom.trim(),
      email: email.trim().toLowerCase(),
      mot_de_passe: mdp,
      departement_id: departementId || null,
    };
    if (role === 'etudiant')   payload.niveau = niveau;
    if (role === 'enseignant') payload.grade  = grade;

    setLoading(true);
    try {
      const res = await callRegister(payload);
      console.log('[register] response:', res);
      if (res.ok && res.data && res.data.succes) {
        setSuccess('Compte cree avec succes ! Vous pouvez maintenant vous connecter.');
        setTimeout(() => { if (onRegistered) onRegistered(res.data); }, 1500);
      } else {
        const d = res.data || {};
        const msg = (d.erreurs && d.erreurs.join(' ')) || d.erreur ||
                    ('Inscription impossible (HTTP ' + (res.status || '?') + ').');
        setError(msg);
      }
    } catch (err) {
      console.error('[register] error:', err);
      setError('Erreur reseau ou serveur : ' + (err && err.message ? err.message : err));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="login-page">
      <div className="login-left">
        <div className="login-logo">Smart<span>Campus</span></div>
        <div className="login-tagline">Rejoignez la plateforme</div>
        <h1 className="login-headline">Creer votre compte<br/><em>en quelques secondes</em></h1>
        <p className="login-desc">Etudiants et enseignants peuvent s'inscrire directement.</p>
      </div>
      <div className="login-right">
        <h2>Inscription</h2>
        <p className="sub">Renseignez vos informations pour creer un compte.</p>

        {error   && <Alert type="error">{error}</Alert>}
        {success && <Alert type="success">{success}</Alert>}

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>Je suis</label>
            <div style={{ display:'flex', gap:8 }}>
              <button type="button"
                      className={'btn ' + (role==='etudiant'?'btn-primary':'btn-secondary')}
                      onClick={() => setRole('etudiant')}
                      style={{ flex:1 }}>Etudiant</button>
              <button type="button"
                      className={'btn ' + (role==='enseignant'?'btn-primary':'btn-secondary')}
                      onClick={() => setRole('enseignant')}
                      style={{ flex:1 }}>Enseignant</button>
            </div>
          </div>

          <div style={{ display:'flex', gap:12 }}>
            <div className="form-group" style={{ flex:1 }}>
              <label>Prenom</label>
              <input type="text" value={prenom}
                     onChange={(e)=>setPrenom(e.target.value)}
                     placeholder="Prenom" required />
            </div>
            <div className="form-group" style={{ flex:1 }}>
              <label>Nom</label>
              <input type="text" value={nom}
                     onChange={(e)=>setNom(e.target.value)}
                     placeholder="Nom" required />
            </div>
          </div>

          <div className="form-group">
            <label>Adresse email</label>
            <input type="email" value={email}
                   onChange={(e)=>setEmail(e.target.value)}
                   placeholder="prenom.nom@univ.fr" required />
          </div>

          <div className="form-group">
            <label>Departement (optionnel)</label>
            <select value={departementId}
                    onChange={(e)=>setDepId(e.target.value)}>
              <option value="">-- Aucun --</option>
              {departements.map((d) => (
                <option key={d.id} value={d.id}>{d.nom}</option>
              ))}
            </select>
          </div>

          {role === 'etudiant' ? (
            <div className="form-group">
              <label>Niveau</label>
              <select value={niveau} onChange={(e)=>setNiveau(e.target.value)}>
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
              <select value={grade} onChange={(e)=>setGrade(e.target.value)}>
                <option>Maitre de conferences</option>
                <option>Professeur</option>
                <option>Vacataire</option>
                <option>ATER</option>
                <option>Doctorant</option>
              </select>
            </div>
          )}

          <div style={{ display:'flex', gap:12 }}>
            <div className="form-group" style={{ flex:1 }}>
              <label>Mot de passe</label>
              <input type="password" value={mdp}
                     onChange={(e)=>setMdp(e.target.value)}
                     placeholder="8 caracteres min." required minLength={8} />
            </div>
            <div className="form-group" style={{ flex:1 }}>
              <label>Confirmation</label>
              <input type="password" value={mdpConfirm}
                     onChange={(e)=>setMdpConfirm(e.target.value)}
                     placeholder="********" required minLength={8} />
            </div>
          </div>

          <button type="submit" className="btn btn-primary btn-full"
                  disabled={loading} style={{ marginTop:8 }}>
            {loading ? 'Creation du compte...' : 'Creer mon compte'}
          </button>

          <p style={{ textAlign:'center', marginTop:16, fontSize:14 }}>
            Deja inscrit ?{' '}
            <a href="#" onClick={(e)=>{e.preventDefault(); onSwitchToLogin && onSwitchToLogin();}}
               style={{ color:'#1f4e79', fontWeight:600, textDecoration:'underline' }}>
              Se connecter
            </a>
          </p>
        </form>
      </div>
    </div>
  );
}
