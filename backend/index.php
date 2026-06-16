<?php
// Désactiver l'affichage des erreurs pour éviter de corrompre le JSON en sortie
error_reporting(0);
ini_set('display_errors', 0);

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
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'employee',
        status VARCHAR(20) DEFAULT 'pending'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Définir le super administrateur
    if (!defined('SUPER_ADMIN_USERNAME')) {
        define('SUPER_ADMIN_USERNAME', 'enzo');
    }

    // Mise à jour sécurisée de la structure de la table (compatibilité MySQL standard)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'employee'");
    } catch (Exception $e) {
        // La colonne existe déjà, on ignore l'erreur
    }
    
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'pending'");
    } catch (Exception $e) {
        // La colonne existe déjà, on ignore l'erreur
    }
    
    try {
        $pdo->exec("ALTER TABLE factures ADD COLUMN numDeclaration VARCHAR(255)");
        $pdo->exec("ALTER TABLE factures ADD COLUMN adresseFacturation TEXT");
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE facture_lignes ADD COLUMN type VARCHAR(20) DEFAULT 'prestation'");
    } catch (Exception $e) {}
    
    // Mise à jour automatique de la raison sociale
    define('COMPANY_NAME', 'F2N LOGISTICS SARL');

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
        numDeclaration VARCHAR(255),
        adresseFacturation TEXT,
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
        type VARCHAR(20) DEFAULT 'prestation',
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

function numberToFrenchWords($number) {
    $hyphen      = '-';
    $conjunction = ' et ';
    $separator   = ', ';
    $negative    = 'moins ';
    $decimal     = ' virgule ';
    $dictionary  = array(
        0                   => 'zéro',
        1                   => 'un',
        2                   => 'deux',
        3                   => 'trois',
        4                   => 'quatre',
        5                   => 'cinq',
        6                   => 'six',
        7                   => 'sept',
        8                   => 'huit',
        9                   => 'neuf',
        10                  => 'dix',
        11                  => 'onze',
        12                  => 'douze',
        13                  => 'treize',
        14                  => 'quatorze',
        15                  => 'quinze',
        16                  => 'seize',
        17                  => 'dix-sept',
        18                  => 'dix-huit',
        19                  => 'dix-neuf',
        20                  => 'vingt',
        30                  => 'trente',
        40                  => 'quarante',
        50                  => 'cinquante',
        60                  => 'soixante',
        70                  => 'soixante-dix',
        71                  => 'soixante-et-un',
        72                  => 'soixante-douze',
        73                  => 'soixante-treize',
        74                  => 'soixante-quatorze',
        75                  => 'soixante-quinze',
        76                  => 'soixante-seize',
        77                  => 'soixante-dix-sept',
        78                  => 'soixante-dix-huit',
        79                  => 'soixante-dix-neuf',
        80                  => 'quatre-vingt',
        81                  => 'quatre-vingt-un',
        90                  => 'quatre-vingt-dix',
        91                  => 'quatre-vingt-onze',
        99                  => 'quatre-vingt-dix-neuf',
        100                 => 'cent',
        1000                => 'mille',
        1000000             => 'million',
        1000000000          => 'milliard'
    );

    if (!is_numeric($number)) {
        return false;
    }

    if ($number < 0) {
        return $negative . numberToFrenchWords(abs($number));
    }

    $string = null;

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 70:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                if ($units == 1) {
                    $string .= $conjunction . $dictionary[$units];
                } else {
                    $string .= $hyphen . $dictionary[$units];
                }
            }
            break;
        case $number < 80:
            $units  = $number % 10;
            $string = 'soixante';
            if ($units == 1) {
                $string .= ' et onze';
            } else {
                $string .= $hyphen . $dictionary[10 + $units];
            }
            break;
        case $number < 90:
            $units  = $number % 10;
            $string = 'quatre-vingt';
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            } else {
                $string .= 's'; // quatre-vingts
            }
            break;
        case $number < 100:
            $units  = $number % 10;
            $string = 'quatre-vingt';
            $string .= $hyphen . $dictionary[10 + $units];
            break;
        case $number < 100:
            $units  = $number % 10;
            $string = 'quatre-vingt';
            $string .= $hyphen . $dictionary[10 + $units];
            break;
        case $number < 1000:
            $hundreds  = (int) ($number / 100);
            $remainder = $number % 100;
            if ($hundreds == 1) {
                $string = 'cent';
            } else {
                $string = $dictionary[$hundreds] . ' cent';
                if (!$remainder) {
                    $string .= 's';
                }
            }
            if ($remainder) {
                $string .= ' ' . numberToFrenchWords($remainder);
            }
            break;
        case $number < 1000000:
            $thousands = (int) ($number / 1000);
            $remainder = $number % 1000;
            if ($thousands == 1) {
                $string = 'mille';
            } else {
                $string = numberToFrenchWords($thousands) . ' mille';
            }
            if ($remainder) {
                $string .= ' ' . numberToFrenchWords($remainder);
            }
            break;
        case $number < 1000000000:
            $millions  = (int) ($number / 1000000);
            $remainder = $number % 1000000;
            $string    = numberToFrenchWords($millions) . ' million';
            if ($millions > 1) {
                $string .= 's';
            }
            if ($remainder) {
                $string .= ' ' . numberToFrenchWords($remainder);
            }
            break;
        default:
            $string = 'nombre trop grand';
            break;
    }

    return $string;
}

