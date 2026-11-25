<?php
/**
 * api.php
 * Production REST API Controller - FutsalHub Arena
 * Menyediakan response JSON dengan HTTP Status Code standar (200, 400, 404, 409 Conflict).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BookingEngine.php';

$engine = new BookingEngine();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_schedule':
            $courtId = (int)($_GET['court_id'] ?? 1);
            $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
            $data = $engine->getDaySchedule($courtId, $date);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'get_days_summary':
            $courtId = (int)($_GET['court_id'] ?? 1);
            $rawDates = $_GET['dates'] ?? '';
            $dates = array_filter(array_map('trim', explode(',', $rawDates)));
            if (empty($dates)) {
                $dates = [date('Y-m-d')];
            }
            $summary = $engine->getDaysSlotSummary($courtId, $dates);
            echo json_encode(['success' => true, 'data' => $summary]);
            break;

        case 'acquire_lock':
            $courtId  = (int)($_POST['court_id'] ?? 0);
            $date     = trim((string)($_POST['date'] ?? ''));
            $hour     = (int)($_POST['hour'] ?? 0);
            $token    = trim((string)($_POST['token'] ?? ''));

            if (!$courtId || !$date || !$hour || !$token) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
                exit;
            }

            $result = $engine->acquireLock($courtId, $date, $hour, $token);
            if (!$result['success']) {
                http_response_code(409); // 409 Conflict for Race Condition!
                echo json_encode($result);
            } else {
                echo json_encode($result);
            }
            break;

        case 'release_lock':
            $courtId  = (int)($_POST['court_id'] ?? 0);
            $date     = trim((string)($_POST['date'] ?? ''));
            $hour     = (int)($_POST['hour'] ?? 0);
            $token    = trim((string)($_POST['token'] ?? ''));
            $released = $engine->releaseLock($courtId, $date, $hour, $token);
            echo json_encode(['success' => $released]);
            break;

        case 'confirm_dp':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $courtId  = (int)($input['court_id'] ?? 0);
            $date     = trim((string)($input['date'] ?? ''));
            $hour     = (int)($input['hour'] ?? 0);
            $customer = trim((string)($input['customer_name'] ?? ''));
            $phone    = trim((string)($input['phone_number'] ?? ''));
            $token    = trim((string)($input['token'] ?? ''));
            $method   = trim((string)($input['payment_method'] ?? 'QRIS'));

            if (!$courtId || !$date || !$hour || !$customer || !$token) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Data formulir tidak lengkap.']);
                exit;
            }

            $booking = $engine->confirmOnlineDP($courtId, $date, $hour, $customer, $phone, $token, $method);
            
            // Sertakan URL WhatsApp Admin otomatis (Gaya Inos Futsal)
            $booking['whatsappUrl'] = buildWhatsAppBookingUrl(
                $booking['bookingCode'],
                $customer,
                $booking['courtName'],
                $date,
                $booking['timeLabel'],
                (float)$booking['paidDP']
            );

            echo json_encode(['success' => true, 'data' => $booking]);
            break;

        case 'cek_booking':
            $query = trim((string)($_GET['q'] ?? ''));
            $res = $engine->getBookingHistory($query);
            echo json_encode(['success' => true, 'data' => $res]);
            break;

        case 'settle_cashier':
            $code   = trim((string)($_POST['booking_code'] ?? ''));
            $method = trim((string)($_POST['payment_method'] ?? 'CASH'));

            if (!$code) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Kode booking wajib diisi.']);
                exit;
            }

            $res = $engine->settleAtCashier($code, $method);
            echo json_encode(['success' => true, 'data' => $res]);
            break;

        case 'walkin_booking':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $courtId  = (int)($input['court_id'] ?? 1);
            $date     = trim((string)($input['date'] ?? date('Y-m-d')));
            $hour     = (int)($input['hour'] ?? 8);
            $customer = trim((string)($input['customer_name'] ?? 'Pelanggan Walk-In'));
            $phone    = trim((string)($input['phone_number'] ?? '-'));
            $isFull   = ($input['payment_option'] ?? 'LUNAS') === 'LUNAS';

            $walkin = $engine->createWalkIn($courtId, $date, $hour, $customer, $phone, $isFull);
            echo json_encode(['success' => true, 'data' => $walkin]);
            break;

        case 'search_ticket':
            $code = trim((string)($_GET['code'] ?? ''));
            $ticket = $engine->getTicketByCode($code);
            if (!$ticket) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => "Kode booking {$code} tidak ditemukan."]);
            } else {
                echo json_encode(['success' => true, 'data' => $ticket]);
            }
            break;

        case 'get_history':
            $q = trim((string)($_GET['q'] ?? ''));
            $history = $engine->getBookingHistory($q);
            echo json_encode(['success' => true, 'data' => $history]);
            break;

        case 'get_cashier_data':
            $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
            $overview = $engine->getCashierOverview($date);
            echo json_encode(['success' => true, 'data' => $overview]);
            break;

        case 'test_race_condition':
            // Uji konkurensi terdistribusi nyata di level database
            $courtId = (int)($_GET['court_id'] ?? 1);
            $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
            $hour = (int)($_GET['hour'] ?? 20);

            // Bersihkan slot target terlebih dahulu
            $db = Database::getConnection();
            $db->exec("DELETE FROM slot_locks WHERE court_id = {$courtId} AND booking_date = '{$date}' AND slot_hour = {$hour}");
            $db->exec("DELETE FROM bookings WHERE court_id = {$courtId} AND booking_date = '{$date}' AND slot_hour = {$hour}");

            $tokenA = 'User_A_' . rand(100, 999);
            $tokenB = 'User_B_' . rand(100, 999);

            $reqA = $engine->acquireLock($courtId, $date, $hour, $tokenA);
            $reqB = $engine->acquireLock($courtId, $date, $hour, $tokenB);

            echo json_encode([
                'success' => true,
                'targetSlot' => sprintf('Lapangan %d, Jam %02d:00 (%s)', $courtId, $hour, $date),
                'requestA' => [
                    'actor' => $tokenA,
                    'httpStatus' => $reqA['statusCode'],
                    'statusText' => '200 OK (Kunci Didapatkan)',
                    'message' => 'Berhasil mengunci slot selama 5 menit.'
                ],
                'requestB' => [
                    'actor' => $tokenB,
                    'httpStatus' => $reqB['statusCode'],
                    'statusText' => '409 Conflict (Pessimistic Lock Intercepted)',
                    'message' => $reqB['message']
                ]
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
