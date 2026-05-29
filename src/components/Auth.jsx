import React, { useState } from 'react';
import { API_BASE_URL } from '../api';
import { Ship, Mail, Lock, User, ArrowRight } from 'lucide-react';

const Auth = () => {
    const [isLogin, setIsLogin] = useState(true);
    const [loading, setLoading] = useState(false);
    const [formData, setFormData] = useState({ username: '', password: '', email: '' });
    const [error, setError] = useState('');

    const handleChange = (e) => setFormData({ ...formData, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        const endpoint = isLogin ? '/api/login' : '/api/register';

        try {
            const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            const data = await response.json();

            if (response.ok) {
                if (isLogin) {
                    localStorage.setItem('f2n_user', JSON.stringify(data));
                    // On s'assure de revenir sur le tableau de bord par défaut
                    localStorage.setItem('activeTab', 'dashboard');
                    // Recharge la page actuelle au lieu de rediriger vers la racine du domaine
                    window.location.reload();
                } else {
                    alert("Compte créé avec succès ! Connectez-vous maintenant.");
                    setIsLogin(true);
                }
            } else {
                setError(data.error || "Une erreur est survenue");
            }
        } catch (err) {
            setError("Impossible de contacter le serveur.");
        } finally {
            setLoading(false);
        }
    };

    const styles = {
        wrapper: {
            minHeight: '100vh',
            width: '100vw',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: '#0b0f19',
            color: '#f8fafc',
            fontFamily: 'system-ui, sans-serif',
            position: 'fixed',
            top: 0,
            left: 0,
            zIndex: 9999,
            overflowY: 'auto',
            padding: '20px 0'
        },
        card: {
            backgroundColor: '#111827',
            padding: 'min(40px, 8%)',
            borderRadius: '12px',
            width: '90%',
            maxWidth: '400px',
            border: '1px solid rgba(255, 255, 255, 0.1)',
            boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.5)'
        },
        header: {
            // textAlign: 'center', // Géré par .auth-header dans index.css
            marginBottom: '32px'
        },
        logo: {
            width: '60px',
            height: '60px',
            backgroundColor: '#3b82f6',
            borderRadius: '12px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            margin: '0 auto 16px auto'
        },
        title: { fontSize: '1.75rem', fontWeight: '800', marginBottom: '8px', color: '#f8fafc', textAlign: 'center' },
        subtitle: { color: '#94a3b8', fontSize: '0.9rem' },
        form: { display: 'flex', flexDirection: 'column', gap: '20px' },
        error: {
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            border: '1px solid #ef4444',
            color: '#ef4444',
            padding: '12px',
            borderRadius: '8px',
            fontSize: '0.85rem',
            textAlign: 'center'
        },
        inputGroup: { display: 'flex', flexDirection: 'column', gap: '8px' },
        label: { fontSize: '0.875rem', fontWeight: '500', color: '#94a3b8' },
        inputContainer: { position: 'relative', display: 'flex', alignItems: 'center' },
        icon: { position: 'absolute', left: '12px', color: '#64748b' },
        input: {
            width: '100%',
            backgroundColor: 'rgba(255, 255, 255, 0.05)',
            border: '1px solid rgba(255, 255, 255, 0.1)',
            borderRadius: '8px',
            padding: '12px 16px 12px 40px',
            color: '#f8fafc',
            outline: 'none'
        },
        button: {
            backgroundColor: '#3b82f6',
            color: 'white',
            padding: '12px',
            borderRadius: '8px',
            border: 'none',
            fontWeight: '600',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            marginTop: '10px'
        },
        footer: { marginTop: '24px', textAlign: 'center' },
        link: { background: 'none', border: 'none', color: '#3b82f6', cursor: 'pointer', textDecoration: 'underline' }
    };

    return (
        <div className="auth-wrapper" style={styles.wrapper}>
            <div className="auth-card" style={styles.card}>
                <div className="auth-header" style={styles.header}>
                    <div className="auth-logo" style={styles.logo}>
                        <Ship size={32} color="white" />
                    </div>
                    <h1 style={styles.title}>F2N LOGISTICS</h1>
                    <p style={styles.subtitle}>{isLogin ? 'Accédez à votre espace gestion' : 'Créez votre compte administrateur'}</p>
                </div>

                <form onSubmit={handleSubmit} style={styles.form}>
                    {error && <div style={styles.error}>{error}</div>}

                    <div className="auth-input-group" style={styles.inputGroup}>
                        <label className="auth-label" style={styles.label}>Nom d'utilisateur</label>
                        <div className="auth-input-container" style={styles.inputContainer}>
                            <User size={18} style={styles.icon} />
                            <input type="text" name="username" className="auth-input" style={styles.input} required onChange={handleChange} />
                        </div>
                    </div>

                    {!isLogin && (
                        <div className="auth-input-group" style={styles.inputGroup}>
                            <label className="auth-label" style={styles.label}>Email</label>
                            <div className="auth-input-container" style={styles.inputContainer}>
                                <Mail size={18} style={styles.icon} />
                                <input type="email" name="email" className="auth-input" style={styles.input} required onChange={handleChange} />
                            </div>
                        </div>
                    )}

                    <div className="auth-input-group" style={styles.inputGroup}>
                        <label className="auth-label" style={styles.label}>Mot de passe</label>
                        <div style={styles.inputContainer}>
                            <Lock size={18} style={styles.icon} />
                            <input type="password" name="password" className="auth-input" style={styles.input} required onChange={handleChange} />
                        </div>
                    </div>

                    <button type="submit" className="auth-button" style={styles.button} disabled={loading}>
                        {loading ? 'Chargement...' : (isLogin ? 'Se connecter' : "S'inscrire")}
                        {!loading && <ArrowRight size={18} style={{ marginLeft: '8px' }} />}
                    </button>
                </form>

                <div className="auth-footer" style={styles.footer}>
                    <button onClick={() => { setIsLogin(!isLogin); setError(''); }} className="auth-link" style={styles.link}>
                        {isLogin ? "Vous n'avez pas de compte ? S'inscrire" : "Déjà un compte ? Se connecter"}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default Auth;