// js/router.js — Routeur SPA léger basé sur le hash (#)

const Router = {

  _routes: {},
  _currentPage: null,
  _onNavigate: null,

  /**
   * Définir les routes disponibles
   * Ex : Router.define({ dashboard: 'dashboard', etudiants: 'etudiants', ... })
   */
  define(routes) {
    this._routes = routes;
    return this;
  },

  /**
   * Enregistrer le callback appelé à chaque changement de page
   * Le callback reçoit le nom de la page courante
   */
  onNavigate(callback) {
    this._onNavigate = callback;
    return this;
  },

  /**
   * Démarrer le routeur : écoute les changements de hash
   */
  start() {
    window.addEventListener('hashchange', () => this._resolve());
    this._resolve(); // résoudre la route initiale
  },

  /**
   * Naviguer vers une page
   */
  navigate(page) {
    if (this._routes[page]) {
      window.location.hash = '#' + page;
    } else {
      window.location.hash = '#dashboard';
    }
  },

  /**
   * Récupérer la page courante
   */
  current() {
    return this._currentPage;
  },

  /**
   * Résoudre la route depuis le hash courant
   */
  _resolve() {
    const hash = window.location.hash.replace('#', '') || 'dashboard';
    const page = this._routes[hash] ? hash : 'dashboard';

    if (page === this._currentPage) return; // pas de re-rendu inutile

    this._currentPage = page;
    if (typeof this._onNavigate === 'function') {
      this._onNavigate(page);
    }
  },

  /**
   * Obtenir toutes les pages disponibles pour un rôle donné
   */
  pagesForRole(role) {
    const rolePages = {
      admin:      ['dashboard','etudiants','enseignants','cours','emploi','notes','presences','messages','salles'],
      enseignant: ['dashboard','etudiants','cours','emploi','notes','presences','messages'],
      etudiant:   ['dashboard','cours','inscriptions','emploi','notes','presences','messages'],
    };
    return rolePages[role] ?? ['dashboard'];
  },

  /**
   * Vérifier si une page est accessible pour un rôle
   */
  canAccess(page, role) {
    return this.pagesForRole(role).includes(page);
  },
};