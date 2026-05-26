import React, { useState } from 'react';
import { API_BASE_URL } from '../api';
import { Ship, Mail, Lock, User, ArrowRight } from 'lucide-react';

const Auth = ({ onLogin }) => {
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
                    onLogin(data);
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

    return (
        <div className="auth-wrapper">
            <div className="auth-card">
                <div className="auth-header">
                    <div className="auth-logo">
                        <Ship size={32} color="white" />
                    </div>
                    <h1>F2N Logistics</h1>
                    <p>{isLogin ? 'Accédez à votre espace gestion' : 'Créez votre compte administrateur'}</p>
                </div>

                <form onSubmit={handleSubmit} className="auth-form">
                    {error && <div className="auth-error">{error}</div>}

                    <div className="form-group">
                        <label className="form-label">Nom d'utilisateur</label>
                        <div className="input-with-icon">
                            <User size={18} />
                            <input type="text" name="username" className="form-control" required onChange={handleChange} />
                        </div>
                    </div>

                    {!isLogin && (
                        <div className="form-group">
                            <label className="form-label">Email</label>
                            <div className="input-with-icon">
                                <Mail size={18} />
                                <input type="email" name="email" className="form-control" required onChange={handleChange} />
                            </div>
                        </div>
                    )}

                    <div className="form-group">
                        <label className="form-label">Mot de passe</label>
                        <div className="input-with-icon">
                            <Lock size={18} />
                            <input type="password" name="password" className="form-control" required onChange={handleChange} />
                        </div>
                    </div>

                    <button type="submit" className="btn btn-primary btn-block" disabled={loading}>
                        {loading ? 'Chargement...' : (isLogin ? 'Se connecter' : "S'inscrire")}
                        {!loading && <ArrowRight size={18} style={{ marginLeft: '8px' }} />}
                    </button>
                </form>

                <div className="auth-footer">
                    <button onClick={() => { setIsLogin(!isLogin); setError(''); }} className="btn-link">
                        {isLogin ? "Vous n'avez pas de compte ? S'inscrire" : "Déjà un compte ? Se connecter"}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default Auth;