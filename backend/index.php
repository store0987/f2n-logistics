<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M'); // Augmente la mémoire pour Dompdf

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
                $stmt = $pdo->prepare("SELECT f.*, c.nom as client_nom, c.email as client_email, c.adresse as client_adresse, c.ville as client_ville, d.numBL, d.navire, d.origine, d.destination, d.poids, d.volume, d.nombresColis FROM factures f LEFT JOIN clients c ON f.client_id = c.id LEFT JOIN dossiers d ON f.dossier_id = d.id WHERE f.numeroFacture = ?");
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

                $html = "
                <html>
                <head>
                    <style>
                        body { font-family: 'Helvetica', sans-serif; color: #333; font-size: 12px; }
                        .header { margin-bottom: 30px; }
                        .company-info { float: left; width: 50%; }
                        .invoice-info { float: right; width: 30%; text-align: right; }
                        .clear { clear: both; }
                        .client-box { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; width: 45%; float: left; min-height: 100px; }
                        .dossier-box { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; width: 45%; float: right; min-height: 100px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background: #f8f9fa; padding: 10px; border-bottom: 2px solid #ddd; text-align: left; }
                        td { padding: 10px; border-bottom: 1px solid #eee; }
                        .totals { float: right; width: 250px; margin-top: 20px; }
                        .total-row { display: flex; justify-content: space-between; padding: 5px 0; }
                        .grand-total { border-top: 2px solid #333; font-weight: bold; font-size: 14px; margin-top: 5px; padding-top: 5px; }
                    </style>
                </head>
                <body>
                    <div class='header'>
                        <div class='company-info'>
                            <h2 style='color: #2563eb; margin: 0;'>F2N LOGISTICS</h2>
                            <p>Commissionnaire Agréé<br>Zone Franche Industrielle, Dakar, Sénégal<br>Tél: +221 33 000 00 00</p>
                        </div>
                        <div class='invoice-info'>
                            <h1 style='margin: 0;'>$title</h1>
                            <p>N°: $id<br>Date: {$facture['date']}</p>
                        </div>
                        <div class='clear'></div>
                    </div>

                    <div class='client-box'>
                        <strong>Facturé à:</strong><br>
                        {$facture['client_nom']}<br>
                        " . ($facture['client_adresse'] ?: '') . "<br>
                        " . ($facture['client_ville'] ?: '') . "
                    </div>
                    
                    <div class='dossier-box'>
                        <strong>Dossier: {$facture['dossier_id']}</strong><br>
                        B/L: {$facture['numBL']}<br>
                        Navire: {$facture['navire']}<br>
                        Route: {$facture['origine']} -> {$facture['destination']}
                    </div>
                    <div class='clear'></div>

                    <table>
                        <thead>
                            <tr><th>Description</th><th>Qté</th><th>P.U</th><th>Total</th></tr>
                        </thead>
                        <tbody>";
                foreach($lignes as $l) {
                    $rowTotal = $l['quantite'] * $l['prixUnitaire'];
                    $html .= "<tr>
                        <td>{$l['description']}</td>
                        <td>{$l['quantite']}</td>
                        <td>" . number_format($l['prixUnitaire'], 0, ',', ' ') . "</td>
                        <td>" . number_format($rowTotal, 0, ',', ' ') . "</td>
                    </tr>";
                }
                foreach($deboursList as $db) {
                    $html .= "<tr>
                        <td><i style='color: #666;'>(Débours)</i> {$db['description']}</td>
                        <td>1</td>
                        <td>" . number_format($db['montant'], 0, ',', ' ') . "</td>
                        <td>" . number_format($db['montant'], 0, ',', ' ') . "</td>
                    </tr>";
                }
                $html .= "</tbody>
                    </table>

                    <div class='totals'>
                        <div class='total-row'><span>Sous-total:</span> <span>" . number_format($facture['sousTotal'], 0, ',', ' ') . " FCFA</span></div>
                        <div class='total-row'><span>TVA (18%):</span> <span>" . number_format($facture['montantTva'], 0, ',', ' ') . " FCFA</span></div>
                        <div class='total-row grand-total'><span>TOTAL TTC:</span> <span>" . number_format($facture['totalTtc'], 0, ',', ' ') . " FCFA</span></div>
                    </div>
                </body>
                </html>";

                if (file_exists('libs/dompdf/autoload.inc.php')) {
                    require_once 'libs/dompdf/autoload.inc.php';
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->loadHtml($html);
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
} catch (Exception $e) {
    respond(["error" => $e->getMessage()], 500);
}

?>