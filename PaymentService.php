<?php
/**
 * Menangani transaksi pembayaran, validasi DP vs Pelunasan,
 * dan pencatatan audit trail finansial secara atomik.
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class PaymentService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * UPDATE / TRANSACTION: Memproses pembayaran DP atau Pelunasan.
     */
    public function recordPayment(string $bookingCode, float $amount, string $paymentMethod = 'QRIS'): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Nominal pembayaran harus lebih besar dari 0.");
        }

        $this->db->beginTransaction();

        try {
            // 1. Kunci baris booking
            $stmt = $this->db->prepare("
                SELECT id, booking_code, team_name, total_price, status 
                FROM bookings 
                WHERE booking_code = ? 
                FOR UPDATE
            ");
            $stmt->execute([$bookingCode]);
            $b = $stmt->fetch();

            if (!$b) {
                throw new RuntimeException("Kode booking '{$bookingCode}' tidak ditemukan.");
            }

            if (in_array($b['status'], ['CANCELLED', 'EXPIRED'], true)) {
                throw new RuntimeException("Tidak dapat membayar: Booking ini berstatus {$b['status']}.");
            }

            if ($b['status'] === 'SETTLED') {
                throw new RuntimeException("Booking ini sudah LUNAS sebelumnya.");
            }

            $totalPrice = (float) $b['total_price'];

            // 2. Cek total pembayaran yang sudah masuk sebelumnya
            $stmtHistory = $this->db->prepare("
                SELECT COALESCE(SUM(amount), 0) FROM payment_transactions WHERE booking_id = ?
            ");
            $stmtHistory->execute([$b['id']]);
            $previouslyPaid = (float) $stmtHistory->fetchColumn();

            $newTotalPaid = $previouslyPaid + $amount;
            $remaining = max(0.0, $totalPrice - $newTotalPaid);

            // 3. Tentukan tipe transaksi pembayaran
            $paymentType = match(true) {
                ($newTotalPaid >= $totalPrice && $previouslyPaid === 0.0) => 'FULL',
                ($newTotalPaid >= $totalPrice)                            => 'REPAYMENT',
                default                                                   => 'DP'
            };

            // 4. Catat transaksi pembayaran baru
            $trxRef = 'PAY-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -4));
            $stmtInsertPay = $this->db->prepare("
                INSERT INTO payment_transactions (booking_id, transaction_ref, payment_type, amount, payment_method)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtInsertPay->execute([$b['id'], $trxRef, $paymentType, $amount, $paymentMethod]);

            // 5. Update status di header booking
            $newStatus = ($newTotalPaid >= $totalPrice) ? 'SETTLED' : 'DP_PAID';
            $stmtUpd = $this->db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmtUpd->execute([$newStatus, $b['id']]);

            // 6. Catat log sistem (Node B)
            $stmtLog = $this->db->prepare("
                INSERT INTO system_logs (node_name, event_type, reference_code, message)
                VALUES ('Node B (Payment Gateway)', 'PAYMENT_PROCESSED', ?, ?)
            ");
            $logMsg = sprintf(
                "Pembayaran %s diterima Rp %s via %s untuk %s. Status sekarang: %s (Sisa: Rp %s).",
                $paymentType,
                number_format($amount),
                $paymentMethod,
                $bookingCode,
                $newStatus,
                number_format($remaining)
            );
            $stmtLog->execute([$bookingCode, $logMsg]);

            $this->db->commit();

            return [
                'success'         => true,
                'transaction_ref' => $trxRef,
                'payment_type'    => $paymentType,
                'amount_paid'     => $amount,
                'total_paid'      => $newTotalPaid,
                'remaining'       => $remaining,
                'status'          => $newStatus
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ambil rincian nota pembayaran untuk sebuah booking.
     */
    public function getPaymentsByBookingCode(string $bookingCode): array {
        $stmt = $this->db->prepare("
            SELECT pt.* 
            FROM payment_transactions pt
            JOIN bookings b ON pt.booking_id = b.id
            WHERE b.booking_code = ?
            ORDER BY pt.paid_at ASC
        ");
        $stmt->execute([$bookingCode]);
        return $stmt->fetchAll();
    }
}
