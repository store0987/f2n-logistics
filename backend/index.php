<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Création de la table users si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Table clients
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Table dossiers
    $pdo->exec("CREATE TABLE IF NOT EXISTS dossiers (
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
        deviseValeur VARCHAR(10) DEFAULT 'EUR',
        dateCreation DATE,
        statutFacturation VARCHAR(255) DEFAULT 'À Facturer',
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Table factures
    $pdo->exec("CREATE TABLE IF NOT EXISTS factures (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Table facture_lignes
    $pdo->exec("CREATE TABLE IF NOT EXISTS facture_lignes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        facture_id VARCHAR(255),
        description TEXT,
        quantite DECIMAL(10, 2),
        prixUnitaire DECIMAL(10, 2),
        taxable TINYINT(1) DEFAULT 0,
        FOREIGN KEY (facture_id) REFERENCES factures(numeroFacture) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Table debours
    $pdo->exec("CREATE TABLE IF NOT EXISTS debours (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE,
        dossier_id VARCHAR(255),
        description TEXT,
        montant DECIMAL(10, 2),
        statut VARCHAR(50) DEFAULT 'En attente',
        facture_id VARCHAR(255),
        FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE SET NULL,
        FOREIGN KEY (facture_id) REFERENCES factures(numeroFacture) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(["error" => "Erreur de connexion : " . $e->getMessage()]));
}

// --- ROUTAGE SIMPLE ---
$method = $_SERVER['REQUEST_METHOD'];
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$uri_parts = explode('/', $uri);

// On cherche la ressource juste après "api" dans l'URL
$api_pos = array_search('api', $uri_parts);
$resource = ($api_pos !== false && isset($uri_parts[$api_pos + 1])) ? $uri_parts[$api_pos + 1] : '';
$id = null;

// Si on a un ID après la ressource (ex: /api/clients/123)
if ($api_pos !== false && isset($uri_parts[$api_pos + 2])) {
    $id = $uri_parts[$api_pos + 2];
}

$input = json_decode(file_get_contents('php://input'), true);

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    switch ($resource) {
        case 'register':
            if ($method === 'POST') {
                $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute([$input['username'], $input['email'], $hashedPassword]);
                    respond(["message" => "Utilisateur créé avec succès", "id" => $pdo->lastInsertId()]);
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        respond(["error" => "Le nom d'utilisateur ou l'email existe déjà."], 400);
                    }
                    throw $e;
                }
            }
            break;

        case 'login':
            if ($method === 'POST') {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$input['username']]);
                $user = $stmt->fetch();

                if ($user && password_verify($input['password'], $user['password'])) {
                    // En production, vous utiliseriez un JWT ici. 
                    // Pour la simplicité sur InfinityFree, on renvoie les infos de base.
                    respond([
                        "id" => $user['id'],
                        "username" => $user['username'],
                        "email" => $user['email'],
                        "message" => "Connexion réussie"
                    ]);
                } else {
                    respond(["error" => "Identifiants incorrects."], 401);
                }
            }
            break;

        case 'change-password':
            if ($method === 'POST') {
                $userId = $input['userId'];
                $oldPass = $input['oldPassword'];
                $newPass = $input['newPassword'];

                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if ($user && password_verify($oldPass, $user['password'])) {
                    $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $userId]);
                    respond(["message" => "Mot de passe mis à jour avec succès"]);
                } else {
                    respond(["error" => "Ancien mot de passe incorrect."], 401);
                }
            }
            break;

        case 'clients':
            if ($method === 'GET') {
                $stmt = $pdo->query("SELECT * FROM clients");
                respond($stmt->fetchAll());
            } elseif ($method === 'POST') {
                $sql = "INSERT INTO clients (type, nom, nif, rccm, contact, email, tel, adresse, ville) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([
                    $input['type'] ?? '', $input['nom'] ?? '', $input['nif'] ?? null, $input['rccm'] ?? null, 
                    $input['contact'] ?? null, $input['email'] ?? null, $input['tel'] ?? null, 
                    $input['adresse'] ?? null, $input['ville'] ?? null
                ]);
                respond(["id" => $pdo->lastInsertId(), "message" => "Client ajouté"]);
            } elseif ($method === 'PUT' && $id) {
                $sql = "UPDATE clients SET type=?, nom=?, nif=?, rccm=?, contact=?, email=?, tel=?, adresse=?, ville=? WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $input['type'] ?? '', $input['nom'] ?? '', $input['nif'] ?? null, $input['rccm'] ?? null, 
                    $input['contact'] ?? null, $input['email'] ?? null, $input['tel'] ?? null, 
                    $input['adresse'] ?? null, $input['ville'] ?? null, $id
                ]);
                respond(["message" => "Client mis à jour"]);
            } elseif ($method === 'DELETE' && $id) {
                $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
                respond(["message" => "Client supprimé"]);
            }
            break;

        case 'dossiers':
            if ($method === 'GET') {
                $sql = "SELECT d.*, c.nom as client_nom FROM dossiers d LEFT JOIN clients c ON d.client_id = c.id";
                respond($pdo->query($sql)->fetchAll());
            } elseif ($method === 'POST') {
                $typeOp = $input['typeOperation'] ?? 'Import';
                $prefix = ($typeOp === 'Import') ? 'IMP' : (($typeOp === 'Export') ? 'EXP' : 'TRS');
                $year = date("Y");
                $pattern = "$prefix-$year-%";
                $stmt = $pdo->prepare("SELECT id FROM dossiers WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$pattern]);
                $last = $stmt->fetchColumn();
                
                $nextNum = 1;
                if ($last) {
                    $parts = explode('-', $last);
                    $nextNum = intval(end($parts)) + 1;
                }
                $newId = $input['id'] ?? ($prefix . "-" . $year . "-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT));
                
                $sql = "INSERT INTO dossiers (id, typeOperation, modeTransport, numBL, incoterm, compagnie, navire, numVoyage, etd, eta, origine, destination, client_id, expediteur, natureMarchandise, nombresColis, typeConteneur, poids, volume, valeurMarchandise, dateCreation, statutFacturation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $pdo->prepare($sql)->execute([
                    $newId, $typeOp, $input['modeTransport'] ?? null, $input['numBL'] ?? null, $input['incoterm'] ?? null,
                    $input['compagnie'] ?? null, $input['navire'] ?? null, $input['numVoyage'] ?? null, $input['etd'] ?? null, $input['eta'] ?? null,
                    $input['origine'] ?? null, $input['destination'] ?? null, $input['client_id'] ?? null, $input['expediteur'] ?? null,
                    $input['natureMarchandise'] ?? null, $input['nombresColis'] ?? null, $input['typeConteneur'] ?? null,
                    $input['poids'] ?? null, $input['volume'] ?? null, $input['valeurMarchandise'] ?? null,
                    $input['dateCreation'] ?? date("Y-m-d"),
                    $input['statutFacturation'] ?? 'À Facturer'
                ]);
                respond(["id" => $newId, "message" => "Dossier créé"]);
            } elseif ($method === 'PUT' && $id) {
                $sql = "UPDATE dossiers SET typeOperation=?, modeTransport=?, numBL=?, incoterm=?, compagnie=?, navire=?, numVoyage=?, etd=?, eta=?, origine=?, destination=?, client_id=?, expediteur=?, natureMarchandise=?, nombresColis=?, typeConteneur=?, poids=?, volume=?, valeurMarchandise=?, statutFacturation=? WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $input['typeOperation'] ?? 'Import',
                    $input['modeTransport'] ?? null, $input['numBL'] ?? null, $input['incoterm'] ?? null,
                    $input['compagnie'] ?? null, $input['navire'] ?? null, $input['numVoyage'] ?? null,
                    !empty($input['etd']) ? $input['etd'] : null,
                    !empty($input['eta']) ? $input['eta'] : null,
                    $input['origine'] ?? null, $input['destination'] ?? null,
                    !empty($input['client_id']) ? $input['client_id'] : null,
                    $input['expediteur'] ?? null, $input['natureMarchandise'] ?? null,
                    $input['nombresColis'] ?? null, $input['typeConteneur'] ?? null,
                    $input['poids'] ?? null, $input['volume'] ?? null, $input['valeurMarchandise'] ?? null,
                    $input['deviseValeur'] ?? 'EUR',
                    $input['statutFacturation'] ?? 'À Facturer',
                    $id
                ]);
                respond(["message" => "Dossier mis à jour"]);
            } elseif ($method === 'DELETE' && $id) {
                // Optionnel : On peut vérifier si des factures sont liées avant de supprimer
                $check = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE dossier_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() > 0) {
                    respond(["error" => "Impossible de supprimer ce dossier car il est lié à une facture."], 400);
                }
                
                $pdo->prepare("DELETE FROM dossiers WHERE id = ?")->execute([$id]);
                respond(["message" => "Dossier supprimé"]);
            }
            break;

        case 'debours':
            if ($method === 'GET') {
                if (isset($_GET['dossier_id'])) {
                    // Récupérer les débours en attente pour un dossier spécifique
                    $stmt = $pdo->prepare("SELECT * FROM debours WHERE dossier_id = ? AND statut = 'En attente'");
                    $stmt->execute([$_GET['dossier_id']]);
                    respond($stmt->fetchAll());
                } else {
                    // Liste globale
                    $sql = "SELECT db.*, c.nom as client_nom FROM debours db 
                            LEFT JOIN dossiers d ON db.dossier_id = d.id 
                            LEFT JOIN clients c ON d.client_id = c.id ORDER BY db.date DESC";
                    respond($pdo->query($sql)->fetchAll());
                }
            } elseif ($method === 'POST') {
                $sql = "INSERT INTO debours (date, dossier_id, description, montant, statut) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([
                    $input['date'] ?? date('Y-m-d'),
                    $input['dossier_id'],
                    $input['description'],
                    $input['montant'],
                    'En attente'
                ]);
                respond(["id" => $pdo->lastInsertId(), "message" => "Débours enregistré"]);
            } elseif ($method === 'PUT' && $id) {
                $sql = "UPDATE debours SET date=?, dossier_id=?, description=?, montant=? WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $input['date'] ?? date('Y-m-d'),
                    $input['dossier_id'],
                    $input['description'],
                    $input['montant'],
                    $id
                ]);
                respond(["message" => "Débours mis à jour"]);
            } elseif ($method === 'DELETE' && $id) {
                $pdo->prepare("DELETE FROM debours WHERE id = ?")->execute([$id]);
                respond(["message" => "Débours supprimé"]);
            }
            break;

        case 'next-facture-number':
        // Note: l'URL attendue est /api/next-facture-number/PRO ou FACT
        $type = $id; // Dans ce cas, l'ID est le type (PRO ou FACT)
        $year = date("Y");
        $pattern = "$type-$year-%";
        $stmt = $pdo->prepare("SELECT numeroFacture FROM factures WHERE numeroFacture LIKE ? ORDER BY numeroFacture DESC LIMIT 1");
        $stmt->execute([$pattern]);
        $last = $stmt->fetchColumn();
        
        $nextNum = 1;
        if ($last) {
            $parts = explode('-', $last);
            $nextNum = intval(end($parts)) + 1;
        }
        respond(["number" => $type . "-" . $year . "-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT)]);
        break;

        case 'factures':
        if ($method === 'GET') {
            if ($id) {
                // Détails d'une facture spécifique
                $stmt = $pdo->prepare("SELECT f.*, c.nom as client_nom FROM factures f LEFT JOIN clients c ON f.client_id = c.id WHERE f.numeroFacture = ?");
                $stmt->execute([$id]);
                $facture = $stmt->fetch();
                if (!$facture) respond(["error" => "Non trouvé"], 404);
                
                $stmtLignes = $pdo->prepare("SELECT * FROM facture_lignes WHERE facture_id = ?");
                $stmtLignes->execute([$id]);
                respond(["factureInfo" => $facture, "lignes" => $stmtLignes->fetchAll()]);
            } else {
                // Liste des factures
                $sql = "SELECT f.*, c.nom as client_nom FROM factures f LEFT JOIN clients c ON f.client_id = c.id";
                respond($pdo->query($sql)->fetchAll());
            }
        } elseif ($method === 'POST') {
            $info = $input['factureInfo'];
            $lignes = $input['lignes'];
            $totaux = $input['totaux'];
            $deboursIds = $input['debours_ids'] ?? [];
            
            $pdo->beginTransaction();
            try {
                $sqlFact = "INSERT INTO factures (numeroFacture, date, dossier_id, client_id, statut, sousTotal, montantTva, totalTtc) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE date=VALUES(date), dossier_id=VALUES(dossier_id), client_id=VALUES(client_id), statut=VALUES(statut), sousTotal=VALUES(sousTotal), montantTva=VALUES(montantTva), totalTtc=VALUES(totalTtc)";
                
                $dossier_id = empty($info['dossierLie']) ? null : $info['dossierLie'];
                
                $pdo->prepare($sqlFact)->execute([
                    $info['numeroFacture'], $info['date'], $dossier_id, $info['client_id'], 
                    $info['statut'], $totaux['sousTotal'], $totaux['montantTva'], $totaux['totalTtc']
                ]);
                
                // Suppression et réinsertion des lignes
                $pdo->prepare("DELETE FROM facture_lignes WHERE facture_id = ?")->execute([$info['numeroFacture']]);
                
                $sqlLigne = "INSERT INTO facture_lignes (facture_id, description, quantite, prixUnitaire, taxable) VALUES (?, ?, ?, ?, ?)";
                $stmtL = $pdo->prepare($sqlLigne);
                foreach ($lignes as $l) {
                    $stmtL->execute([$info['numeroFacture'], $l['description'], $l['quantite'], $l['prixUnitaire'], $l['taxable'] ? 1 : 0]);
                }
                
                // --- NOUVEAU: Automatisation du statut du dossier ---
                if ($info['statut'] === 'Validée' && !empty($dossier_id)) {
                    $pdo->prepare("UPDATE dossiers SET statutFacturation = 'Facturé' WHERE id = ?")
                        ->execute([$dossier_id]);
                }

                // --- NOUVEAU: Liaison des débours importés ---
                if (!empty($deboursIds)) {
                    $placeholders = implode(',', array_fill(0, count($deboursIds), '?'));
                    $sqlDb = "UPDATE debours SET statut = 'Facturé', facture_id = ? WHERE id IN ($placeholders)";
                    $params = array_merge([$info['numeroFacture']], $deboursIds);
                    $pdo->prepare($sqlDb)->execute($params);
                }
                // --- FIN NOUVEAU ---

                $pdo->commit();
                respond(["numeroFacture" => $info['numeroFacture'], "message" => "Succès"]);
            } catch (Exception $e) {
                $pdo->rollBack();
                respond(["error" => $e->getMessage()], 500);
            }
        } elseif ($method === 'DELETE' && $id) {
            // Réinitialiser les débours liés à cette facture avant de la supprimer
            $pdo->prepare("UPDATE debours SET statut = 'En attente', facture_id = NULL WHERE facture_id = ?")->execute([$id]);
            
            $pdo->prepare("DELETE FROM factures WHERE numeroFacture = ?")->execute([$id]);
            respond(["message" => "Facture supprimée"]);
        }
        break;

        case 'factures-pdf':
            if ($method === 'GET' && $id) {
                $stmt = $pdo->prepare("SELECT f.*, c.nom as client_nom, c.email as client_email, c.adresse as client_adresse, c.ville as client_ville, d.numBL, d.navire, d.numVoyage, d.origine, d.destination, d.poids, d.volume, d.nombresColis FROM factures f LEFT JOIN clients c ON f.client_id = c.id LEFT JOIN dossiers d ON f.dossier_id = d.id WHERE f.numeroFacture = ?");
                $stmt->execute([$id]);
                $facture = $stmt->fetch();
                if (!$facture) respond(["error" => "Facture non trouvée"], 404);
                
                $stmtLignes = $pdo->prepare("SELECT * FROM facture_lignes WHERE facture_id = ?");
                $stmtLignes->execute([$id]);
                $lignes = $stmtLignes->fetchAll();
                

                $stmtDebours = $pdo->prepare("SELECT * FROM debours WHERE facture_id = ?");
                $stmtDebours->execute([$id]);
                $deboursList = $stmtDebours->fetchAll();

                $isProforma = strpos($id, 'PRO') !== false;
                $title = $isProforma ? 'PROFORMA' : 'FACTURE';

                $logoPath = __DIR__ . '/assets/ship.svg';
                $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';

                $html = "
                <html>
                <head>
                    <style>
                        body { font-family: 'Helvetica', sans-serif; color: #333; font-size: 11px; line-height: 1.4; }
                        .container { padding: 20px; }
                        .header-table { width: 100%; margin-bottom: 40px; }
                        .logo-box { width: 50px; height: 50px; background: #2563eb; border-radius: 10px; text-align: center; padding: 10px; }
                        .company-name { font-size: 22px; font-weight: bold; color: #333; margin: 0; }
                        .company-tag { color: #666; font-weight: bold; text-transform: uppercase; font-size: 10px; margin: 2px 0; }
                        .company-details { color: #666; font-size: 9px; }
                        
                        .title-badge { border: 2px solid " . ($isProforma ? '#666' : '#2563eb') . "; padding: 5px 15px; font-size: 18px; font-weight: bold; display: inline-block; }
                        .info-label { color: #666; text-transform: uppercase; font-size: 8px; margin-bottom: 2px; }
                        .info-value { background: #f9f9f9; border: 1px solid #eee; padding: 5px; font-weight: bold; text-align: right; width: 120px; }

                        .context-table { width: 100%; border: 1px solid #eee; border-radius: 8px; margin-bottom: 30px; border-spacing: 0; }
                        .context-td { padding: 15px; vertical-align: top; }
                        .section-title { font-size: 10px; color: #666; text-transform: uppercase; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px; }
                        .client-name { font-size: 14px; font-weight: bold; display: block; }
                        
                        .dossier-grid { width: 100%; }
                        .dossier-label { color: #666; font-size: 8px; }
                        .dossier-value { font-weight: bold; font-size: 10px; }

                        .lines-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                        .lines-table th { border-bottom: 2px solid #eee; padding: 10px; text-align: left; color: #666; font-size: 9px; text-transform: uppercase; }
                        .lines-table td { padding: 10px; border-bottom: 1px solid #eee; }
                        
                        .summary-table { width: 100%; }
                        .payment-box { border: 1px solid #eee; border-radius: 8px; padding: 15px; color: #666; font-size: 9px; }
                        .totals-box { border: 2px solid #eee; border-radius: 8px; padding: 15px; }
                        .total-row { font-size: 12px; margin-bottom: 5px; }
                        .grand-total { border-top: 2px solid #eee; padding-top: 10px; margin-top: 10px; font-size: 16px; font-weight: bold; }
                        .clear { clear: both; }
                    </style>
                </head>
                <body>
                <div class='container'>
                    <table class='header-table'>
                        <tr>
                            <td>
                                <table cellspacing='0' cellpadding='0'>
                                    <tr>
                                        <td style='padding-right: 15px;'>
                                            <div class='logo-box'>
                                                <img src='data:image/svg+xml;base64,$logoBase64' width='30' height='30' />
                                            </div>
                                        </td>
                                        <td>
                                            <h1 class='company-name'>F2N LOGISTICS</h1>
                                            <p class='company-tag'>Commissionnaire Agréé</p>
                                            <div class='company-details'>
                                                Zone Franche Industrielle, Douala, Cameroun<br>
                                                Tél: +237 6 99 97 98 85 • NINEA/NIU: 000111222333
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                            <td align='right' valign='top'>
                                <div class='title-badge'>$title</div>
                                <table style='margin-top: 10px;' align='right'>
                                    <tr>
                                        <td align='right' style='padding-right: 10px;'>
                                            <div class='info-label'>Numéro</div>
                                            <div class='info-value'>{$facture['numeroFacture']}</div>
                                        </td>
                                        <td align='right'>
                                            <div class='info-label'>Date d'émission</div>
                                            <div class='info-value'>{$facture['date']}</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <table class='context-table'>
                        <tr>
                            <td class='context-td' width='35%' style='border-right: 1px solid #eee;'>
                                <div class='section-title'>Facturé à</div>
                                <span class='client-name'>{$facture['client_nom']}</span>
                                <div class='company-details' style='margin-top: 5px;'>
                                    NINEA / NIU: À renseigner<br>
                                    " . ($facture['client_adresse'] ?: '') . "<br>
                                    " . ($facture['client_ville'] ?: '') . "
                                </div>
                            </td>
                            <td class='context-td'>
                                <div class='section-title'>Détails de l'Expédition</div>
                                <table class='dossier-grid'>
                                    <tr>
                                        <td width='50%'>
                                            <div class='dossier-label'>B/L / LTA</div>
                                            <div class='dossier-value'>{$facture['numBL']}</div>
                                        </td>
                                        <td>
                                            <div class='dossier-label'>Navire / Voyage</div>
                                            <div class='dossier-value'>{$facture['navire']} " . ($facture['numVoyage'] ? "/ V.{$facture['numVoyage']}" : '') . "</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding-top: 10px;'>
                                            <div class='dossier-label'>Routage</div>
                                            <div class='dossier-value'>{$facture['origine']} &rarr; {$facture['destination']}</div>
                                        </td>
                                        <td style='padding-top: 10px;'>
                                            <div class='dossier-label'>Poids / Volume / Colis</div>
                                            <div class='dossier-value'>{$facture['poids']} kg / {$facture['volume']} CBM / {$facture['nombresColis']}</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <table class='lines-table'>
                        <thead>
                            <tr>
                                <th>Désignation des Frais</th>
                                <th align='center' width='50'>Qté</th>
                                <th align='right' width='100'>P.U</th>
                                <th align='right' width='120'>Montant Total</th>
                            </tr>
                        </thead>
                        <tbody>";
                foreach($lignes as $l) {
                    $rowTotal = $l['quantite'] * $l['prixUnitaire'];
                    $html .= "<tr>
                        <td>{$l['description']}</td>
                        <td align='center'>{$l['quantite']}</td>
                        <td align='right'>" . number_format($l['prixUnitaire'], 0, ',', ' ') . "</td>
                        <td align='right'><strong>" . number_format($rowTotal, 0, ',', ' ') . "</strong></td>
                    </tr>";
                }
                foreach($deboursList as $db) {
                    $html .= "<tr>
                        <td><em style='color: #666;'>(Débours)</em> {$db['description']}</td>
                        <td align='center'>1</td>
                        <td align='right'>" . number_format($db['montant'], 0, ',', ' ') . "</td>
                        <td align='right'><strong>" . number_format($db['montant'], 0, ',', ' ') . "</strong></td>
                    </tr>";
                }
                $html .= "</tbody>
                    </table>

                    <table class='summary-table'>
                        <tr>
                            <td width='55%' valign='top'>
                                <div class='payment-box'>
                                    <strong>Conditions de paiement :</strong><br>
                                    Paiement à réception de la facture par chèque ou virement bancaire.<br><br>
                                    <strong>Banque BICIS</strong><br>
                                    IBAN: SN010 01234 000000123456 78<br>
                                    Code SWIFT: BICISNXXXX
                                </div>
                            </td>
                            <td valign='top'>
                                <div class='totals-box'>
                                    <table width='100%'>
                                        <tr>
                                            <td style='color: #666;'>Sous-total HT</td>
                                            <td align='right' style='font-weight: bold;'>" . number_format($facture['sousTotal'], 0, ',', ' ') . " FCFA</td>
                                        </tr>
                                        <tr>
                                            <td style='color: #666; padding-top: 5px;'>TVA (18%)</td>
                                            <td align='right' style='font-weight: bold; padding-top: 5px;'>" . number_format($facture['montantTva'], 0, ',', ' ') . " FCFA</td>
                                        </tr>
                                        <tr class='grand-total'>
                                            <td style='padding-top: 10px;'>TOTAL TTC</td>
                                            <td align='right' style='padding-top: 10px;'>" . number_format($facture['totalTtc'], 0, ',', ' ') . " FCFA</td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                </body>
                </html>";

                $autoloadPath = __DIR__ . '/libs/dompdf/autoload.inc.php';
                if (file_exists($autoloadPath)) {
                    require_once $autoloadPath;
                    
                    $options = new \Dompdf\Options();
                    $options->set('isRemoteEnabled', true);
                    
                    $dompdf = new \Dompdf\Dompdf($options);
                    $dompdf->loadHtml($html, 'UTF-8');
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $dompdf->stream("{$id}.pdf", ["Attachment" => true]);
                    exit;
                } else {
                    echo $html; // Fallback pour tester le design si Dompdf n'est pas encore là
                    exit;
                }
            }
            break;

        case 'factures-email':
            if ($method === 'POST' && $id) {
                $stmt = $pdo->prepare("SELECT f.*, c.nom as client_nom, c.email as client_email FROM factures f LEFT JOIN clients c ON f.client_id = c.id WHERE f.numeroFacture = ?");
                $stmt->execute([$id]);
                $facture = $stmt->fetch();

                if (empty($facture['client_email'])) {
                    respond(["error" => "Le client n'a pas d'adresse email configurée."], 400);
                }

                if (defined('SMTP_HOST') && file_exists('libs/PHPMailer/src/PHPMailer.php')) {
                    require 'libs/PHPMailer/src/Exception.php';
                    require 'libs/PHPMailer/src/PHPMailer.php';
                    require 'libs/PHPMailer/src/SMTP.php';

                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = SMTP_HOST;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = SMTP_USER;
                        $mail->Password   = SMTP_PASS;
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = SMTP_PORT;
                        $mail->setFrom(SMTP_FROM, 'F2N Logistics');
                        $mail->addAddress($facture['client_email'], $facture['client_nom']);
                        $mail->isHTML(true);
                        $mail->Subject = "Document " . $id . " - F2N Logistics";
                        $mail->Body    = "Bonjour " . $facture['client_nom'] . ",<br><br>Veuillez trouver ci-joint votre document " . $id . " d'un montant de " . number_format($facture['totalTtc'], 0, ',', ' ') . " FCFA.<br><br>Cordialement.";
                        $mail->send();
                        respond(["message" => "Email envoyé avec succès à {$facture['client_email']}"]);
                    } catch (Exception $e) {
                        respond(["error" => "Erreur SMTP : " . $mail->ErrorInfo], 500);
                    }
                } else {
                    $to = $facture['client_email'];
                    $subject = "Document " . $id;
                    $message = "Bonjour, voici votre document de " . number_format($facture['totalTtc'], 0, ',', ' ') . " FCFA.";
                    $headers = "From: " . (defined('SMTP_FROM') ? SMTP_FROM : "gestion@f2nlogistics.sn");
                    if(mail($to, $subject, $message, $headers)) {
                        respond(["message" => "Email envoyé (mail standard)"]);
                    } else {
                        respond(["error" => "Échec de l'envoi. Configurez SMTP dans config.php."], 500);
                    }
                }
            }
            break;

        default:
            respond(["error" => "Route non trouvée : " . $resource], 404);
            break;
    }
} catch (Throwable $e) {
    respond([
        "error" => "Erreur critique : " . $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ], 500);
}

?>