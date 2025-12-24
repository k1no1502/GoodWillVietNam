<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    $inventoryRow = Database::fetch("SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS ts FROM inventory");
    $donationRow = Database::fetch("SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS ts FROM donations");
    $orderRow = Database::fetch("SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS ts FROM orders");
    $campaignVolunteerTs = 0;
    $campaignTaskTs = 0;
    $volunteerHoursTs = 0;

    try {
        $campaignVolunteerRow = Database::fetch("SELECT UNIX_TIMESTAMP(MAX(created_at)) AS ts FROM campaign_volunteers");
        $campaignVolunteerTs = (int)($campaignVolunteerRow['ts'] ?? 0);
    } catch (Exception $e) {
        $campaignVolunteerTs = 0;
    }

    try {
        $campaignTaskRow = Database::fetch("SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS ts FROM campaign_tasks");
        $campaignTaskTs = (int)($campaignTaskRow['ts'] ?? 0);
    } catch (Exception $e) {
        $campaignTaskTs = 0;
    }

    try {
        $volunteerHoursRow = Database::fetch("SELECT UNIX_TIMESTAMP(MAX(created_at)) AS ts FROM volunteer_hours_logs");
        $volunteerHoursTs = (int)($volunteerHoursRow['ts'] ?? 0);
    } catch (Exception $e) {
        $volunteerHoursTs = 0;
    }

    $inventoryTs = (int)($inventoryRow['ts'] ?? 0);
    $donationTs = (int)($donationRow['ts'] ?? 0);
    $orderTs = (int)($orderRow['ts'] ?? 0);

    $version = implode('-', [
        $inventoryTs,
        $donationTs,
        $orderTs,
        $campaignVolunteerTs,
        $campaignTaskTs,
        $volunteerHoursTs
    ]);

    echo json_encode([
        'success' => true,
        'inventory_version' => $inventoryTs,
        'donation_version' => $donationTs,
        'order_version' => $orderTs,
        'campaign_volunteer_version' => $campaignVolunteerTs,
        'campaign_task_version' => $campaignTaskTs,
        'volunteer_hours_version' => $volunteerHoursTs,
        'version' => $version
    ]);
} catch (Exception $e) {
    error_log('get-data-version error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch version.'
    ]);
}
?>
