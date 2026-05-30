function Sidebar({ user, page, navigate, unreadMessages, onLogout }) {
  const navItems = [
    { key: 'dashboard',    label: 'Tableau de bord', icon: <Icons.Dashboard />,  roles: ['admin','enseignant','etudiant'] },
    { key: 'etudiants',    label: 'Étudiants',        icon: <Icons.Students />,   roles: ['admin','enseignant'] },
    { key: 'enseignants',  label: 'Enseignants',       icon: <Icons.Teachers />,   roles: ['admin'] },
    { key: 'cours',        label: 'Cours',             icon: <Icons.Courses />,    roles: ['admin','enseignant','etudiant'] },
    { key: 'inscriptions', label: 'Inscriptions',      icon: <Icons.Attendance />, roles: ['etudiant'] },
    { key: 'emploi',       label: 'Emploi du temps',   icon: <Icons.Schedule />,   roles: ['admin','enseignant','etudiant'] },
    { key: 'notes',        label: 'Notes',             icon: <Icons.Grades />,     roles: ['admin','enseignant','etudiant'] },
    { key: 'presences',    label: 'Présences',         icon: <Icons.Attendance />, roles: ['admin','enseignant','etudiant'] },
    { key: 'messages',     label: 'Messagerie',        icon: <Icons.Messages />,   roles: ['admin','enseignant','etudiant'], badge: unreadMessages },
    { key: 'salles',       label: 'Salles',            icon: <Icons.Rooms />,      roles: ['admin'] },
    { key: 'statistiques', label: 'Statistiques',      icon: <Icons.Dashboard />,  roles: ['admin'] },
  ].filter(i => i.roles.includes(user.role));

  const initials = ((user.prenom || '')[0] || '') + ((user.nom || '')[0] || '');

  return (
    <aside className="sidebar">
      <div className="sidebar-header">
        <div className="sidebar-brand">Smart<span>Campus</span></div>
        <div className="sidebar-role">
          {user.role === 'admin' ? 'Administrateur' : user.role === 'enseignant' ? 'Enseignant' : 'Étudiant'}
        </div>
      </div>

      <div className="sidebar-user">
        <div className="sidebar-avatar">{initials.toUpperCase()}</div>
        <div className="sidebar-user-info">
          <div className="sidebar-user-name">{user.prenom} {user.nom}</div>
          <div className="sidebar-user-email">{user.email || ''}</div>
        </div>
      </div>

      <nav className="sidebar-nav">
        {navItems.map(item => (
          <div
            key={item.key}
            className={`sidebar-item ${page === item.key ? 'active' : ''}`}
            onClick={() => navigate(item.key)}
          >
            {item.icon}
            <span>{item.label}</span>
            {item.badge > 0 && <span className="sidebar-badge">{item.badge}</span>}
          </div>
        ))}
      </nav>

      <div className="sidebar-footer">
        <div className="sidebar-item" onClick={onLogout}>
          <Icons.Logout /><span>Déconnexion</span>
        </div>
      </div>
    </aside>
  );
}