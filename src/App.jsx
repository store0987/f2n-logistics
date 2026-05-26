import React, { useState } from 'react';
import { API_BASE_URL } from './api';
import DossiersView from './components/DossiersView';
import FacturationForm from './components/FacturationForm';
import FacturesView from './components/FacturesView';
import ClientsView from './components/ClientsView';
import DeboursView from './components/DeboursView';
import Auth from './components/Auth';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  LineChart, Line, PieChart, Pie, Cell
} from 'recharts';
import {
  LayoutDashboard,
  FileText,
  Users,
  Settings,
  Ship,
  Plane,
  Truck,
  Search,
  Bell,
  ArrowUpRight,
  ArrowDownRight,
  Plus,
  DollarSign,
  LogOut
} from 'lucide-react';

function App() {
  const [user, setUser] = useState(() => {
    const saved = localStorage.getItem('f2n_user');
    return saved ? JSON.parse(saved) : null;
  });

  const [activeTab, setActiveTab] = useState(() => {
    return localStorage.getItem('activeTab') || 'dashboard';
  });

  React.useEffect(() => {
    localStorage.setItem('activeTab', activeTab);
  }, [activeTab]);

  const [dossiers, setDossiers] = useState([]);
  const [factures, setFactures] = useState([]);
  const [debours, setDebours] = useState([]);
  const [factureViewMode, setFactureViewMode] = useState('list');
  const [editFactureData, setEditFactureData] = useState(null);

  const fetchDashboardData = async () => {
    if (!user) return;
    try {
      const respDossiers = await fetch(`${API_BASE_URL}/api/dossiers`);
      const dataDossiers = await respDossiers.json();
      setDossiers(dataDossiers);

      const respFactures = await fetch(`${API_BASE_URL}/api/factures`);
      const dataFactures = await respFactures.json();
      setFactures(dataFactures);

      const respDebours = await fetch(`${API_BASE_URL}/api/debours`);
      const dataDebours = await respDebours.json();
      setDebours(dataDebours);
    } catch (error) {
      console.error('Erreur lors du chargement des données du dashboard:', error);
    }
  };

  React.useEffect(() => {
    if (user) {
      fetchDashboardData();
      // Réinitialiser les modes formulaires
      setFactureViewMode('list');
      setEditFactureData(null);
    }
  }, [activeTab]);

  const handleLogout = () => {
    localStorage.removeItem('f2n_user');
    setUser(null);
    setActiveTab('dashboard');
  };

  if (!user) return <Auth onLogin={setUser} />;

  const formatCurrency = (amount) => new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';

  const caMois = factures.filter(f => f.statut === 'Validée').reduce((sum, f) => sum + f.totalTtc, 0);
  const dossiersEnCours = dossiers.length;
  const facturesImpayees = factures.filter(f => f.statut === 'Proforma').reduce((sum, f) => sum + f.totalTtc, 0);
  const totalDeboursEnAttente = debours.filter(d => d.statut === 'En attente').reduce((sum, d) => sum + parseFloat(d.montant), 0);

  // Préparation des données pour les graphiques
  const monthlyData = React.useMemo(() => {
    const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
    const currentYear = new Date().getFullYear();
    const data = months.map(name => ({ name, total: 0 }));

    factures.filter(f => f.statut === 'Validée' && f.date).forEach(f => {
      const d = new Date(f.date);
      if (d.getFullYear() === currentYear) {
        data[d.getMonth()].total += parseFloat(f.totalTtc);
      }
    });
    return data;
  }, [factures]);

  const transportData = React.useMemo(() => {
    const counts = dossiers.reduce((acc, d) => {
      const mode = d.modeTransport || 'Maritime';
      acc[mode] = (acc[mode] || 0) + 1;
      return acc;
    }, {});
    return Object.keys(counts).map(name => ({ name, value: counts[name] }));
  }, [dossiers]);

  const topClientsData = React.useMemo(() => {
    const totals = factures.filter(f => f.statut === 'Validée').reduce((acc, f) => {
      const name = f.client_nom || 'Inconnu';
      acc[name] = (acc[name] || 0) + parseFloat(f.totalTtc);
      return acc;
    }, {});
    return Object.keys(totals)
      .map(name => ({ name, total: totals[name] }))
      .sort((a, b) => b.total - a.total)
      .slice(0, 5);
  }, [factures]);

  const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

  
  const styles = {
    appContainer: {
      display: 'flex',
      height: '100vh',
      width: '100vw',
      overflow: 'hidden',
      backgroundColor: '#0b0f19',
      color: '#f8fafc',
      fontFamily: 'system-ui, sans-serif'
    },
    sidebar: {
      width: '260px',
      backgroundColor: '#111827',
      borderRight: '1px solid rgba(255, 255, 255, 0.1)',
      display: 'flex',
      flexDirection: 'column'
    },
    sidebarHeader: {
      padding: '24px',
      display: 'flex',
      alignItems: 'center',
      gap: '12px',
      borderBottom: '1px solid rgba(255, 255, 255, 0.1)'
    },
    navMenu: {
      padding: '16px',
      display: 'flex',
      flexDirection: 'column',
      gap: '8px',
      flex: 1
    },
    mainContent: {
      flex: 1,
      display: 'flex',
      flexDirection: 'column',
      overflowY: 'auto'
    },
    topHeader: {
      height: '70px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: '0 32px',
      backgroundColor: 'rgba(11, 15, 25, 0.8)',
      borderBottom: '1px solid rgba(255, 255, 255, 0.1)',
      position: 'sticky',
      top: 0,
      zIndex: 10
    }
  };

  return (
    <div style={styles.appContainer}>
      {/* Sidebar */}
      <aside style={styles.sidebar}>
        <div style={styles.sidebarHeader}>
          <Ship color="#3b82f6" size={28} />
          <span style={{ fontSize: '1.25rem', fontWeight: '700', color: '#3b82f6' }}>F2N Logistics</span>
        </div>

        <nav style={styles.navMenu}>
          <a className={`nav-item ${activeTab === 'dashboard' ? 'active' : ''}`} onClick={() => setActiveTab('dashboard')}>
            <LayoutDashboard size={20} />
            Tableau de Bord
          </a>
          <a className={`nav-item ${activeTab === 'dossiers' ? 'active' : ''}`} onClick={() => setActiveTab('dossiers')}>
            <Truck size={20} />
            Dossiers / Opérations
          </a>
          <a className={`nav-item ${activeTab === 'debours' ? 'active' : ''}`} onClick={() => setActiveTab('debours')}>
            <DollarSign size={20} />
            Gestion Débours
          </a>
          <a className={`nav-item ${activeTab === 'factures' ? 'active' : ''}`} onClick={() => setActiveTab('factures')}>
            <FileText size={20} />
            Facturation
          </a>
          <a className={`nav-item ${activeTab === 'clients' ? 'active' : ''}`} onClick={() => setActiveTab('clients')}>
            <Users size={20} />
            Clients & Partenaires
          </a>
          <div style={{ flex: 1 }}></div>
          <a className={`nav-item ${activeTab === 'settings' ? 'active' : ''}`} onClick={() => setActiveTab('settings')}>
            <Settings size={20} />
            Paramètres
          </a>
          <a className="nav-item logout-item" onClick={handleLogout}>
            <LogOut size={20} />
            Déconnexion
          </a>
        </nav>
      </aside>

      {/* Main Content */}
      <main style={styles.mainContent}>
        {/* Top Header */}
        <header style={styles.topHeader}>
          <div className="search-bar">
            <Search size={18} />
            <input type="text" placeholder="Rechercher un dossier (ex: B/L, Facture)..." />
          </div>

          <div className="user-profile">
            <Bell size={20} style={{ color: 'var(--text-secondary)', cursor: 'pointer', marginRight: '16px' }} />
            <div style={{ textAlign: 'right', display: 'flex', flexDirection: 'column' }}>
              <span style={{ fontSize: '0.875rem', fontWeight: '600', color: '#f8fafc' }}>{user.username}</span>
              <span style={{ fontSize: '0.75rem', color: '#94a3b8' }}>{user.email}</span>
            </div>
            <div className="avatar">A</div>
          </div>
        </header>

        {/* Views */}
        {activeTab === 'dashboard' && (
          <div className="dashboard-page">
            <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <h1 className="page-title">Aperçu Global</h1>
                <p className="page-subtitle">Performances et statuts des dossiers en cours</p>
              </div>
              <button className="btn btn-primary" onClick={() => setActiveTab('dossiers')}>
                <Plus size={18} />
                Nouveau Dossier
              </button>
            </div>

            {/* Stats Cards */}
            <div className="stats-grid">
              <div className="stat-card">
                <div className="stat-info">
                  <span className="stat-label">Chiffre d'Affaires (Mois)</span>
                  <span className="stat-value">{formatCurrency(caMois)}</span>
                  <span className="stat-trend" style={{ color: 'var(--text-secondary)' }}>
                    Basé sur les factures validées
                  </span>
                </div>
                <div className="stat-icon" style={{ color: 'var(--accent-primary)' }}>
                  <FileText size={24} />
                </div>
              </div>

              <div className="stat-card">
                <div className="stat-info">
                  <span className="stat-label">Dossiers en Cours</span>
                  <span className="stat-value">{dossiersEnCours}</span>
                  <span className="stat-trend" style={{ color: 'var(--text-secondary)' }}>
                    Total dossiers enregistrés
                  </span>
                </div>
                <div className="stat-icon" style={{ color: 'var(--accent-secondary)' }}>
                  <Ship size={24} />
                </div>
              </div>

              <div className="stat-card">
                <div className="stat-info">
                  <span className="stat-label">Encours Facturé (Proforma)</span>
                  <span className="stat-value">{formatCurrency(facturesImpayees)}</span>
                  <span className="stat-trend" style={{ color: 'var(--text-secondary)' }}>
                    Proformas en attente
                  </span>
                </div>
                <div className="stat-icon" style={{ color: 'var(--accent-danger)' }}>
                  <Users size={24} />
                </div>
              </div>

              <div className="stat-card">
                <div className="stat-info">
                  <span className="stat-label">Débours en Attente</span>
                  <span className="stat-value">{formatCurrency(totalDeboursEnAttente)}</span>
                  <span className="stat-trend" style={{ color: 'var(--text-secondary)' }}>
                    À facturer aux clients
                  </span>
                </div>
                <div className="stat-icon" style={{ color: 'var(--accent-warning)' }}>
                  <Plane size={24} />
                </div>
              </div>
            </div>

            {/* Dashboard Charts */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(400px, 1fr))', gap: '24px', marginBottom: '32px' }}>
              <div className="stat-card" style={{ flexDirection: 'column', height: '400px', alignItems: 'stretch' }}>
                <h3 style={{ fontSize: '1rem', fontWeight: '600', marginBottom: '24px' }}>Évolution du Chiffre d'Affaires ({new Date().getFullYear()})</h3>
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={monthlyData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="name" fontSize={12} tickLine={false} axisLine={false} />
                    <YAxis fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `${value / 1000}k`} />
                    <Tooltip formatter={(value) => formatCurrency(value)} contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }} />
                    <Line type="monotone" dataKey="total" stroke="var(--accent-primary)" strokeWidth={3} dot={{ r: 4, fill: 'var(--accent-primary)' }} activeDot={{ r: 6 }} />
                  </LineChart>
                </ResponsiveContainer>
              </div>

              <div className="stat-card" style={{ flexDirection: 'column', height: '400px', alignItems: 'stretch' }}>
                <h3 style={{ fontSize: '1rem', fontWeight: '600', marginBottom: '24px' }}>Répartition par Mode de Transport</h3>
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={transportData}
                      cx="50%"
                      cy="45%"
                      innerRadius={70}
                      outerRadius={100}
                      paddingAngle={8}
                      dataKey="value"
                    >
                      {transportData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip />
                    <Legend verticalAlign="bottom" height={36} />
                  </PieChart>
                </ResponsiveContainer>
              </div>

              <div className="stat-card" style={{ flexDirection: 'column', height: '400px', alignItems: 'stretch', gridColumn: '1 / -1' }}>
                <h3 style={{ fontSize: '1rem', fontWeight: '600', marginBottom: '24px' }}>Top 5 Clients les plus Rentables</h3>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={topClientsData} layout="vertical" margin={{ left: 60, right: 40, top: 0, bottom: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                    <XAxis type="number" hide />
                    <YAxis type="category" dataKey="name" fontSize={12} width={120} axisLine={false} tickLine={false} />
                    <Tooltip formatter={(value) => formatCurrency(value)} cursor={{ fill: 'transparent' }} />
                    <Bar dataKey="total" fill="var(--accent-secondary)" radius={[0, 4, 4, 0]} barSize={24} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </div>

            {/* Recent Operations Table */}
            <div className="data-table-container">
              <div className="data-table-header">
                <h2 style={{ fontSize: '1.1rem', fontWeight: '600' }}>Opérations Récentes</h2>
                <button onClick={() => setActiveTab('dossiers')} className="btn btn-outline" style={{ fontSize: '0.75rem', padding: '6px 12px' }}>Voir tout</button>
              </div>
              <table className="data-table">
                <thead>
                  <tr>
                    <th>N° Dossier</th>
                    <th>Type</th>
                    <th>Client</th>
                    <th>Statut Facturation</th>
                    <th>Montant</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {dossiers.length === 0 && (
                    <tr>
                      <td colSpan="6" style={{ textAlign: 'center', padding: '32px', color: 'var(--text-secondary)' }}>Aucune opération récente à afficher.</td>
                    </tr>
                  )}
                  {dossiers.slice(0, 5).map(d => {
                    const relatedFacture = factures.find(f => f.dossier_id === d.id);
                    const isPayee = relatedFacture?.statut === 'Validée';
                    const amount = relatedFacture ? formatCurrency(relatedFacture.totalTtc) : '-';
                    const displayStatus = d.statutFacturation || (relatedFacture ? (isPayee ? 'Facture Validée' : 'Proforma Émise') : 'À Facturer');

                    return (
                      <tr key={d.id}>
                        <td style={{ fontWeight: '500' }}>{d.id}</td>
                        <td>
                          <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            {d.modeTransport === 'Maritime' || !d.modeTransport ? <Ship size={14} color="var(--accent-primary)" /> : null}
                            {d.modeTransport === 'Aérien' && <Plane size={14} color="var(--accent-secondary)" />}
                            {d.modeTransport === 'Terrestre' && <Truck size={14} color="var(--accent-warning)" />}
                            {d.modeTransport}
                          </span>
                        </td>
                        <td>{d.client_nom || d.expediteur || '-'}</td>
                        <td>
                          <span className={`badge ${displayStatus.includes('Validée') ? 'badge-success' : displayStatus.includes('Proforma') ? 'badge-warning' : 'badge-primary'}`}>
                            {displayStatus}
                          </span>
                        </td>
                        <td>{amount}</td>
                        <td>
                          <button className="btn btn-outline" style={{ padding: '4px 8px', fontSize: '0.75rem' }} onClick={() => setActiveTab('dossiers')}>Détails</button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {activeTab === 'dossiers' && (
          <DossiersView />
        )}

        {activeTab === 'debours' && (
          <DeboursView />
        )}

        {activeTab === 'factures' && (
          factureViewMode === 'list' ? (
            <FacturesView
              onCreateFacture={() => {
                setFactureViewMode('form');
                setEditFactureData(null);
              }}
              onViewFacture={(facture) => {
                setFactureViewMode('form');
                setEditFactureData(facture);
              }}
            />
          ) : (
            <FacturationForm
              editData={editFactureData}
              onCancel={() => {
                setFactureViewMode('list');
                setEditFactureData(null);
              }}
            />
          )
        )}

        {activeTab === 'clients' && (
          <ClientsView />
        )}
      </main>
    </div>
  );
}

export default App;
