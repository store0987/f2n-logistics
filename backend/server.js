const express = require('express');
const cors = require('cors');
const path = require('path');
const bodyParser = require('body-parser');
const mysql = require('mysql2/promise'); // Utilisation de mysql2/promise pour les requêtes asynchrones

const app = express();
const PORT = process.env.PORT || 5000;
const DB_PATH = process.env.DB_PATH || path.join(__dirname, 'logistics.db');

// Middleware
app.use(cors());
app.use(bodyParser.json());

// MySQL Connection Pool
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'logistics_billing',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

pool.getConnection()
  .then(connection => {
    console.log('Connecté à la base de données MySQL.');
    connection.release();

    // Création des tables si elles n'existent pas
    const createTables = async () => {
      try {
        await pool.execute(`
          CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(255) NOT NULL,
            nom VARCHAR(255) NOT NULL,
            nif VARCHAR(255),
            rccm VARCHAR(255),
            contact VARCHAR(255),
            email VARCHAR(255),
            tel VARCHAR(255),
            adresse TEXT,
            ville VARCHAR(255)
          )
        `);

        await pool.execute(`
          CREATE TABLE IF NOT EXISTS dossiers (
            id VARCHAR(255) PRIMARY KEY,
            typeOperation VARCHAR(255),
            modeTransport VARCHAR(255),
            numBL VARCHAR(255),
            incoterm VARCHAR(255),
            compagnie VARCHAR(255),
            navire VARCHAR(255),
            numVoyage VARCHAR(255),
            etd DATE,
            eta DATE,
            origine VARCHAR(255),
            destination VARCHAR(255),
            client_id INT,
            expediteur VARCHAR(255),
            natureMarchandise TEXT,
            nombresColis VARCHAR(255),
            typeConteneur VARCHAR(255),
            poids VARCHAR(255),
            volume VARCHAR(255),
            valeurMarchandise VARCHAR(255),
            dateCreation DATE,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
          )
        `);

        await pool.execute(`
          CREATE TABLE IF NOT EXISTS factures (
            numeroFacture VARCHAR(255) PRIMARY KEY,
            date DATE,
            dossier_id VARCHAR(255),
            client_id INT,
            statut VARCHAR(255),
            sousTotal DECIMAL(10, 2),
            montantTva DECIMAL(10, 2),
            totalTtc DECIMAL(10, 2),
            FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE SET NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
          )
        `);

        await pool.execute(`
          CREATE TABLE IF NOT EXISTS facture_lignes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            facture_id VARCHAR(255),
            description TEXT,
            quantite DECIMAL(10, 2),
            prixUnitaire DECIMAL(10, 2),
            taxable BOOLEAN,
            FOREIGN KEY (facture_id) REFERENCES factures(numeroFacture) ON DELETE CASCADE
          )
        `);

        await pool.execute(`
          CREATE TABLE IF NOT EXISTS debours (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE,
            dossier_id VARCHAR(255),
            description TEXT,
            montant DECIMAL(10, 2),
            statut VARCHAR(50) DEFAULT 'En attente',
            facture_id VARCHAR(255),
            FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE SET NULL,
            FOREIGN KEY (facture_id) REFERENCES factures(numeroFacture) ON DELETE SET NULL
          )
        `);
        console.log('Tables MySQL initialisées.');
      } catch (err) {
        console.error('Erreur lors de la création des tables MySQL:', err.message);
      }
    };
    createTables();
  })
  .catch(err => {
    console.error('Erreur lors de la connexion à la base de données MySQL:', err.message);
  });

// Helper to normalize keys to lowercase
function normalizeKeys(obj) {
  const out = {};
  Object.keys(obj || {}).forEach(k => {
    out[k.toLowerCase()] = obj[k];
  });
  return out;
}

// ==========================================
// ROUTES API
// ==========================================

app.get('/', (req, res) => {
  res.send('API Backend F2N Logistics en ligne.');
});

