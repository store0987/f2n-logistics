import React, { useState, useEffect } from 'react';
import { API_BASE_URL } from '../api';
import { Plus, Trash2, DollarSign, Calendar, FileText } from 'lucide-react';

const DeboursView = () => {
    const [debours, setDebours] = useState([]);
    const [dossiers, setDossiers] = useState([]);
    const [showForm, setShowForm] = useState(false);
    const [formData, setFormData] = useState({
        date: new Date().toISOString().split('T')[0],
        dossier_id: '',
        description: '',
        montant: 0
    });

    const fetchDebours = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/api/debours`);
            const data = await response.json();
            setDebours(data);
        } catch (error) {
            console.error('Erreur lors du chargement des débours:', error);
        }
    };

    const fetchDossiers = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/api/dossiers`);
            const data = await response.json();
            setDossiers(data);
        } catch (error) {
            console.error('Erreur lors du chargement des dossiers:', error);
        }
    };

    useEffect(() => {
        fetchDebours();
        fetchDossiers();
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            const response = await fetch(`${API_BASE_URL}/api/debours`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            if (response.ok) {
                fetchDebours();
                setShowForm(false);
                setFormData({ date: new Date().toISOString().split('T')[0], dossier_id: '', description: '', montant: 0 });
            }
        } catch (error) {
            console.error('Erreur lors de l\'enregistrement:', error);
        }
    };

    const handleDelete = async (id) => {
        if (window.confirm("Supprimer ce débours ?")) {
            await fetch(`${API_BASE_URL}/api/debours/${id}`, { method: 'DELETE' });
            fetchDebours();
        }
    };

    const formatCurrency = (amount) => new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';

    return (
        <div className="dashboard-page">
            <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
                <div>
                    <h1 className="page-title">Gestion des Débours</h1>
                    <p className="page-subtitle">Suivez les frais tiers engagés pour vos clients (Douane, Port, etc.)</p>
                </div>
                {!showForm && (
                    <button className="btn btn-primary" onClick={() => setShowForm(true)}>
                        <Plus size={18} /> Nouveau Débours
                    </button>
                )}
            </div>

            {showForm && (
                <div className="form-container" style={{ marginBottom: '32px' }}>
                    <form onSubmit={handleSubmit}>
                        <div className="form-grid">
                            <div className="form-group">
                                <label className="form-label">Dossier lié</label>
                                <select className="form-control" value={formData.dossier_id} onChange={e => setFormData({ ...formData, dossier_id: e.target.value })} required>
                                    <option value="">Sélectionner un dossier...</option>
                                    {dossiers.map(d => <option key={d.id} value={d.id}>{d.id} - {d.client_nom}</option>)}
                                </select>
                            </div>
                            <div className="form-group">
                                <label className="form-label">Date de paiement</label>
                                <input type="date" className="form-control" value={formData.date} onChange={e => setFormData({ ...formData, date: e.target.value })} required />
                            </div>
                            <div className="form-group full-width">
                                <label className="form-label">Description (Nature des frais)</label>
                                <input type="text" className="form-control" value={formData.description} onChange={e => setFormData({ ...formData, description: e.target.value })} required placeholder="ex: Frais de magasinage Terminal" />
                            </div>
                            <div className="form-group">
                                <label className="form-label">Montant (FCFA)</label>
                                <input type="number" className="form-control" value={formData.montant} onChange={e => setFormData({ ...formData, montant: parseFloat(e.target.value) })} required />
                            </div>
                        </div>
                        <div className="form-actions">
                            <button type="button" className="btn btn-outline" onClick={() => setShowForm(false)}>Annuler</button>
                            <button type="submit" className="btn btn-primary">Enregistrer le Débours</button>
                        </div>
                    </form>
                </div>
            )}

            <div className="data-table-container">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Dossier</th>
                            <th>Client</th>
                            <th>Description</th>
                            <th style={{ textAlign: 'right' }}>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {debours.map(db => (
                            <tr key={db.id}>
                                <td>{db.date}</td>
                                <td style={{ fontWeight: '600' }}>{db.dossier_id}</td>
                                <td>{db.client_nom}</td>
                                <td>{db.description}</td>
                                <td style={{ textAlign: 'right', fontWeight: '700' }}>{formatCurrency(db.montant)}</td>
                                <td>
                                    <span className={`badge ${db.statut === 'Facturé' ? 'badge-success' : 'badge-warning'}`}>
                                        {db.statut}
                                    </span>
                                </td>
                                <td>
                                    {db.statut === 'En attente' && (
                                        <button className="btn btn-outline" style={{ color: 'var(--accent-danger)' }} onClick={() => handleDelete(db.id)}>
                                            <Trash2 size={14} />
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default DeboursView;