<?php
require_once __DIR__ . '/../config/init.php';
requireLogin();

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = 'Невалидна сесия. Моля, опитайте отново.';
    header('Location: edit.php');
    exit;
}

// Check if ID is provided
if (!isset($_POST['id'])) {
    $_SESSION['error_message'] = 'Липсващо ID на животновъдния обект.';
    header('Location: edit.php');
    exit;
}

$id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

try {
    // Get current animal object data
    $stmt = $pdo->prepare("SELECT * FROM animal_objects WHERE id = ?");
    $stmt->execute([$id]);
    $current_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_data) {
        $_SESSION['error_message'] = 'Животновъдният обект не беше намерен.';
        header('Location: edit.php');
        exit;
    }

    // Get form data
    $star_jo = htmlspecialchars(trim($_POST['star_jo']), ENT_QUOTES, 'UTF-8');
    $nov_jo = htmlspecialchars(trim($_POST['nov_jo']), ENT_QUOTES, 'UTF-8');
    $proizvoditel = htmlspecialchars(trim($_POST['proizvoditel']), ENT_QUOTES, 'UTF-8');
    $bulstat = htmlspecialchars(trim($_POST['bulstat']), ENT_QUOTES, 'UTF-8');
    $eik_egn = htmlspecialchars(trim($_POST['eik_egn']), ENT_QUOTES, 'UTF-8');
    $oblast = htmlspecialchars(trim($_POST['oblast']), ENT_QUOTES, 'UTF-8');
    $obshtina = htmlspecialchars(trim($_POST['obshtina']), ENT_QUOTES, 'UTF-8');
    $naseleno_miasto = htmlspecialchars(trim($_POST['naseleno_miasto']), ENT_QUOTES, 'UTF-8');
    $telefon = htmlspecialchars(trim($_POST['telefon'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email_mandra = htmlspecialchars(trim($_POST['email_mandra'] ?? ''), ENT_QUOTES, 'UTF-8');
    $belezhka = htmlspecialchars(trim($_POST['belezhka'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Check if star_jo is being changed
        if ($star_jo !== $current_data['star_jo']) {
            // Check if new star_jo already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM animal_objects WHERE star_jo = ? AND id != ?");
            $stmt->execute([$star_jo, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Вече съществува животновъден обект с този Стар ЖО.');
            }

            // Temporarily disable foreign key checks
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

            // First update related records in other tables
            $stmt = $pdo->prepare("UPDATE request_sample SET star_jo = ? WHERE star_jo = ?");
            $stmt->execute([$star_jo, $current_data['star_jo']]);

            $stmt = $pdo->prepare("UPDATE samples SET star_jo = ? WHERE star_jo = ?");
            $stmt->execute([$star_jo, $current_data['star_jo']]);

            $stmt = $pdo->prepare("UPDATE animal_milk_types SET star_jo = ? WHERE star_jo = ?");
            $stmt->execute([$star_jo, $current_data['star_jo']]);

            // Then update animal_objects
            $stmt = $pdo->prepare("UPDATE animal_objects SET 
                star_jo = ?,
                nov_jo = ?,
                proizvoditel = ?,
                bulstat = ?,
                eik_egn = ?,
                oblast = ?,
                obshtina = ?,
                naseleno_miasto = ?,
                telefon = ?,
                email = ?,
                email_mandra = ?,
                belezhka = ?
                WHERE id = ?");

            $stmt->execute([
                $star_jo,
                $nov_jo,
                $proizvoditel,
                $bulstat,
                $eik_egn,
                $oblast,
                $obshtina,
                $naseleno_miasto,
                $telefon,
                $email,
                $email_mandra,
                $belezhka,
                $id
            ]);

            // Re-enable foreign key checks
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } else {
            // If star_jo is not changing, just update the animal object
            $stmt = $pdo->prepare("UPDATE animal_objects SET 
                nov_jo = ?,
                proizvoditel = ?,
                bulstat = ?,
                eik_egn = ?,
                oblast = ?,
                obshtina = ?,
                naseleno_miasto = ?,
                telefon = ?,
                email = ?,
                email_mandra = ?,
                belezhka = ?
                WHERE id = ?");

            $stmt->execute([
                $nov_jo,
                $proizvoditel,
                $bulstat,
                $eik_egn,
                $oblast,
                $obshtina,
                $naseleno_miasto,
                $telefon,
                $email,
                $email_mandra,
                $belezhka,
                $id
            ]);
        }

        // Handle milk types
        if (isset($_POST['milk_types'])) {
            // Delete existing milk types
            $stmt = $pdo->prepare("DELETE FROM animal_milk_types WHERE star_jo = ?");
            $stmt->execute([$star_jo]);

            // Insert new milk types
            $stmt = $pdo->prepare("INSERT INTO animal_milk_types (star_jo, milk_type) VALUES (?, ?)");
            foreach ($_POST['milk_types'] as $milk_type) {
                $stmt->execute([$star_jo, $milk_type]);
            }
        }

        // Commit transaction
        $pdo->commit();

        $_SESSION['success_message'] = 'Животновъдният обект беше успешно обновен.';
        header('Location: edit.php');
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        // Make sure foreign key checks are re-enabled even if there's an error
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        throw $e;
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Грешка при обработка на данните: ' . $e->getMessage();
    header('Location: edit_form.php?id=' . $id);
    exit;
} 