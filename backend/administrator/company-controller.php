<?php
require_once __DIR__ . '/../shared/api.php';
api_require_roles(['admin', 'sas_staff', 'coordinator']);

try {
    $db = api_db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->query(
            'SELECT c.*, COUNT(DISTINCT p.id) AS active_placements
             FROM companies c
             LEFT JOIN ojt_placements p ON p.company_id = c.id AND p.status IN ("approved", "ongoing")
             GROUP BY c.id ORDER BY c.name'
        );
        api_json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    api_require_roles(['admin', 'sas_staff']);
    api_require_csrf();
    $input = api_input();
    $name = trim($input['name'] ?? '');
    $address = trim($input['address'] ?? '');
    $contactEmail = trim($input['contact_email'] ?? '');
    $website = trim($input['website'] ?? '');
    if ($name === '' || $address === '') {
        api_json(['success' => false, 'message' => 'Company name and address are required.'], 422);
    }
    if (($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) ||
        ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL))) {
        api_json(['success' => false, 'message' => 'Enter a valid contact email and website URL.'], 422);
    }
    $stmt = $db->prepare(
        'INSERT INTO companies (name, industry, address, contact_person, contact_email, contact_phone, website)
         VALUES (:name, :industry, :address, :contact_person, :contact_email, :contact_phone, :website)'
    );
    $stmt->execute([
        'name' => $name,
        'industry' => trim($input['industry'] ?? '') ?: null,
        'address' => $address,
        'contact_person' => trim($input['contact_person'] ?? '') ?: null,
        'contact_email' => $contactEmail ?: null,
        'contact_phone' => trim($input['contact_phone'] ?? '') ?: null,
        'website' => $website ?: null,
    ]);
    $companyId = (int) $db->lastInsertId();
    api_log($db, 'company.created', 'Added company ' . $name);
    api_json(['success' => true, 'message' => 'Company added.', 'id' => $companyId], 201);
} catch (Throwable $e) {
    error_log('Company API failed: ' . $e->getMessage());
    api_json(['success' => false, 'message' => 'Unable to process the company request.'], 500);
}
