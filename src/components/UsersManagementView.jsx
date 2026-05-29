import React, { useState, useEffect } from 'react';
import { API_BASE_URL } from '../api';
import { Users, Check, Trash, Shield, Mail, User as UserIcon } from 'lucide-react';

// Définir le nom d'utilisateur du super administrateur pour la protection côté client
const SUPER_ADMIN_USERNAME = 'enzo';
const UsersManagementView = ({ currentUser }) => {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);

    const fetchUsers = async () => {
        setLoading(true);
        try {
            const resp = await fetch(`${API_BASE_URL}/api/users`);
            const data = await resp.json();
            setUsers(data);
        } catch (error) {
            console.error("Erreur chargement utilisateurs:", error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchUsers();
    }, []);

    const handleUpdateUser = async (targetId, status, role) => {
        await fetch(`${API_BASE_URL}/api/users/${targetId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status, role })
        });
        fetchUsers();
    };

    const handleDeleteUser = async (u) => {
        if (window.confirm(`Voulez-vous vraiment supprimer l'utilisateur ${u.username} ?`)) {
            await fetch(`${API_BASE_URL}/api/users/${u.id}`, { method: 'DELETE' });
            fetchUsers();
        }
    };

    return (
        <div className="dashboard-page">
            <div className="page-header" style={{ marginBottom: '32px' }}>
                <h1 className="page-title">Gestion des Utilisateurs</h1>
                <p className="page-subtitle">Approuvez les nouveaux comptes et gérez les droits d'accès de l'équipe</p>
            </div>

            <div className="data-table-container">
                <div className="data-table-header">
                    <h2 style={{ fontSize: '1.1rem', fontWeight: '600', display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <Shield size={20} color="var(--accent-primary)" />
                        Liste des comptes enregistrés
                    </h2>
                </div>
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th style={{ textAlign: 'right' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users
                            .filter(u => currentUser.username === SUPER_ADMIN_USERNAME || u.username !== SUPER_ADMIN_USERNAME)
                            .map(u => (
                                <tr key={u.id}>
                                    <td style={{ fontWeight: '600' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            <div className="avatar" style={{ width: '30px', height: '30px', fontSize: '0.8rem', backgroundColor: u.username === SUPER_ADMIN_USERNAME ? 'var(--accent-danger)' : '' }}>{u.username.charAt(0).toUpperCase()}</div>
                                            {u.username}
                                            {u.username === SUPER_ADMIN_USERNAME && (
                                                <span className="badge" style={{ backgroundColor: 'var(--accent-danger)', color: 'white', fontSize: '0.7rem', padding: '4px 8px', borderRadius: '4px' }}>
                                                    Super Admin
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td>{u.email}</td>
                                    <td>
                                        <select
                                            value={u.role}
                                            onChange={(e) => handleUpdateUser(u.id, u.status, e.target.value)}
                                            disabled={u.id === currentUser.id || u.username === SUPER_ADMIN_USERNAME}
                                            className="form-control"
                                            style={{ padding: '6px 12px', fontSize: '0.85rem', width: 'auto', minWidth: '140px', opacity: 1 }}
                                        >
                                            <option value="employee">Employé</option>
                                            <option value="admin">Administrateur</option>
                                        </select>
                                    </td>
                                    <td>
                                        <span className={`badge ${u.status === 'approved' ? 'badge-success' : 'badge-warning'}`}>
                                            {u.status === 'approved' ? 'Approuvé' : 'En attente'}
                                        </span>
                                    </td>
                                    <td style={{ textAlign: 'right' }}>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                            {u.status === 'pending' && (
                                                <button className="btn btn-outline" style={{ padding: '6px', color: '#10b981' }} onClick={() => handleUpdateUser(u.id, 'approved', u.role)} title="Approuver"><Check size={16} /></button>
                                            )}
                                            {u.id !== currentUser.id && u.username !== SUPER_ADMIN_USERNAME && (
                                                <button className="btn btn-outline" style={{ padding: '6px', color: 'var(--accent-danger)' }} onClick={() => handleDeleteUser(u)} title="Supprimer"><Trash size={16} /></button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default UsersManagementView;