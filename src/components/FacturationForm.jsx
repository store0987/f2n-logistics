import React, { useState } from 'react';
import { API_BASE_URL } from '../api';
import {
  Plus, Trash2, Printer, Save, CheckCircle, Ship, MapPin, Box, Hash, User, Download, Mail, FileDown, ArrowLeft, X
} from 'lucide-react';

const LOGISTICS_DESIGNATIONS = [
  "Assurance Faculté (Transport)", "Cautionnement Conteneur", "Chargement / Empotage", "Correction de Manifeste",
  "Déchargement / Dépotage", "Détention Conteneur", "Droits et Taxes Douaniers", "Émission Bon à Délivrer (BAD)",
  "Entreposage Logistique", "Escorte Douanière", "Expertise Marchandise", "Frais d'Agence", "Frais de dossier",
  "Frais de Pesage (VGM)", "Frais de Scellé Douane", "Frais de Timbre Douane", "Frais de Timbre Fiscal",
  "Frais LTA / Master BL", "Fumigation / Certificat Phytosanitaire", "Honoraires de Transit Export",
  "Honoraires de Transit Import", "Immobilisation Véhicule", "Livraison Hors Ville", "Location de Grue / Élévateur",
  "Magasinage Portuaire (DPW)", "Magasinage Sous Douane", "Manutention (Terre-Plein/Quai)",
  "Messagerie et Courrier (DHL/UPS)", "Ouverture de Dossier", "Palettisation et Filmage", "Passage Portuaire (BAD)",
  "Prélèvement Communautaire (PCC/PCS)", "Redevance Statistique (RS)", "Scanner Portuaire", "SGS / Bureau Veritas / Cotecna",
  "Suivi Logistique & Reporting", "Surestaries Armateur", "Taxe COSEC", "Traction Portuaire", "Transport Local (Camion)",
  "Visite Douane / Inspection"
].sort();

