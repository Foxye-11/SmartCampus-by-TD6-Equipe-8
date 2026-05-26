function EmptyState({ icon = '📭', message = 'Aucune donnée disponible' }) {
  return (
    <div className="empty-state">
      <div className="icon">{icon}</div>
      <p>{message}</p>
    </div>
  );
}