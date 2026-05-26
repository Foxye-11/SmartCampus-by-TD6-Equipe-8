// js/auth.js — Gestion de l'état d'authentification côté client

const Auth = {

  // Clé de stockage session (sessionStorage = effacé à la fermeture du tab)
  _KEY: 'sc_user',

  /**
   * Sauvegarder l'utilisateur après connexion réussie
   */
  save(userData) {
    sessionStorage.setItem(this._KEY, JSON.stringify(userData));
  },

  /**
   * Récupérer l'utilisateur connecté (null si non connecté)
   */
  get() {
    try {
      const raw = sessionStorage.getItem(this._KEY);
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  },

  /**
   * Supprimer la session locale (appelé au logout)
   */
  clear() {
    sessionStorage.removeItem(this._KEY);
  },

  /**
   * Vérifier si un utilisateur est connecté localement
   */
  isLoggedIn() {
    return this.get() !== null;
  },

  /**
   * Récupérer le rôle de l'utilisateur connecté
   */
  getRole() {
    return this.get()?.role ?? null;
  },

  /**
   * Vérifier si l'utilisateur a l'un des rôles donnés
   */
  hasRole(...roles) {
    return roles.includes(this.getRole());
  },

  /**
   * Récupérer l'ID métier selon le rôle
   * (etudiant_id pour un étudiant, enseignant_id pour un enseignant)
   */
  getMetierId() {
    const user = this.get();
    if (!user) return null;
    if (user.role === 'etudiant')   return user.etudiant_id   ?? null;
    if (user.role === 'enseignant') return user.enseignant_id ?? null;
    return null;
  },
};