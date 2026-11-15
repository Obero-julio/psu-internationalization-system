<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $pdo = db();

    /* GET FORM DATA */
    $country_id     = trim($_POST['country_id'] ?? '');
    $country_manual = trim($_POST['country_manual'] ?? '');
    $institution    = trim($_POST['institution_name'] ?? '');
    $agreementName  = trim($_POST['agreement_type'] ?? '');
    $status         = ucfirst(strtolower(trim($_POST['status'] ?? 'Active'))); 
    
    $firstName      = trim($_POST['contact_first_name'] ?? '');
    $middleName     = trim($_POST['contact_mi'] ?? '');
    $lastName       = trim($_POST['contact_last_name'] ?? '');
    $email          = trim($_POST['contact_email'] ?? '');
    $expiry         = trim($_POST['expiry_date'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');

    if ($institution === '') {
        echo json_encode(['success' => false, 'message' => 'Institution name is required.']);
        exit;
    }

    /* HANDLE COUNTRY */
    $countryName = '';

    if ($country_id === '__other__') {
        $countryName = $country_manual;
    } elseif (is_numeric($country_id)) {
        // Look up the name from the countries_addresses table (where the dropdown came from)
        $stmt = $pdo->prepare("SELECT country_name FROM countries_addresses WHERE id = ?");
        $stmt->execute([$country_id]);
        $countryName = $stmt->fetchColumn();
    }

    if (!$countryName) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing country.']);
        exit;
    }

    // Now find or create the country in the `countries` table (the one with the Foreign Key)
    $stmt = $pdo->prepare("SELECT id FROM countries WHERE country_name = ?");
    $stmt->execute([$countryName]);
    $realCountryId = $stmt->fetchColumn();

    if (!$realCountryId) {
        // It doesn't exist in `countries` yet, so create it
        $stmt = $pdo->prepare("INSERT INTO countries (country_name) VALUES (?)");
        $stmt->execute([$countryName]);
        $realCountryId = $pdo->lastInsertId();
    }

    /* HANDLE AGREEMENT TYPE */
    $agreementId = null;
    if ($agreementName !== '') {
        $stmt = $pdo->prepare("SELECT id FROM agreement_types WHERE agreement_name = ?");
        $stmt->execute([$agreementName]);
        $agreementId = $stmt->fetchColumn();
        
        if (!$agreementId) {
            $stmt = $pdo->prepare("INSERT INTO agreement_types (agreement_name) VALUES (?)");
            $stmt->execute([$agreementName]);
            $agreementId = $pdo->lastInsertId();
        }
    }

    /* INSERT PARTNER */
    $stmt = $pdo->prepare("
        INSERT INTO partners (
            institution_name, country_id, agreement_type_id, status, 
            contact_first_name, contact_middle_name, contact_last_name, 
            contact_email, expiry_date, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $institution, 
        (int)$realCountryId, 
        $agreementId ? (int)$agreementId : null, 
        $status,
        $firstName ?: null, 
        $middleName ?: null, 
        $lastName ?: null, 
        $email ?: null, 
        $expiry ?: null, 
        $notes ?: null
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Partner added successfully.'
    ]);

} catch (Throwable $e) {
    error_log("Add Partner Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'DB Error: ' . $e->getMessage()
    ]);
}