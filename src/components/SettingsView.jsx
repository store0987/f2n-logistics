import React, { useState } from 'react';
import { User, Mail, Moon, Sun, Shield, Save, LogOut } from 'lucide-react';

const SettingsView = ({ user, setUser, theme, setTheme }) => {
    const [formData, setFormData] = useState({
        username: user?.username || '',
        email: user?.email || ''
    });
    const [message, setMessage] = useState('');

    const handleProfileSubmit = (e) => {
        e.preventDefault();
        const updatedUser = { ...user, ...formData };
        setUser(updatedUser);
        localStorage.setItem('f2n_user', JSON.stringify(updatedUser));
        setMessage('Profil mis à jour avec succès !');
        setTimeout(() => setMessage(''), 3000);
    };

    const toggleTheme = () => {
        setTheme(theme === 'dark' ? 'light' : 'dark');
    };

    return (
        <div className="dashboard-page">
            <div className="page-header" style={{ marginBottom: '32px' }}>
                <h1 className="page-title">Paramètres</h1>
                <p className="page-subtitle">Gérez vos préférences et les informations de votre compte</p>
            </div>

            <div className="form-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(350px, 1fr))', gap: '24px' }}>
                
                {/* Profil Utilisateur */}
                <div className="form-container" style={{ padding: '24px', borderRadius: '12px', border: '1px solid var(--border-color)' }}>
                    <h3 style={{ fontSize: '1.1rem', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <User size={20} color="var(--accent-primary)" />
                        Informations du Profil
                    </h3>
                    
                    {message && (
                        <div style={{ padding: '10px', backgroundColor: 'rgba(16, 185, 129, 0.1)', color: '#10b981', borderRadius: '8px', marginBottom: '16px', fontSize: '0.85rem' }}>
                            {message}
                        </div>
                    )}

                    <form onSubmit={handleProfileSubmit}>
                        <div className="form-group" style={{ marginBottom: '16px' }}>
                            <label className="form-label" style={{ display: 'block', marginBottom: '8px', fontSize: '0.85rem', color: 'var(--text-secondary)' }}>Nom d'utilisateur</label>
                            <div style={{ position: 'relative' }}>
                                <User size={16} style={{ position: 'absolute', left: '12px', top: '12px', color: 'var(--text-secondary)' }} />
                                <input 
                                    type="text" 
                                    className="form-control" 
                                    style={{ width: '100%', padding: '10px 10px 10px 40px', borderRadius: '8px' }}
                                    value={formData.username}
                                    onChange={(e) => setFormData({...formData, username: e.target.value})}
                                />
                            </div>
                        </div>
                        <div className="form-group" style={{ marginBottom: '24px' }}>
                            <label className="form-label" style={{ display: 'block', marginBottom: '8px', fontSize: '0.85rem', color: 'var(--text-secondary)' }}>Adresse Email</label>
                            <div style={{ position: 'relative' }}>
                                <Mail size={16} style={{ position: 'absolute', left: '12px', top: '12px', color: 'var(--text-secondary)' }} />
                                <input 
                                    type="email" 
                                    className="form-control" 
                                    style={{ width: '100%', padding: '10px 10px 10px 40px', borderRadius: '8px' }}
                                    value={formData.email}
                                    onChange={(e) => setFormData({...formData, email: e.target.value})}
                                />
                            </div>
                        </div>
                        <button type="submit" className="btn btn-primary" style={{ width: '100%' }}>
                            <Save size={18} /> Enregistrer les modifications
                        </button>
                    </form>
                </div>

                {/* Préférences Interface */}
                <div className="form-container" style={{ padding: '24px', borderRadius: '12px', border: '1px solid var(--border-color)' }}>
                    <h3 style={{ fontSize: '1.1rem', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <Sun size={20} color="var(--accent-warning)" />
                        Interface & Affichage
                    </h3>

                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px', backgroundColor: 'var(--input-bg)', borderRadius: '10px', marginBottom: '16px' }}>
                        <div>
                            <span style={{ display: 'block', fontWeight: '600' }}>Mode Clair</span>
                            <span style={{ fontSize: '0.8rem', color: 'var(--text-secondary)' }}>Activer le thème lumineux</span>
                        </div>
                        <button 
                            onClick={toggleTheme}
                            style={{ 
                                padding: '8px 16px', borderRadius: '20px', border: 'none', cursor: 'pointer',
                                backgroundColor: theme === 'light' ? 'var(--accent-primary)' : '#334155',
                                color: '#fff', transition: 'all 0.3s'
                            }}
                        >
                            {theme === 'light' ? <Sun size={18} /> : <Moon size={18} />}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SettingsView;