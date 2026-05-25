<?php
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
                
                $sql = "INSERT INTO dossiers (id, typeOperation, modeTransport, numBL, incoterm, compagnie, navire, numVoyage, etd, eta, origine, destination, client_id, expediteur, natureMarchandise, nombresColis, typeConteneur, poids, volume, valeurMarchandise, dateCreation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $pdo->prepare($sql)->execute([
                    $newId, $typeOp, $input['modeTransport'] ?? null, $input['numBL'] ?? null, $input['incoterm'] ?? null,
                    $input['compagnie'] ?? null, $input['navire'] ?? null, $input['numVoyage'] ?? null, $input['etd'] ?? null, $input['eta'] ?? null,
                    $input['origine'] ?? null, $input['destination'] ?? null, $input['client_id'] ?? null, $input['expediteur'] ?? null,
                    $input['natureMarchandise'] ?? null, $input['nombresColis'] ?? null, $input['typeConteneur'] ?? null,
                    $input['poids'] ?? null, $input['volume'] ?? null, $input['valeurMarchandise'] ?? null,
                    $input['dateCreation'] ?? date("Y-m-d")
                ]);
                respond(["id" => $newId, "message" => "Dossier créé"]);
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
                
                $pdo->commit();
                respond(["numeroFacture" => $info['numeroFacture'], "message" => "Succès"]);
            } catch (Exception $e) {
                $pdo->rollBack();
                respond(["error" => $e->getMessage()], 500);
            }
        } elseif ($method === 'DELETE' && $id) {
            $pdo->prepare("DELETE FROM factures WHERE numeroFacture = ?")->execute([$id]);
            respond(["message" => "Facture supprimée"]);
        }
        break;

        default:
            respond(["error" => "Route non trouvée : " . $resource], 404);
            break;
    }
} catch (Exception $e) {
    respond(["error" => $e->getMessage()], 500);
}

?>