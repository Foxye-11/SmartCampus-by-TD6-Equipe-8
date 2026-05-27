// js/api.js — Couche d'appel API centralisée

const API_BASE = '/api';

const api = {
  async _request(method, endpoint, body = null, params = {}) {
    const url = new URL(API_BASE + endpoint, window.location.origin);
    Object.entries(params).forEach(([k, v]) => { if (v != null && v !== '') url.searchParams.append(k, v); });
    const options = { method, headers: { 'Content-Type': 'application/json' }, credentials: 'include' };
    if (body) options.body = JSON.stringify(body);
    const res = await fetch(url, options);
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, data };
  },

  get:    (e, p) => api._request('GET',    e, null, p || {}),
  post:   (e, b) => api._request('POST',   e, b),
  put:    (e, b) => api._request('PUT',    e, b),
  delete: (e)    => api._request('DELETE', e),

  // Auth
  login:  (email, mot_de_passe) => api.post('/login.php', { email, mot_de_passe }),
  logout: ()                     => api.post('/logout.php'),

  // Étudiants
  getEtudiants:  (f) => api.get('/etudiants.php', f),
  getEtudiant:   (id) => api.get('/etudiants.php', { id }),
  createEtudiant:(d) => api.post('/etudiants.php', d),
  updateEtudiant:(id, d) => api.put(`/etudiants.php?id=${id}`, d),
  deleteEtudiant:(id) => api.delete(`/etudiants.php?id=${id}`),

  // Enseignants
  getEnseignants:  () => api.get('/enseignants.php'),
  getEnseignant:   (id) => api.get('/enseignants.php', { id }),
  createEnseignant:(d) => api.post('/enseignants.php', d),
  updateEnseignant:(id, d) => api.put(`/enseignants.php?id=${id}`, d),
  deleteEnseignant:(id) => api.delete(`/enseignants.php?id=${id}`),
  coursEnseignant: (id) => api.get('/enseignants.php', { id, action: 'cours' }),

  // Cours
  getCours:     (p) => api.get('/cours.php', p),
  getCour:      (id) => api.get('/cours.php', { id }),
  createCours:  (d) => api.post('/cours.php', d),
  updateCours:  (id, d) => api.put(`/cours.php?id=${id}`, d),
  deleteCours:  (id) => api.delete(`/cours.php?id=${id}`),
  getSemestres: () => api.get('/cours.php', { action: 'semestres' }),
  getDepartements: () => api.get('/cours.php', { action: 'departements' }),
  getSessionsCours: (id) => api.get('/cours.php', { id, action: 'sessions' }),
  getCoursGroupes:  (id) => api.get('/cours.php', { id, action: 'groupes' }),

  // Inscriptions
  coursDisponibles: (eid) => api.get('/inscriptions.php', { action: 'disponibles', id: eid }),
  coursSuivis:      (eid) => api.get('/inscriptions.php', { action: 'suivis',      id: eid }),
  etudiantsDuCours: (cid) => api.get('/inscriptions.php', { action: 'etudiants',   id: cid }),
  inscrire:         (etudiant_id, cours_id) => api.post('/inscriptions.php', { etudiant_id, cours_id }),
  annulerInscription: (id) => api.delete(`/inscriptions.php?id=${id}`),

  // Notes
  notesEtudiant: (id, cours_id) => api.get('/notes.php', { action: 'etudiant', id, cours_id }),
  notesDuCours:  (id) => api.get('/notes.php', { action: 'cours', id }),
  bulletin:      (id, semestre_id) => api.get('/notes.php', { action: 'bulletin', id, semestre_id }),
  saisirNote:    (d) => api.post('/notes.php', d),
  modifierNote:  (id, d) => api.put(`/notes.php?id=${id}`, d),
  supprimerNote: (id) => api.delete(`/notes.php?id=${id}`),
  verrouillerNotes: (id) => api.put(`/notes.php?action=verrouiller&id=${id}`, {}),

  // Présences
  presencesEtudiant: (id, cours_id) => api.get('/presences.php', { action: 'etudiant', id, cours_id }),
  presencesSession:  (id) => api.get('/presences.php', { action: 'session', id }),
  resumeAbsences:    (id) => api.get('/presences.php', { action: 'resume',  id }),
  alertesAbsences:   (id) => api.get('/presences.php', { action: 'alertes', id }),
  enregistrerPresences: (d) => api.post('/presences.php', d),
  modifierPresence:  (id, statut) => api.put(`/presences.php?id=${id}`, { statut }),

  // Références (groupes de TD)
  getGroupesTD:   (niveau) => api.get('/references.php', { action: 'groupes_td', niveau }),
  creerGroupeTD:  (d)      => api.post('/references.php', d),

  // Emploi du temps
  getEmploiDuTemps: (f) => api.get('/emploi_du_temps.php', f || {}),
  creerSession:     (d) => api.post('/emploi_du_temps.php', d),
  supprimerSession: (id) => api.delete(`/emploi_du_temps.php?id=${id}`),

  // Messages
  reception:    (page) => api.get('/messages.php', { action: 'reception', page }),
  envoyes:      (page) => api.get('/messages.php', { action: 'envoyes',   page }),
  lireMessage:  (id)   => api.get('/messages.php', { action: 'lire',      id }),
  contacts:     ()     => api.get('/messages.php', { action: 'contacts' }),
  nonLus:       ()     => api.get('/messages.php', { action: 'non_lus' }),
  envoyerMsg:   (d)    => api.post('/messages.php', d),
  supprimerMsg: (id)   => api.delete(`/messages.php?id=${id}`),

  // Notifications
  getNotifications:  () => api.get('/notifications.php'),
  nonLuesNotifs:     () => api.get('/notifications.php', { action: 'non_lues' }),
  compterNotifs:     () => api.get('/notifications.php', { action: 'compter' }),
  marquerLue:        (id) => api.put(`/notifications.php?id=${id}`, {}),
  toutMarquerLues:   () => api.put('/notifications.php?action=tout_lire', {}),

  // Salles
  getSalles:  () => api.get('/salles.php'),
  getSalle:   (id) => api.get('/salles.php', { id }),
  creerSalle: (d) => api.post('/salles.php', d),
  modifierSalle: (id, d) => api.put(`/salles.php?id=${id}`, d),
  supprimerSalle: (id) => api.delete(`/salles.php?id=${id}`),

  // Relevé
  releveData: (etudiant_id, semestre_id) => api.get('/releve.php', { action: 'donnees', etudiant_id, semestre_id }),
  relevePDF:  (etudiant_id, semestre_id) => {
    const url = `/api/releve.php?etudiant_id=${etudiant_id}&semestre_id=${semestre_id}`;
    window.open(url, '_blank');
  },
};
