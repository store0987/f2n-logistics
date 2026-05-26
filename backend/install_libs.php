<?php
/**
 * Script d'installation automatique pour Dompdf et PHPMailer
 * À placer dans le dossier 'backend' (htdocs/api) et à exécuter via votre navigateur.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // Extension du temps d'exécution pour le téléchargement

$libsDir = __DIR__ . '/libs';
if (!is_dir($libsDir)) {
    mkdir($libsDir, 0755, true);
}

function installLib($url, $zipName, $targetDir, $renameFrom = null, $renameTo = null) {
    echo "Tentative de téléchargement de $zipName...<br>";
    $zipPath = __DIR__ . "/$zipName";
    
    // Utilisation de cURL car plus robuste sur les hébergements mutualisés
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);

    if (!$data) {
        echo "Erreur : Impossible de télécharger $zipName. Votre hébergeur bloque peut-être les connexions sortantes.<br>";
        return;
    }
    
    file_put_contents($zipPath, $data);
    
    $zip = new ZipArchive;
    $res = $zip->open($zipPath);
    if ($res === TRUE) {
        $zip->extractTo($targetDir);
        $zip->close();
        unlink($zipPath);
        echo "Extraction de $zipName réussie.<br>";
        
        if ($renameFrom && $renameTo && is_dir($targetDir . '/' . $renameFrom)) {
            rename($targetDir . '/' . $renameFrom, $targetDir . '/' . $renameTo);
            echo "Dossier renommé en $renameTo.<br>";
        }
    } else {
        echo "Erreur : Impossible d'ouvrir $zipName. Code erreur ZipArchive : $res. (L'archive est peut-être incomplète ou trop lourde pour le serveur).<br>";
    }
}

echo "<h2>Installation des bibliothèques PDF et Email</h2>";
installLib("https://github.com/dompdf/dompdf/releases/download/v3.0.0/dompdf_3-0-0.zip", "dompdf.zip", $libsDir);
installLib("https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip", "phpmailer.zip", $libsDir, "PHPMailer-6.9.1", "PHPMailer");
echo "<br><strong>Installation terminée !</strong> Pensez à supprimer ce fichier après vérification.";