const FacturationForm = ({ onCancel, editData, user }) => {
  const [availableDossiers, setAvailableDossiers] = useState([]);
  const [pendingDebours, setPendingDebours] = useState([]);
  const [importedDeboursIds, setImportedDeboursIds] = useState([]);

  const [factureInfo, setFactureInfo] = useState({
    numeroFacture: 'Chargement...',
    date: new Date().toISOString().split('T')[0],
    dossierLie: '',
    numDeclaration: '',
    adresseFacturation: ''
  });

  const [lignes, setLignes] = useState([
    { id: 1, description: '', quantite: 1, prixUnitaire: 0, taxable: true, type: 'prestation' },
  ]);

  const fetchDossiers = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/api/dossiers`);
      const data = await response.json();
      setAvailableDossiers(data);
      if (data.length > 0 && !editData) {
        setFactureInfo(prev => ({ ...prev, dossierLie: data[0].id }));
      }
    } catch (error) {
      console.error('Erreur lors du chargement des dossiers:', error);
    }
  };

  const fetchPendingDebours = async (dossierId) => {
    if (!dossierId) return;
    try {
      const response = await fetch(`${API_BASE_URL}/api/debours?dossier_id=${dossierId}`);
      const data = await response.json();
      setPendingDebours(data);
    } catch (error) {
      console.error('Erreur lors du chargement des débours:', error);
    }
  };

  React.useEffect(() => {
    // Chargement initial des dossiers (une seule fois)
    const initDossiers = async () => {
      await fetchDossiers();
    };
    initDossiers();
  }, []); // Dépendance vide pour ne le faire qu'au montage

  React.useEffect(() => {
    // Chargement des débours quand le dossier change
    if (factureInfo.dossierLie) {
      fetchPendingDebours(factureInfo.dossierLie);
    }
  }, [factureInfo.dossierLie]);

  React.useEffect(() => {
    const fetchNextNumber = async () => {
      if (!editData) {
        const response = await fetch(`${API_BASE_URL}/api/next-facture-number/PRO`);
        const data = await response.json();
        setFactureInfo(prev => ({ ...prev, numeroFacture: data.number }));
      }
    };
    fetchNextNumber();

    if (editData) {
      const loadInvoiceData = async () => {
        try {
          const response = await fetch(`${API_BASE_URL}/api/factures/${editData.numeroFacture}`);
          const data = await response.json();
          if (data.factureInfo) {
            setFactureInfo({
              numeroFacture: data.factureInfo.numeroFacture,
              date: data.factureInfo.date,
              dossierLie: data.factureInfo.dossier_id || '',
              numDeclaration: data.factureInfo.numDeclaration || '',
              adresseFacturation: data.factureInfo.adresseFacturation || ''
            });
          }
          if (data.lignes) {
            setLignes(data.lignes.map((l, index) => ({
              id: l.id || index + 1,
              description: l.description,
              quantite: l.quantite,
              prixUnitaire: l.prixUnitaire,
              taxable: !!l.taxable,
              type: l.type || 'prestation'
            })));
          }
        } catch (error) {
          console.error("Erreur lors du chargement des détails de la facture:", error);
        }
      };
      loadInvoiceData();
    }
  }, [editData]);

  const selectedDossier = availableDossiers.find(d => d.id === factureInfo.dossierLie) || {
    client_nom: '-',
    numBL: '-',
    navire: '-',
    origine: '-',
    destination: '-',
    natureMarchandise: '-',
    poids: '-',
    volume: '-',
    nombresColis: '-',
    client_id: null,
    client_nif: '',
    client_rccm: '',
    client_tel: '',
    numVoyage: ''
  };

  const tvaRate = 0.1925;

  const handleInfoChange = (e) => {
    setFactureInfo({ ...factureInfo, [e.target.name]: e.target.value });
  };

  const handleImportDebour = (db) => {
    const newLigne = {
      id: lignes.length > 0 ? Math.max(...lignes.map(l => l.id)) + 1 : 1,
      description: db.description,
      quantite: 1,
      prixUnitaire: db.montant,
      taxable: false,
      type: 'debour'
    };
    setLignes([...lignes, newLigne]);
    setImportedDeboursIds([...importedDeboursIds, db.id]);
    setPendingDebours(pendingDebours.filter(d => d.id !== db.id));
  };

  const handleSave = async (statut = 'Proforma', customNumFacture = null) => {
    const payload = {
      factureInfo: {
        ...factureInfo,
        numeroFacture: customNumFacture || factureInfo.numeroFacture,
        client_id: selectedDossier.client_id,
        statut
      },
      lignes,
      debours_ids: importedDeboursIds,
      totaux: {
        sousTotal,
        montantTva: montantTVA,
        totalTtc: totalTTC
      }
    };

    try {
      const response = await fetch(`${API_BASE_URL}/api/factures`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (response.ok) {
        alert(statut === 'Proforma' ? 'Proforma enregistrée avec succès !' : `Facture validée et enregistrée avec succès !`);
        if (onCancel) onCancel();
      } else {
        alert("Erreur lors de l'enregistrement de la facture.");
      }
    } catch (error) {
      console.error("Erreur lors de l'enregistrement de la facture:", error);
    }
  };

  const handleValidate = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/api/next-facture-number/FN2`);
      const data = await response.json();
      handleSave('Validée', data.number);
    } catch (error) {
      console.error("Erreur lors de la génération du numéro de facture:", error);
    }
  };

  const handleDownloadPDF = () => {
    if (editData || factureInfo.numeroFacture) {
      const numero = editData ? editData.numeroFacture : factureInfo.numeroFacture;
      // Ouvre le lien de génération PDF dans un nouvel onglet
      window.open(`${API_BASE_URL}/api/factures-pdf/${numero}`, '_blank');
    } else {
      alert("Veuillez d'abord enregistrer la facture.");
    }
  };

  const handleSendEmail = async () => {
    const numero = editData ? editData.numeroFacture : factureInfo.numeroFacture;
    if (!numero || numero === 'Chargement...') {
      alert("Enregistrez la facture avant l'envoi.");
      return;
    }

    try {
      const response = await fetch(`${API_BASE_URL}/api/factures-email/${numero}`, { method: 'POST' });
      const data = await response.json();
      if (response.ok) {
        alert(data.message);
      } else {
        alert(`Erreur: ${data.error}`);
      }
    } catch (error) {
      console.error("Erreur envoi email:", error);
    }
  };

  const updateLigne = (id, field, value) => {
    setLignes(lignes.map(l => l.id === id ? { ...l, [field]: value } : l));
  };

  const addLigne = () => {
    const newId = lignes.length > 0 ? Math.max(...lignes.map(l => l.id)) + 1 : 1;
    setLignes([...lignes, { id: newId, description: '', quantite: 1, prixUnitaire: 0, taxable: true, type: 'prestation' }]);
  };

  const removeLigne = (id) => {
    setLignes(lignes.filter(l => l.id !== id));
  };

  const calculateSousTotalHT = () => lignes.reduce((t, l) => t + (l.quantite * l.prixUnitaire), 0);
  const calculateTVA = () => lignes.reduce((t, l) => l.taxable ? t + (l.quantite * l.prixUnitaire * tvaRate) : t, 0);
  const formatCurrency = (amount) => new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';

  const deboursTotal = lignes.filter(l => l.type === 'debour').reduce((t, l) => t + (l.quantite * l.prixUnitaire), 0);
  const prestationsTotal = lignes.filter(l => l.type === 'prestation').reduce((t, l) => t + (l.quantite * l.prixUnitaire), 0);

  const sousTotal = calculateSousTotalHT();
  const montantTVA = calculateTVA();
  const totalTTC = sousTotal + montantTVA;

  const isProforma = factureInfo.numeroFacture.startsWith('PRO');
  // Une facture est modifiable si c'est une proforma OU si l'utilisateur est admin
  const canEdit = isProforma || user?.role === 'admin';

  return (
    <div className="dashboard-page">
      <style>{`
        @media print {
          .facture-footer { 
            position: fixed !important; 
            bottom: 0 !important; 
            left: 0 !important; 
            right: 0 !important; 
            border-top: 1px solid #eee !important;
            padding-bottom: 20px !important;
            background: white !important;
          }
          .no-print { display: none !important; }
          @page { margin: 1.5cm; }
        }
      `}</style>

      <div className="page-header no-print" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
        <div>
          <h1 className="page-title">{editData ? 'Édition Document' : 'Nouvelle Facturation'}</h1>
          <p className="page-subtitle">Créez vos documents de facturation logistique</p>
        </div>
        <button className="btn btn-outline" onClick={onCancel}>
          <ArrowLeft size={18} />
          <span>Retour à la liste</span>
        </button>
      </div>

      <div className="form-container" style={{ maxWidth: '1040px', margin: '0 auto', padding: '40px' }}>
        <div className="facture-header-flex" style={{ display: 'flex', justifyContent: 'space-between', gap: '40px', marginBottom: '40px', alignItems: 'flex-start' }}>
          {/* Logo & Identité */}
          <div style={{ display: 'flex', gap: '24px', alignItems: 'center', minWidth: 'min-content' }}>
            <div style={{
              width: '120px', height: '120px',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              overflow: 'hidden'
            }}>
              <img
                src={`${API_BASE_URL}/assets/logo.jpeg`}
                alt="Logo"
                style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }}
                onError={(e) => {
                  e.target.onerror = null;
                  e.target.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1'/%3E%3Cpath d='M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.26 1.18 4.27 3 5.39'/%3E%3Cpath d='M11 13c1.33 0 2.67 0 4 0'/%3E%3Cpath d='M12 10v4'/%3E%3Cpath d='M11 10h2'/%3E%3C/svg%3E";
                }}
              />
            </div>
            <div>
              <h2 style={{ fontSize: '2rem', fontWeight: '800', margin: '0 0 4px 0', color: 'var(--text-primary)', letterSpacing: '-0.5px' }}>
                F2N LOGISTICS SARL
              </h2>
              <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', fontWeight: '700', letterSpacing: '1px', textTransform: 'uppercase', margin: '0 0 8px 0' }}>Commissionnaire en Douane Agréé</p>
              <div style={{ color: 'var(--text-secondary)', fontSize: '0.85rem', lineHeight: '1.4' }}>
                BP 4056 Douala - Bonapriso - CAMEROUN<br />
                Tél: +237 699 97 98 85 • NIU: M042517669133Q • RCCM : CM-DLA-01-2025-B12-000508
              </div>
            </div>
          </div>
          {/* Infos Facture */}
          <div style={{ textAlign: 'right', display: 'flex', flexDirection: 'column', gap: '12px', minWidth: '180px', flex: '1' }}>
            <div className="title-badge" style={{
              display: 'flex', justifyContent: 'center', alignItems: 'center', padding: '8px 24px', borderRadius: '8px',
              border: `2px solid ${isProforma ? 'var(--text-secondary)' : 'var(--accent-primary)'}`,
              color: 'var(--text-primary)',
              fontWeight: '800', fontSize: '1.25rem', letterSpacing: '2px', alignSelf: 'flex-end'
            }}>
              {isProforma ? 'PROFORMA' : 'FACTURE'}
            </div>

            <div className="info-inputs" style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', marginTop: '8px' }}>
              <div style={{ textAlign: 'right' }}>
                <label style={{ display: 'block', fontSize: '0.75rem', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '4px' }}>Numéro</label>
                <input type="text" className="form-control" style={{ width: '140px', padding: '8px 12px', border: '1px solid var(--border-color)', fontWeight: '700', color: 'var(--text-primary)', textAlign: 'right' }} name="numeroFacture" value={factureInfo.numeroFacture} readOnly />
              </div>
              <div style={{ textAlign: 'right' }}>
                <label style={{ display: 'block', fontSize: '0.75rem', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.5px', marginBottom: '4px' }}>Date d'émission</label>
                <input type="date" className="form-control" style={{ width: '140px', padding: '8px 12px', border: '1px solid var(--border-color)', color: 'var(--text-primary)', textAlign: 'right' }} name="date" value={factureInfo.date} onChange={handleInfoChange} disabled={!isProforma} />
              </div>
            </div>
          </div>
        </div>

        {/* --- CONTEXTE DU DOSSIER --- */}
        <div className="facture-context-grid" style={{
          display: 'flex', gap: '24px', marginBottom: '32px', flexWrap: 'wrap',
          border: '1px solid var(--border-color)', borderRadius: '8px', padding: '24px'
        }}>
          {/* Colonne Client */}
          <div style={{ flex: '1', minWidth: '280px', borderRight: '1px solid var(--border-color)', paddingRight: '24px' }} className="context-col">
            <h3 style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '1px', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <User size={16} /> Facturé à
            </h3>
            <div style={{ marginBottom: '12px' }}>
              <span style={{ fontSize: '1.25rem', fontWeight: '700', color: 'var(--text-primary)', display: 'block', marginBottom: '4px' }}>{selectedDossier.client_nom}</span>
              {selectedDossier.client_nif && <span style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', display: 'block' }}>NINEA / NIU: {selectedDossier.client_nif}</span>}
              {selectedDossier.client_rccm && <span style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', display: 'block' }}>RCCM: {selectedDossier.client_rccm}</span>}
              {selectedDossier.client_tel && <span style={{ color: 'var(--text-secondary)', fontSize: '0.9rem', display: 'block' }}>Tél: {selectedDossier.client_tel}</span>}
            </div>
            {isProforma && (
              <div className="no-print">
                <label style={{ display: 'block', fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '16px', marginBottom: '4px' }}>Changer le Dossier Lié</label>
                <select className="form-control" style={{ width: '100%', padding: '8px 12px', fontSize: '0.9rem', border: '1px solid var(--border-color)' }} name="dossierLie" value={factureInfo.dossierLie} onChange={handleInfoChange}>
                  <option value="">Sélectionner un dossier...</option>
                  {availableDossiers.map(d => (
                    <option key={d.id} value={d.id}>{d.id} - {d.natureMarchandise}</option>
                  ))}
                </select>
              </div>
            )}
            <div style={{ display: 'none', marginTop: '16px' }} className="print-only">
              <label style={{ display: 'block', fontSize: '0.75rem', color: 'var(--text-secondary)', marginBottom: '4px' }}>Dossier Lié</label>
              <span style={{ color: 'var(--text-primary)', fontWeight: '600' }}>{factureInfo.dossierLie}</span>
            </div>
          </div>

          {/* Colonne Expédition */}
          <div style={{ flex: '2', minWidth: '280px' }}>
            <h3 className="section-title-icon" style={{ fontSize: '0.85rem', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '1px', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Box size={16} /> Détails de l'Expédition
            </h3>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '16px 24px' }}>
              <div>
                <span style={{ color: 'var(--text-secondary)', fontSize: '0.75rem', display: 'flex', alignItems: 'center', gap: '4px' }}><Hash size={12} /> B/L / LTA</span>
                <span style={{ color: 'var(--text-primary)', fontWeight: '600', fontSize: '0.95rem' }}>{selectedDossier.numBL}</span>
              </div>
              <div>
                <span style={{ color: 'var(--text-secondary)', fontSize: '0.75rem', display: 'flex', alignItems: 'center', gap: '4px' }}><Ship size={12} /> Navire / Voyage</span>
                <span style={{ color: 'var(--text-primary)', fontWeight: '600', fontSize: '0.95rem' }}>{selectedDossier.navire} {selectedDossier.numVoyage ? `/ V.${selectedDossier.numVoyage}` : ''}</span>
              </div>
              <div>
                <span style={{ color: 'var(--text-secondary)', fontSize: '0.75rem', display: 'flex', alignItems: 'center', gap: '4px' }}><MapPin size={12} /> Routage</span>
                <span style={{ color: 'var(--text-primary)', fontWeight: '600', fontSize: '0.95rem' }}>{selectedDossier.origine} → {selectedDossier.destination}</span>
              </div>
              <div>
                <span style={{ color: 'var(--text-secondary)', fontSize: '0.75rem', display: 'flex', alignItems: 'center', gap: '4px' }}><Box size={12} /> Poids / Volume / Colis</span>
                <span style={{ color: 'var(--text-primary)', fontWeight: '600', fontSize: '0.95rem' }}>{selectedDossier.poids} kg / {selectedDossier.volume} CBM / {selectedDossier.nombresColis}</span>
              </div>
            </div>
          </div>
        </div>

        {/* --- IMPORT DÉBOURS --- */}
        {pendingDebours.length > 0 && isProforma && (
          <div className="no-print" style={{ marginBottom: '24px', backgroundColor: 'var(--accent-primary-light)', padding: '16px', borderRadius: '8px', border: '1px dashed var(--accent-primary)' }}>
            <h4 style={{ fontSize: '0.85rem', marginBottom: '12px', color: 'var(--accent-primary)', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Download size={16} /> Débours en attente pour ce dossier
            </h4>
            <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
              {pendingDebours.map(db => (
                <button key={db.id} type="button" onClick={() => handleImportDebour(db)} className="btn btn-outline" style={{ fontSize: '0.75rem', padding: '4px 8px' }}>
                  + {db.description} ({formatCurrency(db.montant)})
                </button>
              ))}
            </div>
          </div>
        )}

        {/* --- LIGNES DE FACTURE --- */}
        <div style={{ marginBottom: '32px' }} className="data-table-container">
          <table style={{ width: '100%', borderCollapse: 'collapse' }} className="data-table">
            <thead>
              <tr>
                <th style={{ textAlign: 'left', padding: '12px 16px', color: 'var(--text-secondary)', fontSize: '0.75rem', textTransform: 'uppercase', borderBottom: '2px solid var(--border-color)' }}>Désignation des Frais</th>
                <th style={{ textAlign: 'center', padding: '12px 16px', color: 'var(--text-secondary)', fontSize: '0.75rem', textTransform: 'uppercase', borderBottom: '2px solid var(--border-color)' }}>Qté</th>
                <th style={{ textAlign: 'right', padding: '12px 16px', color: 'var(--text-secondary)', fontSize: '0.75rem', textTransform: 'uppercase', borderBottom: '2px solid var(--border-color)' }}>Prix Unitaire</th>
                <th className="no-print" style={{ textAlign: 'center', padding: '12px 16px', color: 'var(--text-secondary)', fontSize: '0.75rem', textTransform: 'uppercase', borderBottom: '2px solid var(--border-color)' }}>TVA</th>
                <th style={{ textAlign: 'right', padding: '12px 16px', color: 'var(--text-secondary)', fontSize: '0.75rem', textTransform: 'uppercase', borderBottom: '2px solid var(--border-color)' }}>Montant Total</th>
                <th className="no-print" style={{ width: '40px', borderBottom: '2px solid var(--border-color)' }}></th>
              </tr>
            </thead>
            <tbody>
              {lignes.map((ligne) => (
                <tr key={ligne.id}>
                  <td style={{ padding: '8px 16px', borderBottom: '1px solid var(--border-color)' }}>
                    <input type="text" list="designations-list" className="form-control" style={{ width: '100%', padding: '8px', border: '1px solid transparent', fontWeight: '700' }} value={ligne.description} onChange={(e) => updateLigne(ligne.id, 'description', e.target.value)} placeholder="Description des frais..." readOnly={!canEdit} />
                    <datalist id="designations-list">
                      {LOGISTICS_DESIGNATIONS.map(d => <option key={d} value={d} />)}
                    </datalist>
                  </td>
                  <td style={{ padding: '8px 16px', borderBottom: '1px solid var(--border-color)' }}>
                    <input type="number" className="form-control" style={{ width: '100%', padding: '8px', border: '1px solid transparent', textAlign: 'center', fontWeight: '700' }} value={ligne.quantite} onChange={(e) => updateLigne(ligne.id, 'quantite', parseFloat(e.target.value) || 0)} readOnly={!canEdit} />
                  </td>
                  <td style={{ padding: '8px 16px', borderBottom: '1px solid var(--border-color)' }}>
                    <input type="number" className="form-control" style={{ width: '100%', padding: '8px', border: '1px solid transparent', textAlign: 'right', fontWeight: '700' }} value={ligne.prixUnitaire} onChange={(e) => updateLigne(ligne.id, 'prixUnitaire', parseFloat(e.target.value) || 0)} readOnly={!canEdit} />
                  </td>
                  <td className="no-print" style={{ padding: '8px 16px', textAlign: 'center', borderBottom: '1px solid var(--border-color)' }}>
                    <input type="checkbox" checked={ligne.taxable} onChange={(e) => updateLigne(ligne.id, 'taxable', e.target.checked)} style={{ width: '18px', height: '18px', accentColor: 'var(--accent-secondary)' }} disabled={!canEdit} />
                  </td>
                  <td style={{ padding: '8px 16px', textAlign: 'right', fontWeight: '700', color: 'var(--text-primary)', borderBottom: '1px solid var(--border-color)' }}>
                    {formatCurrency(ligne.quantite * ligne.prixUnitaire)}
                  </td>
                  <td className="no-print" style={{ padding: '8px 16px', textAlign: 'center', borderBottom: '1px solid var(--border-color)' }}>
                    {canEdit && (
                      <button type="button" onClick={() => removeLigne(ligne.id)} style={{ background: 'none', border: 'none', color: 'var(--accent-danger)', cursor: 'pointer', padding: '4px' }}>
                        <Trash2 size={18} />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {canEdit && (
            <button type="button" className="btn btn-outline no-print" onClick={addLigne} style={{ marginTop: '16px' }}>
              <Plus size={16} /> Ajouter une ligne
            </button>
          )}
        </div>

        {/* --- RÉSUMÉ FINANCIER --- */}
        <div className="facture-summary-grid" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '24px' }}>

          <div className="payment-box" style={{ flex: '1', minWidth: '280px', color: 'var(--text-secondary)', fontSize: '0.85rem', border: '1px solid var(--border-color)', padding: '16px', borderRadius: '8px' }}>
            <p style={{ marginBottom: '8px', color: 'var(--text-primary)' }}><strong>Conditions de paiement :</strong></p>
            <p>Paiement à réception de la facture par chèque à l'ordre de <strong>F2N LOGISTICS</strong> ou par virement bancaire :</p>
            <p style={{ marginTop: '8px', color: 'var(--text-primary)' }}><strong>First Bank :</strong> 10005 00002 10137791001-95</p>
          </div>

          <div className="totals-box" style={{ width: '100%', maxWidth: '380px', border: '2px solid var(--border-color)', borderRadius: '8px', padding: '24px', marginLeft: 'auto' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '12px', fontSize: '1rem' }}>
              <span style={{ color: 'var(--text-secondary)' }}>Sous-total HT</span>
              <span style={{ fontWeight: '600', color: 'var(--text-primary)' }}>{formatCurrency(sousTotal)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '16px', fontSize: '1rem' }}>
              <span style={{ color: 'var(--text-secondary)' }}>TVA (19,25%)</span>
              <span style={{ fontWeight: '600', color: 'var(--text-primary)' }}>{formatCurrency(montantTVA)}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: '16px', borderTop: '2px solid var(--border-color)', fontSize: '1.25rem', fontWeight: '800', color: 'var(--text-primary)' }}>
              <span>TOTAL TTC</span>
              <span>{formatCurrency(totalTTC)}</span>
            </div>
          </div>

        </div>

        {/* --- BAS DE PAGE (FOOTER) --- */}
        <div className="facture-footer" style={{ marginTop: '40px', paddingTop: '16px', borderTop: '1px solid var(--border-color)', textAlign: 'center', fontSize: '0.75rem', color: 'var(--text-secondary)', lineHeight: '1.6' }}>
          <p style={{ margin: 0, fontWeight: '600', color: 'var(--text-primary)' }}>F2N LOGISTICS / SOCIETE A RESPONSABILITE LIMITE au capital de 10 000 000 FCFA - BP 4056 Douala - Bonapriso - CAMEROUN</p>
          <p style={{ margin: '4px 0' }}>N° RCCM : CM-DLA-01-2025-B12-000508 / NIU : M042517669133Q / N° CNPS : 351-0126148-000H</p>
          <p style={{ margin: '4px 0' }}>Compte First Bank N° 10005 00002 10137791001-95</p>
          <p style={{ margin: '4px 0' }}>Tél: +237 674 573 495 / +237 679 517 186 / +237 699 97 98 85</p>
          <p style={{ margin: '4px 0' }}>Email: f2nlogistics@gmail.com / franklin.ngangoua@f2nlogistics.com - www.f2nlogistics.com</p>
        </div>
      </div>

      {/* --- BOUTONS D'ACTION (Hors Document) --- */}
      <div className="form-actions no-print" style={{ maxWidth: '1040px', margin: '24px auto 0 auto', borderTop: 'none', padding: '0', display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: '16px' }}>
        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
          <button type="button" className="btn btn-outline" onClick={onCancel} style={{ marginRight: '8px' }}>
            <ArrowLeft size={18} />
            Retour
          </button>
          <button type="button" className="btn btn-outline" onClick={() => window.print()}>
            <Printer size={18} />
            Imprimer
          </button>
          <button type="button" className="btn btn-outline" onClick={handleDownloadPDF} title="Générer un PDF propre via le serveur">
            <FileDown size={18} />
            Télécharger PDF
          </button>
          {!isProforma && (
            <button type="button" className="btn btn-outline" style={{ color: 'var(--accent-secondary)' }} onClick={handleSendEmail}>
              <Mail size={18} />
              Envoyer par Email
            </button>
          )}
        </div>
        <div style={{ display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
          <button type="button" className="btn btn-outline" onClick={() => handleSave('Proforma')}>
            <Save size={18} />
            Enregistrer Proforma
          </button>
          {!isProforma ? (
            user?.role === 'admin' ? (
              <button type="button" className="btn btn-primary" onClick={() => handleSave('Validée')}>
                <Save size={18} />
                Enregistrer les modifications
              </button>
            ) : (
              <div style={{ padding: '8px 16px', display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--accent-secondary)', fontWeight: 'bold' }}>
                <CheckCircle size={18} /> Facture Validée
              </div>
            )
          ) : (
            <button type="button" className="btn btn-primary" onClick={handleValidate}>
              <CheckCircle size={18} />
              Valider la Facture
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default FacturationForm;