// --- CLIENTS ---
app.get('/api/clients', async (req, res) => {
  try {
    const [rows] = await pool.execute("SELECT * FROM clients");
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.post('/api/clients', async (req, res) => {
  const payload = normalizeKeys(req.body);
  try {
    const { type, nom, nif, rccm, contact, email, tel, adresse, ville } = payload;
    const query = `INSERT INTO clients (type, nom, nif, rccm, contact, email, tel, adresse, ville) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`;
    const [result] = await pool.execute(query, [type, nom, nif, rccm, contact, email, tel, adresse, ville]);
    res.json({ id: result.insertId, message: "Client ajouté avec succès" });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.put('/api/clients/:id', async (req, res) => {
  const { id } = req.params;
  const payload = normalizeKeys(req.body);
  try {
    const { type, nom, nif, rccm, contact, email, tel, adresse, ville } = payload;
    const query = `UPDATE clients SET type = ?, nom = ?, nif = ?, rccm = ?, contact = ?, email = ?, tel = ?, adresse = ?, ville = ? WHERE id = ?`;
    await pool.execute(query, [type, nom, nif, rccm, contact, email, tel, adresse, ville, id]);
    res.json({ message: "Client mis à jour avec succès" });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.delete('/api/clients/:id', async (req, res) => {
  const { id } = req.params;
  try {
    await pool.execute("DELETE FROM clients WHERE id = ?", [id]);
    res.json({ message: "Client supprimé avec succès" });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// --- DOSSIERS ---
app.get('/api/dossiers', async (req, res) => {
  try {
    const query = `
      SELECT d.*, c.nom as client_nom
      FROM dossiers d
      LEFT JOIN clients c ON d.client_id = c.id
    `;
    const [rows] = await pool.execute(query);
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.post('/api/dossiers', async (req, res) => {
  // Simplification : on s'attend à un objet complet dans le body
  const data = req.body;
  const prefix = data.typeOperation === 'Import' ? 'IMP' : data.typeOperation === 'Export' ? 'EXP' : 'TRS';
  const year = new Date().getFullYear();
  const pattern = `${prefix}-${year}-%`;
  try {
    // Calculer le prochain numéro de dossier de manière fiable basé sur la DB
    const [rows] = await pool.execute(`SELECT id FROM dossiers WHERE id LIKE ? ORDER BY id DESC LIMIT 1`, [pattern]);

    let nextNum = 1;
    if (rows.length > 0) {
      const parts = rows[0].id.split('-');
      const lastNum = parseInt(parts[parts.length - 1]);
      if (!isNaN(lastNum)) nextNum = lastNum + 1;
    }

    const id = data.id || `${prefix}-${year}-${String(nextNum).padStart(3, '0')}`;

    const query = `INSERT INTO dossiers (
      id, typeOperation, modeTransport, numBL, incoterm, compagnie, navire, numVoyage,
      etd, eta, origine, destination, client_id, expediteur, natureMarchandise,
      nombresColis, typeConteneur, poids, volume, valeurMarchandise, dateCreation
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;

    const params = [
      id, data.typeOperation, data.modeTransport, data.numBL, data.incoterm,
      data.compagnie, data.navire, data.numVoyage, data.etd, data.eta,
      data.origine, data.destination, data.client_id, data.expediteur,
      data.natureMarchandise, data.nombresColis, data.typeConteneur,
      data.poids, data.volume, data.valeurMarchandise,
      data.dateCreation || new Date().toISOString().split('T')[0]
    ];

    await pool.execute(query, params);
    res.json({ id: id, message: "Dossier créé avec succès" });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.put('/api/dossiers/:id', async (req, res) => {
  const { id } = req.params;
  const data = req.body;
  try {
    const query = `UPDATE dossiers SET typeOperation = ?, modeTransport = ?, numBL = ?, incoterm = ?, compagnie = ?, navire = ?, numVoyage = ?, etd = ?, eta = ?, origine = ?, destination = ?, client_id = ?, expediteur = ?, natureMarchandise = ?, nombresColis = ?, typeConteneur = ?, poids = ?, volume = ?, valeurMarchandise = ? WHERE id = ?`;
    const params = [
      data.typeOperation, data.modeTransport, data.numBL, data.incoterm,
      data.compagnie, data.navire, data.numVoyage, data.etd, data.eta,
      data.origine, data.destination, data.client_id, data.expediteur,
      data.natureMarchandise, data.nombresColis, data.typeConteneur,
      data.poids, data.volume, data.valeurMarchandise, id
    ];
    await pool.execute(query, params);
    res.json({ message: "Dossier mis à jour avec succès" });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.delete('/api/dossiers/:id', async (req, res) => {
  const { id } = req.params;
  try {
    await pool.execute("DELETE FROM dossiers WHERE id = ?", [id]);
    res.json({ message: "Dossier supprimé avec succès" });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Endpoint pour obtenir le prochain numéro de facture ou proforma séquentiel
app.get('/api/next-facture-number/:type', async (req, res) => {
  const { type } = req.params; // 'PRO' ou 'FACT'
  const year = new Date().getFullYear();
  const pattern = `${type}-${year}-%`;
  try {
    const [rows] = await pool.execute(`SELECT numeroFacture FROM factures WHERE numeroFacture LIKE ? ORDER BY numeroFacture DESC LIMIT 1`, [pattern]);

    let nextNum = 1;
    if (rows.length > 0) {
      const parts = rows[0].numeroFacture.split('-');
      const lastNum = parseInt(parts[parts.length - 1]);
      if (!isNaN(lastNum)) nextNum = lastNum + 1;
    }

    const number = `${type}-${year}-${String(nextNum).padStart(3, '0')}`;
    res.json({ number });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// --- FACTURES ---
app.get('/api/factures', async (req, res) => {
  try {
    const query = `
      SELECT f.*, c.nom as client_nom
      FROM factures f
      LEFT JOIN clients c ON f.client_id = c.id
    `;
    const [rows] = await pool.execute(query);
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/factures/:numeroFacture', async (req, res) => {
  const { numeroFacture } = req.params;
  try {
    const queryFacture = `
      SELECT f.*, c.nom as client_nom
      FROM factures f
      LEFT JOIN clients c ON f.client_id = c.id
      WHERE f.numeroFacture = ?
    `;
    const [factures] = await pool.execute(queryFacture, [numeroFacture]);
    const facture = factures[0];

    if (!facture) return res.status(404).json({ error: "Facture non trouvée" });

    const [lignes] = await pool.execute("SELECT * FROM facture_lignes WHERE facture_id = ?", [numeroFacture]);
    res.json({ factureInfo: facture, lignes });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.delete('/api/factures/:numeroFacture', async (req, res) => {
  const { numeroFacture } = req.params;
  let connection;
  try {
    connection = await pool.getConnection();
    await connection.beginTransaction();

    await connection.execute("DELETE FROM facture_lignes WHERE facture_id = ?", [numeroFacture]);
    await connection.execute("DELETE FROM factures WHERE numeroFacture = ?", [numeroFacture]);

    await connection.commit();
    res.json({ message: "Facture supprimée avec succès" });
  } catch (err) {
    if (connection) await connection.rollback();
    res.status(500).json({ error: err.message });
  } finally {
    if (connection) connection.release();
  }
});

app.post('/api/factures', async (req, res) => {
  const { factureInfo, lignes, totaux } = req.body;
  let connection;
  try {
    connection = await pool.getConnection();
    await connection.beginTransaction();

    const { numeroFacture, date, dossierLie, client_id, statut } = factureInfo;

    // Convert empty string dossierLie to null for database insertion if no dossier is linked
    const finalDossierLie = dossierLie === '' ? null : dossierLie;

    // Upsert (INSERT ... ON DUPLICATE KEY UPDATE) l'en-tête de la facture
    const queryFacture = `
      INSERT INTO factures (numeroFacture, date, dossier_id, client_id, statut, sousTotal, montantTva, totalTtc)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        date = VALUES(date),
        dossier_id = VALUES(dossier_id),
        client_id = VALUES(client_id),
        statut = VALUES(statut),
        sousTotal = VALUES(sousTotal),
        montantTva = VALUES(montantTva),
        totalTtc = VALUES(totalTtc)
    `;

    await connection.execute(queryFacture, [numeroFacture, date, finalDossierLie, client_id, statut, totaux.sousTotal, totaux.montantTva, totaux.totalTtc]);

    // Supprimer les anciennes lignes puis ré-insérer (pour la mise à jour)
    await connection.execute("DELETE FROM facture_lignes WHERE facture_id = ?", [numeroFacture]);

    if (lignes && lignes.length > 0) {
      const ligneValues = lignes.map(ligne => [
        numeroFacture,
        ligne.description,
        ligne.quantite,
        ligne.prixUnitaire,
        ligne.taxable ? 1 : 0
      ]);
      const insertLignesQuery = `INSERT INTO facture_lignes (facture_id, description, quantite, prixUnitaire, taxable) VALUES ?`;
      await connection.query(insertLignesQuery, [ligneValues]);
    }

    await connection.commit();
    res.json({ numeroFacture, message: "Facture enregistrée avec succès" });
  } catch (err) {
    if (connection) await connection.rollback();
    console.error('Erreur lors de l\'enregistrement de la facture:', err.message);
    res.status(500).json({ error: err.message });
  } finally {
    if (connection) connection.release();
  }
});

// En production, servir le build frontend depuis dist
const distPath = path.join(__dirname, '..', 'dist');
if (process.env.NODE_ENV === 'production') {
  app.use(express.static(distPath));
  app.get('*', (req, res) => {
    res.sendFile(path.join(distPath, 'index.html'));
  });
}

// Démarrage du serveur
// If run directly, start the server; otherwise export app for serverless wrapper
if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`Serveur Backend en cours d'exécution sur le port ${PORT}`);
  });
} else {
  module.exports = app;
}
