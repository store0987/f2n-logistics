import React, { useState } from 'react';
import { API_BASE_URL } from './api';
import DossiersView from './components/DossiersView';
import FacturationForm from './components/FacturationForm';
import FacturesView from './components/FacturesView';
import ClientsView from './components/ClientsView';
import DeboursView from './components/DeboursView';
import Auth from './components/Auth';
import SkeletonLoader from './components/SkeletonLoader';
import SettingsView from './components/SettingsView';
import UsersManagementView from './components/UsersManagementView';
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
  Shield,
  Truck,
  Search,
  Bell,
  ArrowUpRight,
  ArrowDownRight,
  Plus,
  DollarSign,
  LogOut,
  Menu,
  X
} from 'lucide-react';

function App() {
  const [user, setUser] = useState(() => {
    const saved = localStorage.getItem('f2n_user');
    if (!saved) return null;
    const userData = JSON.parse(saved);

    // Correctif de sécurité : Enzo est le super admin, on force son rôle
    // au cas où sa session locale serait obsolète.
    if (userData.username?.toLowerCase() === 'enzo') {
      userData.role = 'admin';
    }
    return userData;
  });

  const [activeTab, setActiveTab] = useState(() => {
    return localStorage.getItem('activeTab') || 'dashboard';
  });

  const [theme, setTheme] = useState(() => localStorage.getItem('f2n_theme') || 'dark');

  React.useEffect(() => {
    localStorage.setItem('f2n_theme', theme);
  }, [theme]);

  React.useEffect(() => {
    localStorage.setItem('activeTab', activeTab);
  }, [activeTab]);

  const [isSidebarOpen, setIsSidebarOpen] = useState(false);

  const [isLoading, setIsLoading] = useState(true);
  const [dossiers, setDossiers] = useState([]);
  const [factures, setFactures] = useState([]);
  const [debours, setDebours] = useState([]);
  const [factureViewMode, setFactureViewMode] = useState('list');
  const [editFactureData, setEditFactureData] = useState(null);

  const fetchDashboardData = async () => {
    if (!user) return;
    setIsLoading(true);
    try {
      const respDossiers = await fetch(`${API_BASE_URL}/api/dossiers`);
      const dataDossiers = await respDossiers.json();
      if (Array.isArray(dataDossiers)) setDossiers(dataDossiers);

      const respFactures = await fetch(`${API_BASE_URL}/api/factures`);
      const dataFactures = await respFactures.json();
      if (Array.isArray(dataFactures)) setFactures(dataFactures);

      const respDebours = await fetch(`${API_BASE_URL}/api/debours`);
      const dataDebours = await respDebours.json();
      if (Array.isArray(dataDebours)) setDebours(dataDebours);
    } catch (error) {
      console.error('Erreur lors du chargement des données du dashboard:', error);
    } finally {
      setIsLoading(false);
    }
  };

  React.useEffect(() => {
    if (user) {
      fetchDashboardData();
      // Réinitialiser les modes formulaires
      setFactureViewMode('list');
      setEditFactureData(null);
    }
  }, [activeTab, user]); //user ajouté ici pour rafraîchir après login

  const handleLogout = () => {
    localStorage.removeItem('f2n_user');
    // Force le rechargement complet pour éviter l'écran noir
    window.location.href = '/';
  };

  const handleNavClick = (tab) => {
    setActiveTab(tab);
    setIsSidebarOpen(false);
  };

  if (!user) return <Auth />;

  const formatCurrency = (amount) => new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';

  const caMois = factures.filter(f => f.statut === 'Validée').reduce((sum, f) => sum + parseFloat(f.totalTtc || 0), 0);
  const dossiersEnCours = dossiers.length;
  const facturesImpayees = factures.filter(f => f.statut === 'Proforma').reduce((sum, f) => sum + parseFloat(f.totalTtc || 0), 0);
  const totalDeboursEnAttente = debours.filter(d => d.statut === 'En attente').reduce((sum, d) => sum + parseFloat(d.montant), 0);

  // Préparation des données pour les graphiques
  const monthlyData = React.useMemo(() => {
    const months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
    const currentYear = new Date().getFullYear();
    const data = months.map(name => ({ name, total: 0 }));

    factures.filter(f => f.statut === 'Validée' && f.date).forEach(f => {
      const d = new Date(f.date);
      if (d.getFullYear() === currentYear) {
        data[d.getMonth()].total += parseFloat(f.totalTtc || 0);
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
      acc[name] = (acc[name] || 0) + parseFloat(f.totalTtc || 0);
      return acc;
    }, {});
    return Object.keys(totals)
      .map(name => ({ name, total: totals[name] }))
      .sort((a, b) => b.total - a.total)
      .slice(0, 5);
  }, [factures]);

  const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

  // Styles Unifiés pour éviter l'usage de fichier CSS
  const styles = {
    appContainer: {
      backgroundColor: 'var(--bg-app)',
      fontFamily: 'system-ui, -apple-system, sans-serif'
    },
    loadingScreen: {
      height: '100vh',
      width: '100vw',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: 'var(--bg-app)',
      color: '#3b82f6'
    },
    sidebar: {
      // width: '260px', // Géré par .sidebar dans index.css et media queries
      backgroundColor: '#111827',
      borderRight: '1px solid rgba(255, 255, 255, 0.1)',
      // display: 'flex', // Géré par .sidebar dans index.css
      flexDirection: 'column'
    },
    sidebarHeader: {
      padding: '20px 24px',
      display: 'flex',
      alignItems: 'center',
      gap: '12px',
      borderBottom: '1px solid rgba(255, 255, 255, 0.1)' // Géré par .sidebar-header dans index.css
    },
    navMenu: {
      padding: '16px',
      display: 'flex',
      flexDirection: 'column',
      gap: '8px',
      flex: 1
    },
    mainContent: {
      // flex: 1, // Géré par .main-content dans index.css
      display: 'flex',
      flexDirection: 'column',
      overflowY: 'auto',
      backgroundColor: 'var(--bg-app)'
    },
    topHeader: {
      height: '70px',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: '0 32px',
      backgroundColor: 'var(--bg-header)',
      backdropFilter: 'blur(10px)',
      borderBottom: '1px solid rgba(255, 255, 255, 0.1)',
      position: 'sticky',
      top: 0,
      zIndex: 10
    },
    navItem: (isActive) => ({
      // display: 'flex', // Géré par .nav-item dans index.css
      alignItems: 'center',
      gap: '12px',
      padding: '12px 16px',
      borderRadius: '8px',
      color: isActive ? '#3b82f6' : '#94a3b8',
      backgroundColor: isActive ? 'rgba(59, 130, 246, 0.1)' : 'transparent',
      // textDecoration: 'none', // Géré par .nav-item dans index.css
      fontWeight: '500',
      cursor: 'pointer',
      borderLeft: isActive ? '3px solid #3b82f6' : '3px solid transparent'
    }),
    statCard: {
      backgroundColor: 'rgba(30, 41, 59, 0.7)',
      border: '1px solid rgba(255, 255, 255, 0.1)',
      borderRadius: '12px',
      padding: '24px',
      display: 'flex',
      alignItems: 'flex-start',
      justifyContent: 'space-between'
    }
  };

  if (isLoading && user) {
    return <SkeletonLoader />;
  }

  return ( // Utilisation de classes CSS pour la structure principale
    <div className={`app-container theme-${theme}`} style={styles.appContainer} key={user.id}>
      <style>{`
        :root {
          --bg-app: ${theme === 'dark' ? '#0b0f19' : '#f1f5f9'};
          --bg-card: ${theme === 'dark' ? 'rgba(30, 41, 59, 0.7)' : '#ffffff'};
          --bg-header: ${theme === 'dark' ? 'rgba(11, 15, 25, 0.8)' : 'rgba(255, 255, 255, 0.9)'};
          --text-primary: ${theme === 'dark' ? '#f8fafc' : '#1e293b'};
          --text-secondary: ${theme === 'dark' ? '#94a3b8' : '#64748b'};
          --border-color: ${theme === 'dark' ? 'rgba(255, 255, 255, 0.1)' : '#e2e8f0'};
          --input-bg: ${theme === 'dark' ? 'rgba(255, 255, 255, 0.05)' : '#ffffff'};
        }

        body {
          background-color: var(--bg-app);
          color: var(--text-primary);
        }

        .form-container {
          background-color: var(--bg-card) !important;
          border-color: var(--border-color) !important;
        }

        .form-control {
          background-color: var(--input-bg) !important;
          border-color: var(--border-color) !important;
          color: var(--text-primary) !important;
        }

        .page-title { color: var(--text-primary) !important; }
        .page-subtitle { color: var(--text-secondary) !important; }

        @media (max-width: 1024px) {
          .main-content {
            overflow-x: hidden !important;
          }
          .dashboard-page {
            padding: 16px !important;
          }
          .form-grid {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
          }
          .form-container {
            padding: 16px !important;
            width: 100% !important;
            box-sizing: border-box !important;
            margin-bottom: 20px !important;
          }
          .form-group {
            width: 100% !important;
          }
          .form-control {
            font-size: 16px !important; /* Évite le zoom auto sur iOS */
          }
          .form-actions {
            flex-direction: column-reverse !important;
            gap: 12px;
          }
          .form-actions button {
            width: 100%;
          }
          .form-actions div {
            width: 100%;
            justify-content: center;
          }
          .top-header {
            padding: 0 16px !important;
          }
          .sidebar {
            position: fixed !important;
            left: -260px !important;
            top: 0 !important;
            bottom: 0 !important;
            z-index: 1000 !important;
            transition: left 0.3s ease !important;
            width: 260px !important;
            display: flex !important;
          }
          .sidebar.open {
            left: 0 !important;
          }
          .sidebar-overlay {
            display: block !important;
          }
          .menu-toggle {
            display: flex !important;
            align-items: center;
          }
          .hide-on-mobile {
            display: none !important;
          }
          .user-profile-header {
            display: none !important;
          }
          .stats-grid {
            grid-template-columns: 1fr !important;
          }
          .dashboard-charts {
            grid-template-columns: 1fr !important;
          }
          .data-table-container {
            overflow-x: auto !important;
            width: 100% !important;
            -webkit-overflow-scrolling: touch;
          }
          .data-table {
            min-width: 800px;
          }
          .page-header {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 16px;
          }
          .facture-header-flex, .facture-context-grid, .facture-summary-grid {
            flex-direction: column !important;
            align-items: stretch !important;
          }
          .facture-header-flex > div {
            width: 100% !important;
            min-width: 0 !important;
            text-align: left !important;
          }
          .facture-header-flex .title-badge {
            align-self: flex-start !important;
            width: 100%;
            text-align: center;
            box-sizing: border-box;
          }
          .facture-header-flex .info-inputs {
            justify-content: flex-start !important;
            flex-direction: column !important;
            gap: 12px !important;
          }
          .facture-header-flex .info-inputs > div, .facture-header-flex .info-inputs input {
            width: 100% !important;
            text-align: left !important;
          }
          .facture-summary-grid > div {
            width: 100% !important;
            max-width: none !important;
            margin-left: 0 !important;
          }
          .context-col {
            border-right: none !important;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 24px;
          }
          .sidebar-user-info {
            display: flex !important;
            padding: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: auto;
            align-items: center;
            gap: 12px;
          }
        }
      `}</style>

      {/* Overlay pour fermer le menu mobile au clic extérieur */}
      {isSidebarOpen && (
        <div
          className="sidebar-overlay"
          onClick={() => setIsSidebarOpen(false)}
          style={{ position: 'fixed', inset: 0, backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 999, display: 'none' }}
        />
      )}

      {/* Sidebar */}
      <aside className={`sidebar ${isSidebarOpen ? 'open' : ''}`} style={styles.sidebar}>
        <div className="sidebar-header" style={styles.sidebarHeader}>
          <Ship color="#3b82f6" size={28} />
          <span style={{ fontSize: '1.1rem', fontWeight: '700', color: '#3b82f6', flex: 1 }}>F2N LOGISTICS SARL</span>
          <button className="menu-toggle" onClick={() => setIsSidebarOpen(false)} style={{ display: 'none', background: 'none', border: 'none', color: '#94a3b8', cursor: 'pointer' }}>
            <X size={24} />
          </button>
        </div>

        <nav style={styles.navMenu}>
          <a style={styles.navItem(activeTab === 'dashboard')} onClick={() => handleNavClick('dashboard')}>
            <LayoutDashboard size={20} />
            Tableau de Bord
          </a>
          <a style={styles.navItem(activeTab === 'dossiers')} onClick={() => handleNavClick('dossiers')}>
            <Truck size={20} />
            Dossiers / Opérations
          </a>
          <a style={styles.navItem(activeTab === 'debours')} onClick={() => handleNavClick('debours')}>
            <DollarSign size={20} />
            Gestion Débours
          </a>
          <a style={styles.navItem(activeTab === 'factures')} onClick={() => handleNavClick('factures')}>
            <FileText size={20} />
            Facturation
          </a>
          <a style={styles.navItem(activeTab === 'clients')} onClick={() => handleNavClick('clients')}>
            <Users size={20} />
            Clients & Partenaires
          </a>
          {user.role === 'admin' && (
            <a style={styles.navItem(activeTab === 'users-mgmt')} onClick={() => handleNavClick('users-mgmt')}>
              <Shield size={20} />
              Gestion Utilisateurs
            </a>
          )}
          <div style={{ flex: 1 }}></div>
          <a style={styles.navItem(activeTab === 'settings')} onClick={() => handleNavClick('settings')}>
            <Settings size={20} />
            Paramètres
          </a>

          {/* Section Profil dans le menu burger (visible uniquement sur mobile) */}
          <div className="sidebar-user-info" style={{ display: 'none' }}>
            <div className="avatar" style={{ width: '36px', height: '36px', borderRadius: '50%', backgroundColor: '#3b82f6', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', color: 'white' }}>
              {user.username ? user.username.charAt(0).toUpperCase() : 'U'}
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
              <span style={{ fontSize: '0.875rem', fontWeight: '600', color: '#f8fafc', whiteSpace: 'nowrap', textOverflow: 'ellipsis', overflow: 'hidden' }}>{user.username}</span>
              <span style={{ fontSize: '0.75rem', color: '#94a3b8', whiteSpace: 'nowrap', textOverflow: 'ellipsis', overflow: 'hidden' }}>{user.email}</span>
            </div>
          </div>

          <a className="nav-item" style={{ ...styles.navItem(false), color: '#ef4444' }} onClick={handleLogout}>
            <LogOut size={20} />
            Déconnexion
          </a>
        </nav>
      </aside>

      {/* Main Content */} {/* Utilisation de classes CSS pour la structure principale */}
      <main className="main-content" style={styles.mainContent}>
        {/* Top Header */}
        <header className="top-header" style={styles.topHeader}>
          <button className="menu-toggle" onClick={() => setIsSidebarOpen(true)} style={{ display: 'none', background: 'none', border: 'none', color: 'var(--text-primary)', cursor: 'pointer', marginRight: '12px', height: '44px', width: '44px', alignItems: 'center', justifyContent: 'center' }}>
            <Menu size={24} />
          </button>

          <div className="search-bar">
            <Search size={18} style={{ color: 'var(--text-secondary)' }} />
            <input
              type="text"
              placeholder="Rechercher un dossier (ex: B/L, Facture)..."
              style={{ backgroundColor: 'transparent', border: 'none', color: 'var(--text-primary)', outline: 'none', width: '100%' }}
            />
          </div>

          <div className="user-profile user-profile-header">
            <Bell size={20} style={{ color: 'var(--text-secondary)', cursor: 'pointer', marginRight: '16px' }} className="hide-on-mobile" />
            <div style={{ textAlign: 'right', display: 'flex', flexDirection: 'column' }}>
              <span style={{ fontSize: '0.875rem', fontWeight: '600', color: '#f8fafc' }}>{user.username}</span>
              <span style={{ fontSize: '0.75rem', color: '#94a3b8' }}>{user.email}</span>
            </div>
            <div className="avatar">{user.username ? user.username.charAt(0).toUpperCase() : 'U'}</div>
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
            <div className="dashboard-charts" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 400px), 1fr))', gap: '24px', marginBottom: '32px' }}>
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
          <DeboursView user={user} />
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
              key={editFactureData?.numeroFacture || 'new-invoice'}
              editData={editFactureData}
              user={user}
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

        {activeTab === 'users-mgmt' && user.role === 'admin' && (
          <UsersManagementView currentUser={user} />
        )}

        {activeTab === 'settings' && (
          <SettingsView
            user={user}
            setUser={setUser}
            theme={theme}
            setTheme={setTheme}
          />
        )}
      </main>
    </div>
  );
}

export default App;
