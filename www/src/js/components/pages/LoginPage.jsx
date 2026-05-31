function LoginPage({ onLogin }) {
  const { useState } = React;
  const [email, setEmail]     = useState('');
  const [mdp, setMdp]         = useState('');
  const [error, setError]     = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async e => {
    e.preventDefault();
    setError('');
    setLoading(true);
    const { ok, data } = await api.login(email, mdp);
    setLoading(false);
    if (ok && data.succes) onLogin(data);
    else setError(data.erreur || 'Identifiants incorrects.');
  };

  return (
    <div className="login-page">
      <div className="login-left">
        <div className="login-logo">
          <span className="login-logo-text">Smart<span>Campus</span></span>
          <img src="assets/logo.webp" alt="SmartCampus" className="login-logo-img" />
        </div>
        <div className="login-tagline">Gérer · Apprendre · Réussir</div>
        <h1 className="login-headline">
          La gestion académique<br /><em>de notre époque</em>
        </h1>
        <p className="login-desc">
          Une plateforme centralisée pour les étudiants, enseignants
          et l'administration. Accédez à vos cours, notes et emploi du temps
          en quelques secondes.
        </p>
      </div>
      <div className="login-right">
        <h2>Connexion</h2>
        <p className="sub">Entrez vos identifiants pour acceder a la plateforme.</p>
        {error && <Alert type="error">{error}</Alert>}
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>Adresse email</label>
            <input
              type="email" value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="prenom.nom@univ.fr"
              required autoFocus
            />
          </div>
          <div className="form-group">
            <label>Mot de passe</label>
            <input
              type="password" value={mdp}
              onChange={e => setMdp(e.target.value)}
              placeholder="••••••••" required
            />
          </div>
          <button type="submit" className="btn btn-primary btn-full" disabled={loading} style={{ marginTop: 8 }}>
            {loading ? 'Connexion…' : 'Se connecter'}
          </button>
        </form>
      </div>
    </div>
  );
}