try {
    switch ($resource) {
        case 'register':
            if ($method === 'POST') {
                // Le premier utilisateur sera admin approved, les autres employees pending
                $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $role = ($count == 0) ? 'admin' : 'employee';
                $status = ($count == 0) ? 'approved' : 'pending';

                $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute([$input['username'], $input['email'], $hashedPassword, $role, $status]);
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
                    // Comparaison insensible à la casse pour le super-admin
                    if (strtolower($user['username']) === strtolower(SUPER_ADMIN_USERNAME)) {
                        $user['role'] = 'admin';
                        $user['status'] = 'approved';
                    }
                    // Vérifier si le compte est approuvé
                    if ($user['status'] !== 'approved') {
                        respond(["error" => "Votre compte est en attente d'approbation par l'administrateur."], 403);
                    }

                    // En production, vous utiliseriez un JWT ici. 
                    // Pour la simplicité sur InfinityFree, on renvoie les infos de base.
                    respond([
                        "id" => $user['id'],
                        "username" => $user['username'],
                        "email" => $user['email'],
                        "role" => $user['role'],
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

        case 'users':
            if ($method === 'GET') {
                $stmt = $pdo->query("SELECT id, username, email, role, status FROM users ORDER BY username");
                respond($stmt->fetchAll());
            } elseif ($method === 'PUT' && $id) {
                // Récupérer l'ID du super administrateur pour le protéger
                $superAdminStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $superAdminStmt->execute([SUPER_ADMIN_USERNAME]);
                $superAdminId = $superAdminStmt->fetchColumn();

                if ($id == $superAdminId) {
                    respond(["error" => "Impossible de modifier le compte super administrateur."], 403);
                }
                // Mise à jour du statut ou du rôle (Admin uniquement)
                $sql = "UPDATE users SET status = ?, role = ? WHERE id = ?";
                $pdo->prepare($sql)->execute([
                    $input['status'] ?? 'pending',
                    $input['role'] ?? 'employee',
                    $id
                ]);
                respond(["message" => "Utilisateur mis à jour"]);
            } elseif ($method === 'DELETE' && $id) {
                $superAdminStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $superAdminStmt->execute([SUPER_ADMIN_USERNAME]);
                $superAdminId = $superAdminStmt->fetchColumn();

                if ($id == $superAdminId) {
                    respond(["error" => "Impossible de supprimer le compte super administrateur."], 403);
                }
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                respond(["message" => "Utilisateur supprimé"]);
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
                $sql = "SELECT d.*, c.nom as client_nom, c.nif as client_nif, c.rccm as client_rccm, c.adresse as client_adresse, c.ville as client_ville, c.tel as client_tel FROM dossiers d LEFT JOIN clients c ON d.client_id = c.id";
                respond($pdo->query($sql)->fetchAll());
            } elseif ($method === 'POST') {
                $typeOp = $input['typeOperation'] ?? 'Import';
                $prefix = "26F2N";
                $suffix = "D";
                $pattern = "$prefix-%-$suffix";

                // Extraire le numéro maximum réel (tri numérique, pas alphabétique)
                $stmt = $pdo->prepare("SELECT id FROM dossiers WHERE id LIKE ?");
                $stmt->execute([$pattern]);
                $allIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $maxNum = 0;
                foreach ($allIds as $existingId) {
                    // Extraire la partie numérique : 26F2N-003D → 3
                    if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)' . preg_quote($suffix, '/') . '$/', $existingId, $m)) {
                        $maxNum = max($maxNum, intval($m[1]));
                    }
                }
                $nextNum = $maxNum + 1;

                $newId = $input['id'] ?? ($prefix . "-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT) . $suffix);
                
                // Sécurité supplémentaire : si l'ID existe déjà, incrémenter jusqu'à trouver un libre
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE id = ?");
                while (true) {
                    $checkStmt->execute([$newId]);
                    if ($checkStmt->fetchColumn() == 0) break;
                    $nextNum++;
                    $newId = $prefix . "-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT) . $suffix;
                }
                
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
        $prefix = "26F2N";
        $suffix = "F";
        $pattern = "$prefix-%-$suffix";

        // Tri numérique réel : récupère tous les numéros et extrait le MAX
        $stmt = $pdo->prepare("SELECT numeroFacture FROM factures WHERE numeroFacture LIKE ?");
        $stmt->execute([$pattern]);
        $allNums = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $maxNum = 0;
        foreach ($allNums as $num) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)' . preg_quote($suffix, '/') . '$/', $num, $m)) {
                $maxNum = max($maxNum, intval($m[1]));
            }
        }
        $nextNum = $maxNum + 1;
        $nextNumber = $prefix . "-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT) . $suffix;

        // Sécurité : boucle jusqu'à trouver un numéro vraiment libre
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE numeroFacture = ?");
        while (true) {
            $checkStmt->execute([$nextNumber]);
            if ($checkStmt->fetchColumn() == 0) break;
            $nextNum++;
            $nextNumber = $prefix . "-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT) . $suffix;
        }

        respond(["number" => $nextNumber]);
        break;

        case 'factures':
        if ($method === 'GET') {
            if ($id) {
                // Détails d'une facture spécifique
                $stmt = $pdo->prepare("SELECT f.*, c.nom as client_nom, c.nif as client_nif, c.rccm as client_rccm, c.adresse as client_adresse, c.ville as client_ville, c.tel as client_tel FROM factures f LEFT JOIN clients c ON f.client_id = c.id WHERE f.numeroFacture = ?");
                $stmt->execute([$id]);
                $facture = $stmt->fetch();
                if (!$facture) respond(["error" => "Non trouvé"], 404);
                
                $stmtLignes = $pdo->prepare("SELECT * FROM facture_lignes WHERE facture_id = ?");
                $stmtLignes->execute([$id]);
                respond(["factureInfo" => $facture, "lignes" => $stmtLignes->fetchAll()]);
            } else {
                // Liste des factures
                $sql = "SELECT f.*, c.nom as client_nom, c.nif as client_nif, c.rccm as client_rccm FROM factures f LEFT JOIN clients c ON f.client_id = c.id";
                respond($pdo->query($sql)->fetchAll());
            }
        } elseif ($method === 'POST') {
            $info = $input['factureInfo'];
            $lignes = $input['lignes'];
            $totaux = $input['totaux'];
            $deboursIds = $input['debours_ids'] ?? [];
            
            $pdo->beginTransaction();
            try {
                // Sécurité : ne pas écraser une facture déjà Validée avec une Proforma
                $checkExisting = $pdo->prepare("SELECT statut FROM factures WHERE numeroFacture = ?");
                $checkExisting->execute([$info['numeroFacture']]);
                $existingFacture = $checkExisting->fetch();
                if ($existingFacture && $existingFacture['statut'] === 'Validée' && $info['statut'] === 'Proforma') {
                    throw new Exception("Impossible de modifier une facture déjà validée en Proforma. Le numéro " . $info['numeroFacture'] . " est déjà utilisé.");
                }

                $sqlFact = "INSERT INTO factures (numeroFacture, date, dossier_id, client_id, statut, sousTotal, montantTva, totalTtc, numDeclaration, adresseFacturation) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE date=VALUES(date), dossier_id=VALUES(dossier_id), client_id=VALUES(client_id), statut=VALUES(statut), sousTotal=VALUES(sousTotal), montantTva=VALUES(montantTva), totalTtc=VALUES(totalTtc), numDeclaration=VALUES(numDeclaration), adresseFacturation=VALUES(adresseFacturation)";
                
                $dossier_id = empty($info['dossierLie']) ? null : $info['dossierLie'];
                
                $pdo->prepare($sqlFact)->execute([
                    $info['numeroFacture'], $info['date'], $dossier_id, $info['client_id'], 
                    $info['statut'], $totaux['sousTotal'], $totaux['montantTva'], $totaux['totalTtc'],
                    $info['numDeclaration'] ?? null, $info['adresseFacturation'] ?? null
                ]);
                
                // Suppression et réinsertion des lignes
                $pdo->prepare("DELETE FROM facture_lignes WHERE facture_id = ?")->execute([$info['numeroFacture']]);
                
                $sqlLigne = "INSERT INTO facture_lignes (facture_id, description, quantite, prixUnitaire, taxable, type) VALUES (?, ?, ?, ?, ?, ?)";
                $stmtL = $pdo->prepare($sqlLigne);
                foreach ($lignes as $l) {
                    $stmtL->execute([$info['numeroFacture'], $l['description'], $l['quantite'], $l['prixUnitaire'], $l['taxable'] ? 1 : 0, $l['type'] ?? 'prestation']);
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
                $stmt = $pdo->prepare("SELECT f.*, c.nom as client_nom, c.email as client_email, c.adresse as client_adresse, c.ville as client_ville, c.nif as client_nif, c.rccm as client_rccm, c.tel as client_tel, d.numBL, d.navire, d.numVoyage, d.origine, d.destination, d.poids, d.volume, d.nombresColis FROM factures f LEFT JOIN clients c ON f.client_id = c.id LEFT JOIN dossiers d ON f.dossier_id = d.id WHERE f.numeroFacture = ?");
                $stmt->execute([$id]);
                $facture = $stmt->fetch();
                if (!$facture) respond(["error" => "Facture non trouvée"], 404);
                
                $stmtLignes = $pdo->prepare("SELECT * FROM facture_lignes WHERE facture_id = ?");
                $stmtLignes->execute([$id]);
                $lignes = $stmtLignes->fetchAll();
                
                $isProforma = $facture['statut'] === 'Proforma';
                $title = $isProforma ? 'PROFORMA' : 'FACTURE';

                // Recherche du logo personnel (png, jpg ou svg)
                $logoPath = __DIR__ . '/assets/logo.png';
                if (!file_exists($logoPath)) $logoPath = __DIR__ . '/assets/logo.jpg';
                if (!file_exists($logoPath)) $logoPath = __DIR__ . '/assets/ship.svg';

                $logoData = file_get_contents($logoPath);
                $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
                $mime = ($ext === 'svg') ? 'image/svg+xml' : 'image/' . $ext;
                $logoBase64 = base64_encode($logoData);
                $logoSrc = "data:$mime;base64,$logoBase64";

                $html = "
                <html>
                <head>
                    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
                    <style>
                        @page { margin: 1cm 1.5cm 3cm 1.5cm; }
                        body { font-family: 'DejaVu Sans', sans-serif; color: #333; font-size: 10px; line-height: 1.4; }
                        .container { width: 100%; }
                        .header-table { width: 100%; margin-bottom: 40px; }
                        .logo-box { width: 120px; height: 120px; text-align: left; }
                        .company-name { font-size: 22px; font-weight: bold; color: #333; margin: 0; }
                        .company-tag { color: #666; font-weight: bold; text-transform: uppercase; font-size: 9px; margin: 2px 0; }
                        .company-details { color: #666; font-size: 9px; }
                        
                        .title-badge { border: 2px solid " . ($isProforma ? '#666' : '#2563eb') . "; padding: 5px 15px; font-size: 18px; font-weight: bold; display: inline-block; }
                        .info-label { color: #000; text-transform: uppercase; font-size: 8px; margin-bottom: 2px; }
                        .info-value { background: #f9f9f9; border: 1px solid #000; padding: 5px; font-weight: bold; text-align: right; width: 120px; }

                        .context-table { width: 100%; border: 1px solid #000; border-radius: 8px; margin-bottom: 30px; border-spacing: 0; }
                        .context-td { padding: 15px; vertical-align: top; }
                        .section-title { font-size: 10px; color: #000; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 10px; }
                        .client-name { font-size: 14px; font-weight: bold; display: block; }
                        
                        .dossier-grid { width: 100%; }
                        .dossier-label { color: #666; font-size: 8px; }
                        .dossier-value { font-weight: normal; font-size: 10px; }

                        .lines-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                        .lines-table th { border-bottom: 2px solid #000; padding: 10px; text-align: left; color: #000; font-size: 9px; text-transform: uppercase; }
                        .lines-table td { padding: 8px 10px; border-bottom: 1px solid #ccc; color: #333; font-size: 9px; }
                        .group-header { background: #f0f0f0; font-weight: bold; text-transform: uppercase; padding: 5px 10px !important; border-bottom: 2px solid #333 !important; }
                        
                        .summary-table { width: 100%; }
                        .payment-box { border: 1px solid #000; border-radius: 8px; padding: 15px; color: #000; font-size: 9px; }
                        .totals-box { border: 2px solid #000; border-radius: 8px; padding: 15px; }
                        .total-row { font-size: 12px; margin-bottom: 5px; }
                        .grand-total { border-top: 2px solid #000; padding-top: 10px; margin-top: 10px; font-size: 16px; font-weight: bold; }
                        .footer { position: fixed; bottom: -2cm; left: 0; right: 0; text-align: center; font-size: 8px; color: #777; border-top: 1px solid #eee; padding-top: 10px; height: 1.5cm; }
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
                                                <img src='$logoSrc' style='max-width: 80px; max-height: 80px;' />
                                            </div>
                                        </td>
                                        <td>
                                            <h1 class='company-name'>F2N LOGISTICS SARL</h1>
                                            <p class='company-tag'>Commissionnaire en Douane Agréé</p>
                                            <div class='company-details'>
                                                BP 4056 Douala - Bonapriso - CAMEROUN<br>
                                                Tél: +237 699 97 98 85 • NIU: M042517669133Q • RCCM : CM-DLA-01-2025-B12-000508
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
                                <div class='company-details' style='margin-top: 5px;'>";
                $html .= ($facture['client_nif'] ? "NIU: {$facture['client_nif']}<br>" : "");
                $html .= ($facture['client_tel'] ? "Tél: {$facture['client_tel']}<br>" : "");
                $html .= ($facture['adresseFacturation'] ?: ($facture['client_adresse'] ?: '')) . "<br>
                                    " . ($facture['client_ville'] ?: '') . "
                                </div>
                            </td>
                            <td class='context-td'>
                                <div class='section-title'>Détails de l'Expédition</div>
                                <table class='dossier-grid'>
                                    <tr>
                                        <td width='50%'>
                                            <div class='dossier-label'>N° Déclaration / BL</div>
                                            <div class='dossier-value'>" . ($facture['numDeclaration'] ? "DEC: " . $facture['numDeclaration'] : "BL: " . $facture['numBL']) . "</div>
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
                                <th align='center' width='45'>Qté</th>
                                <th align='right' width='90'>Débours</th>
                                <th align='right' width='95'>Prestations</th>
                                <th align='right' width='95'>TVA (19,25%)</th>
                                <th align='right' width='105'>Montant</th>
                            </tr>
                        </thead>
                        <tbody>";
                
                $debours = array_filter($lignes, function($l) { return $l['type'] === 'debour'; });
                $prestations = array_filter($lignes, function($l) { return $l['type'] === 'prestation'; });

                // Calcul des totaux par groupe pour le résumé
                $totalDebours = array_reduce($debours, function($carry, $item) { return $carry + ($item['quantite'] * $item['prixUnitaire']); }, 0);
                $totalDeboursPU = array_reduce($debours, function($carry, $item) { return $carry + $item['prixUnitaire']; }, 0);

                $commissionDebours = $totalDebours * 0.02;

                $totalPrestationsExclComm = array_reduce($prestations, function($carry, $item) { return $carry + ($item['quantite'] * $item['prixUnitaire']); }, 0);
                $totalPrestationsPUExclComm = array_reduce($prestations, function($carry, $item) { return $carry + $item['prixUnitaire']; }, 0);

                $totalPrestations = $totalPrestationsExclComm + $commissionDebours;
                $totalPrestationsPU = $totalPrestationsPUExclComm + $commissionDebours;

                $totalTvaPrestations = 0;

                if (!empty($debours)) {
                    $html .= "<tr><td colspan='6' class='group-header'>Débours (Frais Tiers)</td></tr>";
                    foreach($debours as $l) {
                        $rowTotal = $l['quantite'] * $l['prixUnitaire'];
                        $html .= "<tr>
                            <td>{$l['description']}</td>
                            <td align='center'>{$l['quantite']}</td>
                            <td align='right'>" . number_format($l['prixUnitaire'], 0, ',', ' ') . "</td>
                            <td align='right'>-</td>
                            <td align='right'>-</td>
                            <td align='right'><strong>" . number_format($rowTotal, 0, ',', ' ') . "</strong></td>
                        </tr>";
                    }
                    $html .= "<tr style='background-color: #f9f9f9; font-weight: bold;'>
                        <td>SOUS/TOTAL 1</td>
                        <td align='center'>-</td>
                        <td align='right'>" . number_format($totalDeboursPU, 0, ',', ' ') . "</td>
                        <td align='right'>-</td>
                        <td align='right'>-</td>
                        <td align='right'>" . number_format($totalDebours, 0, ',', ' ') . "</td>
                    </tr>";
                }

                if (!empty($prestations) || $commissionDebours > 0) {
                    $html .= "<tr><td colspan='6' class='group-header'>Prestations de Service</td></tr>";
                    
                    if ($commissionDebours > 0) {
                        $html .= "<tr>
                            <td>COMMISSIONS SUR DEBOURS</td>
                            <td align='center'>2%</td>
                            <td align='right'>-</td>
                            <td align='right'>" . number_format($commissionDebours, 0, ',', ' ') . "</td>
                            <td align='right'>-</td>
                            <td align='right'><strong>" . number_format($commissionDebours, 0, ',', ' ') . "</strong></td>
                        </tr>";
                    }

                    foreach($prestations as $l) {
                        $rowTotal = $l['quantite'] * $l['prixUnitaire'];
                        $rowTva = $l['taxable'] ? ($rowTotal * 0.1925) : 0;
                        $totalTvaPrestations += $rowTva;
                        
                        $html .= "<tr>
                            <td>{$l['description']}</td>
                            <td align='center'>{$l['quantite']}</td>
                            <td align='right'>-</td>
                            <td align='right'>" . number_format($l['prixUnitaire'], 0, ',', ' ') . "</td>
                            <td align='right'>" . ($rowTva > 0 ? number_format($rowTva, 0, ',', ' ') : "-") . "</td>
                            <td align='right'><strong>" . number_format($rowTotal, 0, ',', ' ') . "</strong></td>
                        </tr>";
                    }

                    $html .= "<tr style='background-color: #f9f9f9; font-weight: bold;'>
                        <td>SOUS/TOTAL 2</td>
                        <td align='center'>-</td>
                        <td align='right'>-</td>
                        <td align='right'>" . number_format($totalPrestationsPU, 0, ',', ' ') . "</td>
                        <td align='right'>" . ($totalTvaPrestations > 0 ? number_format($totalTvaPrestations, 0, ',', ' ') : "-") . "</td>
                        <td align='right'>" . number_format($totalPrestations, 0, ',', ' ') . "</td>
                    </tr>";
                }

                $calculatedTotalTtc = $totalDebours + $totalPrestations + $totalTvaPrestations;

                $html .= "<tr style='background-color: #f9f9f9; font-weight: bold; border-top: 1px solid #000;'>
                    <td>TOTAL GENERAL.</td>
                    <td align='center'>-</td>
                    <td align='right'>" . number_format($totalDebours, 0, ',', ' ') . "</td>
                    <td align='right'>" . number_format($totalPrestations, 0, ',', ' ') . "</td>
                    <td align='right'>" . ($totalTvaPrestations > 0 ? number_format($totalTvaPrestations, 0, ',', ' ') : "-") . "</td>
                    <td align='right'>" . number_format($calculatedTotalTtc, 0, ',', ' ') . "</td>
                </tr>";

                $html .= "<tr style='background-color: #f0f0f0; font-weight: bold; border-top: 2px solid #000;'>
                    <td style='font-size: 11px;'>TOTAL GENERAL A REGLER (FCFA)</td>
                    <td align='center'>-</td>
                    <td align='right'>-</td>
                    <td align='right'>-</td>
                    <td align='right'>" . ($totalTvaPrestations > 0 ? number_format($totalTvaPrestations, 0, ',', ' ') : "-") . "</td>
                    <td align='right' style='font-size: 12px; color: #2563eb;'><strong>" . number_format($calculatedTotalTtc, 0, ',', ' ') . "</strong></td>
                </tr>";

                $html .= "</tbody>
                    </table>

                    <div style='margin-bottom: 25px; font-weight: bold; border: 1px solid #000; padding: 10px; border-radius: 6px; font-size: 9px; line-height: 1.4;'>
                        Arrêté la présente facture à la somme de : <span style='text-transform: uppercase;'>" . numberToFrenchWords(round($calculatedTotalTtc)) . "</span> Francs CFA
                    </div>

                    <table class='summary-table'>
                        <tr>
                            <td width='100%' valign='top'>
                                <div class='payment-box'>
                                    <strong>Conditions de paiement :</strong><br>
                                    Paiement à réception de la facture par chèque à l'ordre de <strong>F2N LOGISTICS SARL</strong> ou par virement bancaire :<br>
                                    <br>
                                    <strong>First Bank :</strong> 10005 00002 10137791001-95
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div class='footer'>
                        F2N LOGISTICS SARL / SOCIETE A RESPONSABILITE LIMITEE au capital de 10 000 000 FCFA - BP 4056 Douala - Bonapriso - CAMEROUN<br>
                        N° RCCM : CM-DLA-01-2025-B12-000508 / NIU : M042517669133Q / N° CNPS : 351-0126148-000H<br>
                        Compte First Bank N° 10005 00002 10137791001-95 - Tél: +237 674 573 495 / +237 679 517 186 / +237 699 97 98 85<br>
                        Email: f2nlogistics@gmail.com / franklin.ngangoua@f2nlogistics.com - www.f2nlogistics.com
                    </div>
                </div>
                </body>
                </html>";

                $autoloadPath = __DIR__ . '/libs/dompdf/autoload.inc.php';
                if (file_exists($autoloadPath)) {
                    require_once $autoloadPath;
                    
                    $options = new \Dompdf\Options();
                    $options->set('isRemoteEnabled', true);
                    $options->set('defaultFont', 'DejaVu Sans');
                    
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
                        $mail->setFrom(SMTP_FROM, 'F2N LOGISTICS SARL');
                        $mail->addAddress($facture['client_email'], $facture['client_nom']);
                        $mail->isHTML(true);
                        $mail->Subject = "Document " . $id . " - F2N LOGISTICS SARL";
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