<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// --- CONFIGURATION BDD (À remplir avec vos infos InfinityFree) ---
$host = 'sql112.infinityfree.com'; // À récupérer sur votre panel
$db_name = 'if0_42018398_logistics_billing'; // À récupérer sur votre panel
$username = 'if0_42018398'; // À récupérer sur votre panel
$password = 'Jennylove237'; // Votre mot de passe de compte

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(["error" => "Erreur de connexion : " . $e->getMessage()]));
}

// --- ROUTAGE SIMPLE ---
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Nettoyage de l'URI pour obtenir la ressource (ex: /api/clients -> clients)
$uri_parts = explode('/', trim($uri, '/'));
// On suppose que le script est dans /api/, donc la ressource est après
$resource = end($uri_parts);
$id = null;

// Gestion des IDs dans l'URL (ex: /api/clients/5)
if (is_numeric($resource) || strpos($resource, '-') !== false) {
    $id = $resource;
    $resource = $uri_parts[count($uri_parts) - 2];
}

$input = json_decode(file_get_contents('php://input'), true);

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

switch ($resource) {
    case 'clients':
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM clients");
            respond($stmt->fetchAll());
        } elseif ($method === 'POST') {
            $sql = "INSERT INTO clients (type, nom, nif, rccm, contact, email, tel, adresse, ville) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([
                $input['type'], $input['nom'], $input['nif'] ?? null, $input['rccm'] ?? null, 
                $input['contact'] ?? null, $input['email'] ?? null, $input['tel'] ?? null, 
                $input['adresse'] ?? null, $input['ville'] ?? null
            ]);
            respond(["id" => $pdo->lastInsertId(), "message" => "Client ajouté"]);
        } elseif ($method === 'PUT' && $id) {
            $sql = "UPDATE clients SET type=?, nom=?, nif=?, rccm=?, contact=?, email=?, tel=?, adresse=?, ville=? WHERE id=?";
            $pdo->prepare($sql)->execute([
                $input['type'], $input['nom'], $input['nif'], $input['rccm'], 
                $input['contact'], $input['email'], $input['tel'], $input['adresse'], $input['ville'], $id
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
            $prefix = ($input['typeOperation'] === 'Import') ? 'IMP' : (($input['typeOperation'] === 'Export') ? 'EXP' : 'TRS');
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
                $newId, $input['typeOperation'], $input['modeTransport'], $input['numBL'], $input['incoterm'],
                $input['compagnie'], $input['navire'], $input['numVoyage'], $input['etd'], $input['eta'],
                $input['origine'], $input['destination'], $input['client_id'], $input['expediteur'],
                $input['natureMarchandise'], $input['nombresColis'], $input['typeConteneur'],
                $input['poids'], $input['volume'], $input['valeurMarchandise'],
                $input['dateCreation'] ?? date("Y-m-d")
            ]);
            respond(["id" => $newId, "message" => "Dossier créé"]);
        } elseif ($method === 'DELETE' && $id) {
            $pdo->prepare("DELETE FROM dossiers WHERE id = ?")->execute([$id]);
            respond(["message" => "Dossier supprimé"]);
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
}
?>