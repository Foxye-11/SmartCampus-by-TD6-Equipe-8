function Dashboard({ user }) {
  const { useState, useEffect, useMemo } = React;
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);

  // Bornes de l'année scolaire 2025-2026
  const YEAR_START = '2025-09-01';
  const YEAR_END   = '2026-06-30';
  const today      = new Date().toISOString().slice(0, 10);

  // ─── Chargement ────────────────────────────────────────────────────────
  useEffect(() => {
    const safe = (p) => p.then(r => r).catch(() => ({ data: null, ok: false }));

    const load = async () => {
      try {
        const role = user.role;

        // 1) Commun à tous
        const baseCalls = [
          safe(api.getNotifications()),
          // Sessions à venir (jusqu'à la fin de l'année scolaire) :
          // sert au calcul des heures restantes ET à la liste "prochaines séances".
          safe(api.getEmploiDuTemps({
            annee_scolaire: '2025-2026',
            date_debut: today,
            date_fin:   YEAR_END,
          })),
          // Sessions passées de l'année — pour les heures déjà effectuées
          safe(api.getEmploiDuTemps({
            annee_scolaire: '2025-2026',
            date_debut: YEAR_START,
            date_fin:   today,
          })),
          safe(api.nonLus()),
        ];

        // 2) Par rôle
        let roleCalls = [];
        if (role === 'etudiant') {
          roleCalls = [
            safe(api.getEtudiant(user.etudiant_id)),
            safe(api.coursSuivis(user.etudiant_id)),
            safe(api.resumeAbsences(user.etudiant_id)),
            safe(api.getSemestres()),
          ];
        } else if (role === 'enseignant') {
          roleCalls = [
            safe(api.getEnseignant(user.enseignant_id)),
            safe(api.coursEnseignant(user.enseignant_id)),
          ];
        } else { // admin
          roleCalls = [
            safe(api.getEtudiants({})),
            safe(api.getEnseignants()),
            safe(api.getCours()),
            safe(api.getSalles()),
          ];
        }

        const all = await Promise.all([...baseCalls, ...roleCalls]);
        const [notifs, futures, passees, msgsNonLus, ...rest] = all;

        const upcoming = Array.isArray(futures.data) ? futures.data : [];
        const past     = Array.isArray(passees.data) ? passees.data : [];

        // Heures cumulées = somme des durées de séances (en minutes / 60)
        const sumHours = (sessions) => {
          let mins = 0;
          for (const s of sessions) {
            const d = (s.heure_debut || '').slice(0, 5);
            const f = (s.heure_fin   || '').slice(0, 5);
            if (!d || !f) continue;
            const [hd, md] = d.split(':').map(Number);
            const [hf, mf] = f.split(':').map(Number);
            mins += (hf * 60 + mf) - (hd * 60 + md);
          }
          return Math.round(mins / 60);
        };

        // Bulletin du semestre courant (si étudiant)
        let bulletin = null;
        if (role === 'etudiant') {
          const semestres = Array.isArray(rest[3]?.data) ? rest[3].data : [];
          // S2 si on est entre fev et août, sinon S1
          const m = new Date().getMonth() + 1;
          const sem = semestres.find(s => s.libelle.startsWith(m >= 2 && m <= 8 ? 'S2' : 'S1'))
                    || semestres[0];
          if (sem) {
            const r = await safe(api.bulletin(user.etudiant_id, sem.id));
            bulletin = { semestre: sem.libelle, ...(r.data || {}) };
          }
        }

        setData({
          role,
          notifs: Array.isArray(notifs.data) ? notifs.data : [],
          msgsNonLus: msgsNonLus.data?.non_lus ?? msgsNonLus.data ?? 0,
          upcoming,
          past,
          heuresRestantes: sumHours(upcoming),
          heuresEffectuees: sumHours(past),
          profile: rest[0]?.data || null,
          extra:   rest,
          bulletin,
        });
      } catch (e) { console.error(e); }
      setLoading(false);
    };
    load();
  }, [user]);

  if (loading) return <Spinner />;
  if (!data)   return <EmptyState message="Impossible de charger le tableau de bord." />;

  const initials = ((user.prenom || '')[0] || '') + ((user.nom || '')[0] || '');
  const notifIcons = {
    note_publiee: '🎓', nouveau_message: '✉️',
    alerte_absence: '⚠️', nouveau_inscrit: '📋', default: '🔔',
  };

  // ─── Format date helper ──────────────────────────────────────────────
  const fmtDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit' });
  };

  // ─── Carte profil ────────────────────────────────────────────────────
  const ProfileCard = () => {
    const p = data.profile || {};
    let infos = [];
    if (data.role === 'etudiant') {
      infos = [
        ['Numéro étudiant', p.numero_etudiant],
        ['Niveau',          p.niveau],
        ['Groupe TD',       p.groupe_td || (p.groupe_td_id ? `#${p.groupe_td_id}` : '—')],
        ['Département',     p.departement || '—'],
        ['École',           p.ecole || '—'],
        ['Année scolaire',  p.annee_scolaire || '2025-2026'],
      ];
    } else if (data.role === 'enseignant') {
      infos = [
        ['Grade',        p.grade || '—'],
        ['Département',  p.departement || '—'],
        ['École',        p.ecole || '—'],
        ['Email',        p.email || user.email],
      ];
    } else {
      infos = [
        ['Rôle',  'Administrateur système'],
        ['Email', user.email],
        ['Année scolaire en cours', '2025-2026'],
      ];
    }
    return (
      <div className="card profile-card">
        <div className="profile-avatar">{initials.toUpperCase()}</div>
        <div className="profile-info">
          <div className="profile-name">{user.prenom} {user.nom}</div>
          <div className="profile-role">
            {data.role === 'admin' ? 'Administrateur' : data.role === 'enseignant' ? 'Enseignant' : 'Étudiant'}
          </div>
          <div className="profile-grid">
            {infos.map(([k, v]) => (
              <div key={k} className="profile-field">
                <div className="profile-field-label">{k}</div>
                <div className="profile-field-value">{v || <em style={{ color: 'var(--text-light)' }}>—</em>}</div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  };

  // ─── Stats tiles ─────────────────────────────────────────────────────
  const Tile = ({ label, value, sub, tone }) => (
    <div className={`stat-card ${tone || ''}`}>
      <div className="stat-label">{label}</div>
      <div className="stat-value">{value}</div>
      {sub && <div className="stat-sub">{sub}</div>}
    </div>
  );

  let tiles = null;
  if (data.role === 'etudiant') {
    const moy = data.bulletin?.moyenne_generale;
    const mention = data.bulletin?.mention_generale;
    const absences = data.extra[2]?.data || [];
    const totalAbs = absences.reduce((s, c) => s + Number(c.absents || 0), 0);
    const alertes  = absences.filter(c => c.alerte).length;
    tiles = (
      <>
        <Tile label="Moyenne du semestre" value={moy != null ? `${moy}/20` : '—'}
              sub={mention && mention !== '-' ? mention : (data.bulletin?.semestre || '')} tone="gold" />
        <Tile label="Heures restantes" value={`${data.heuresRestantes} h`}
              sub={`d'ici le 30/06/2026 · ${data.heuresEffectuees} h effectuées`} tone="green" />
        <Tile label="Absences" value={totalAbs}
              sub={alertes > 0 ? `${alertes} cours en alerte` : 'Aucune alerte'}
              tone={alertes > 0 ? 'red' : ''} />
        <Tile label="Messages non lus" value={data.msgsNonLus} />
      </>
    );
  } else if (data.role === 'enseignant') {
    const cours = data.extra[1]?.data || [];
    const inscrits = cours.reduce((s, c) => s + Number(c.inscrits || 0), 0);
    tiles = (
      <>
        <Tile label="Mes cours" value={cours.length} tone="gold" />
        <Tile label="Étudiants suivis" value={inscrits} />
        <Tile label="Heures restantes" value={`${data.heuresRestantes} h`}
              sub={`d'ici le 30/06/2026 · ${data.heuresEffectuees} h effectuées`} tone="green" />
        <Tile label="Messages non lus" value={data.msgsNonLus} />
      </>
    );
  } else {
    const [etu, ens, crs, sal] = data.extra.map(r => Array.isArray(r?.data) ? r.data : []);
    tiles = (
      <>
        <Tile label="Étudiants" value={etu.length} tone="gold" />
        <Tile label="Enseignants" value={ens.length} />
        <Tile label="Cours actifs" value={crs.length} tone="green" />
        <Tile label="Salles" value={sal.length} />
        <Tile label="Heures programmées" value={`${data.heuresRestantes + data.heuresEffectuees} h`}
              sub={`${data.heuresEffectuees} h passées · ${data.heuresRestantes} h à venir`} />
        <Tile label="Messages non lus" value={data.msgsNonLus} />
      </>
    );
  }

  // ─── Prochaines séances (5) ─────────────────────────────────────────
  const NextSessions = () => {
    const next = data.upcoming.slice(0, 5);
    return (
      <div className="card">
        <div className="card-header"><span className="card-title">Prochaines séances</span></div>
        {next.length === 0 ? (
          <div style={{ padding: 22, color: 'var(--text-light)', fontStyle: 'italic', fontSize: '.88rem' }}>
            Aucune séance prévue d'ici la fin de l'année.
          </div>
        ) : next.map(s => (
          <div key={s.session_id} style={{
            padding: '12px 22px', borderBottom: '1px solid var(--cream-dark)',
            display: 'flex', justifyContent: 'space-between', gap: 16, alignItems: 'center',
          }}>
            <div>
              <div style={{ fontSize: '.92rem', fontWeight: 600, color: 'var(--navy)' }}>
                {s.cours}
              </div>
              <div style={{ fontSize: '.78rem', color: 'var(--text-light)', marginTop: 2 }}>
                {s.salle && <>📍 {s.salle}{s.batiment ? ` · ${s.batiment}` : ''}</>}
                {data.role !== 'enseignant' && s.enseignant && <> · 👤 {s.enseignant}</>}
              </div>
            </div>
            <div style={{ textAlign: 'right', minWidth: 110 }}>
              <div style={{ fontSize: '.85rem', fontWeight: 600, color: 'var(--navy)' }}>
                {fmtDate(s.date || s.date_specifique)}
              </div>
              <div style={{ fontSize: '.78rem', color: 'var(--text-mid)' }}>
                {s.heure_debut?.slice(0,5)}–{s.heure_fin?.slice(0,5)}
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  };

  // ─── Notifications récentes ─────────────────────────────────────────
  const NotifList = () => {
    const recent = data.notifs.slice(0, 5);
    return (
      <div className="card">
        <div className="card-header"><span className="card-title">Notifications récentes</span></div>
        {recent.length === 0 ? (
          <div style={{ padding: 22, color: 'var(--text-light)', fontStyle: 'italic', fontSize: '.88rem' }}>
            Aucune notification.
          </div>
        ) : recent.map(n => (
          <div key={n.id} style={{
            padding: '12px 22px', borderBottom: '1px solid var(--cream-dark)',
            display: 'flex', gap: 12, alignItems: 'flex-start',
            background: !n.lue ? 'rgba(201, 168, 76, .07)' : 'transparent',
          }}>
            <span style={{ fontSize: '1.1rem' }}>{notifIcons[n.type] || notifIcons.default}</span>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: '.85rem', color: 'var(--text-dark)' }}>{n.contenu}</div>
              <div style={{ fontSize: '.72rem', color: 'var(--text-light)', marginTop: 2 }}>
                {new Date(n.date_creation).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })}
              </div>
            </div>
            {!n.lue && <span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--gold)', flexShrink: 0, marginTop: 6 }} />}
          </div>
        ))}
      </div>
    );
  };

  // ─── Render ──────────────────────────────────────────────────────────
  return (
    <div className="fade-in">
      <div style={{ marginBottom: 24 }}>
        <h2 style={{ fontFamily: 'var(--font-display)', fontSize: '1.5rem', color: 'var(--navy)' }}>
          Bonjour, {user.prenom} 👋
        </h2>
        <p style={{ color: 'var(--text-mid)', marginTop: 4 }}>
          {data.role === 'etudiant'  && 'Voici un aperçu de votre scolarité.'}
          {data.role === 'enseignant' && 'Voici un aperçu de votre activité d\'enseignement.'}
          {data.role === 'admin'     && 'Voici un aperçu de l\'établissement.'}
        </p>
      </div>

      <ProfileCard />

      <div className="stat-grid" style={{ marginTop: 24 }}>
        {tiles}
      </div>

      <div className="dashboard-cols">
        <NextSessions />
        <NotifList />
      </div>
    </div>
  );
}
