const { useState, useEffect } = React;

const PAGE_TITLES = {
  dashboard:    'Tableau de bord',
  etudiants:    'Étudiants',
  enseignants:  'Enseignants',
  cours:        'Cours',
  inscriptions: 'Inscriptions',
  emploi:       'Emploi du temps',
  notes:        'Notes',
  presences:    'Présences',
  messages:     'Messagerie',
  salles:       'Salles',
  statistiques: 'Statistiques',
};

function App() {
  const [user, setUser]         = useState(() => Auth.get());
  const [page, setPage]         = useState(() => Router.current() || 'dashboard');
  const [notifs, setNotifs]     = useState([]);
  const [unreadNotifs, setUN]   = useState(0);
  const [unreadMessages, setUM] = useState(0);
  const [showAccount, setShowAccount] = useState(false);

  useEffect(() => {
    if (!user) return;
    Router.define(Object.fromEntries(Router.pagesForRole(user.role).map(p => [p, p])))
      .onNavigate(p => { if (Router.canAccess(p, user.role)) setPage(p); })
      .start();
  }, [user]);

  useEffect(() => {
    if (!user) return;
    const loadNotifs = async () => {
      const [nRes, mRes] = await Promise.all([api.getNotifications(), api.nonLus()]);
      const n = nRes.data || [];
      setNotifs(n);
      setUN(n.filter(x => !x.lue).length);
      setUM(mRes.data?.non_lus || 0);
    };
    loadNotifs();
    const interval = setInterval(loadNotifs, 30000);
    return () => clearInterval(interval);
  }, [user]);

  const handleLogin = data => {
    Auth.save(data);
    setUser(data);
    setPage('dashboard');
    Router.navigate('dashboard');
  };

  const handleLogout = async () => {
    await api.logout();
    Auth.clear();
    setUser(null);
    setPage('dashboard');
  };

  const navigate = p => {
    if (!Router.canAccess(p, user?.role)) return;
    Router.navigate(p);
    setPage(p);
  };

  const handleMarkAllRead = async () => {
    await api.toutMarquerLues();
    setNotifs(n => n.map(x => ({ ...x, lue: 1 })));
    setUN(0);
  };

  if (!user) return <LoginPage onLogin={handleLogin} />;

  const renderPage = () => {
    switch (page) {
      case 'dashboard':    return <Dashboard user={user} />;
      case 'etudiants':    return <EtudiantsPage user={user} />;
      case 'enseignants':  return <EnseignantsPage />;
      case 'cours':        return <CoursPage user={user} />;
      case 'inscriptions': return <InscriptionsPage user={user} />;
      case 'emploi':       return <EmploiDuTempsPage user={user} />;
      case 'notes':        return <NotesPage user={user} />;
      case 'presences':    return <PresencesPage user={user} />;
      case 'messages':     return <MessagesPage user={user} onUnreadChange={setUM} />;
      case 'salles':       return <SallesPage />;
      case 'statistiques': return <StatistiquesPage user={user} />;
      default:             return <Dashboard user={user} />;
    }
  };

  return (
    <div className="app-layout">
      <Sidebar user={user} page={page} navigate={navigate} unreadMessages={unreadMessages} onLogout={handleLogout} />
      <div className="main-content">
        <Topbar title={PAGE_TITLES[page] || 'SmartCampus'} unreadNotifs={unreadNotifs} notifs={notifs} onMarkAllRead={handleMarkAllRead} onOpenAccount={() => setShowAccount(true)} />
        <div className="page-body">{renderPage()}</div>
      </div>
      {showAccount && <ChangePasswordModal onClose={() => setShowAccount(false)} />}
    </div>
  );
}

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<App />);