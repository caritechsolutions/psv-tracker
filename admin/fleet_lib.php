<?php
declare(strict_types=1);

/**
 * Fleet section helpers: POST handlers for vehicles / drivers / routes / owners,
 * plus small validators and the driver-token generator. Mirrors the ads.php
 * CRUD pattern (CSRF-checked POST -> mutate -> PRG redirect with a flash).
 */

const FLEET_TABS = ['vehicles', 'drivers', 'routes', 'owners'];

/** Stash a one-shot flash and redirect back to a tab (optionally an edit view). */
function fleet_redirect(string $tab, string $type, string $msg, ?int $edit = null): void
{
    admin_session_start();
    $_SESSION['fleet_flash'] = ['type' => $type, 'msg' => $msg];
    $tab = in_array($tab, FLEET_TABS, true) ? $tab : 'vehicles';
    $url = 'fleet.php?tab=' . $tab . ($edit ? '&edit=' . $edit : '');
    header('Location: ' . $url);
    exit;
}

function fleet_str_or_null($v): ?string
{
    $v = trim((string) $v);
    return $v === '' ? null : $v;
}

function fleet_int_or_null($v): ?int
{
    $v = trim((string) $v);
    return $v === '' ? null : (int) $v;
}

function fleet_generate_token(): string
{
    return bin2hex(random_bytes(32)); // 64 hex chars, fits VARCHAR(64)
}

function fleet_handle_post(PDO $pdo): void
{
    switch ($_POST['entity'] ?? '') {
        case 'vehicle': fleet_handle_vehicle($pdo); break;
        case 'driver':  fleet_handle_driver($pdo);  break;
        case 'route':   fleet_handle_route($pdo);   break;
        case 'owner':   fleet_handle_owner($pdo);   break;
        default:        fleet_redirect('vehicles', 'err', 'Unknown action.');
    }
}

function fleet_handle_vehicle(PDO $pdo): void
{
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM vehicles WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        } catch (PDOException $e) {
            fleet_redirect('vehicles', 'err', 'Cannot delete a vehicle with shift history — set it inactive instead.');
        }
        fleet_redirect('vehicles', 'ok', 'Vehicle deleted.');
    }

    $reg      = trim($_POST['registration'] ?? '');
    $label    = fleet_str_or_null($_POST['label'] ?? '');
    $capacity = fleet_int_or_null($_POST['capacity'] ?? '');
    if ($capacity !== null && $capacity < 0) {
        $capacity = 0;
    }
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $owner  = fleet_int_or_null($_POST['owner_id'] ?? '');

    if ($reg === '') {
        fleet_redirect('vehicles', 'err', 'Registration is required.');
    }

    try {
        if ($action === 'create') {
            $pdo->prepare('INSERT INTO vehicles (registration, label, capacity, status, owner_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$reg, $label, $capacity, $status, $owner]);
            fleet_redirect('vehicles', 'ok', 'Vehicle created.');
        }
        if ($action === 'update') {
            $pdo->prepare('UPDATE vehicles SET registration=?, label=?, capacity=?, status=?, owner_id=? WHERE id=?')
                ->execute([$reg, $label, $capacity, $status, $owner, (int) ($_POST['id'] ?? 0)]);
            fleet_redirect('vehicles', 'ok', 'Vehicle saved.');
        }
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            fleet_redirect('vehicles', 'err', 'That registration is already in use (or the selected owner is invalid).');
        }
        throw $e;
    }
    fleet_redirect('vehicles', 'err', 'Unknown action.');
}

function fleet_handle_driver(PDO $pdo): void
{
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $pdo->prepare("UPDATE drivers SET status = IF(status='active','suspended','active') WHERE id = ?")
            ->execute([(int) ($_POST['id'] ?? 0)]);
        fleet_redirect('drivers', 'ok', 'Driver updated.');
    }

    if ($action === 'token_generate') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('INSERT INTO driver_tokens (driver_id, token, label) VALUES (?, ?, ?)')
            ->execute([$id, fleet_generate_token(), 'admin issued']);
        fleet_redirect('drivers', 'ok', 'New token generated.', $id);
    }

    if ($action === 'token_revoke') {
        $tid = (int) ($_POST['token_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT driver_id FROM driver_tokens WHERE id = ?');
        $stmt->execute([$tid]);
        $driverId = (int) $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM driver_tokens WHERE id = ?')->execute([$tid]);
        fleet_redirect('drivers', 'ok', 'Token revoked.', $driverId ?: null);
    }

    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $status   = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';

    if ($name === '' || $username === '') {
        fleet_redirect('drivers', 'err', 'Name and username are required.');
    }

    try {
        if ($action === 'create') {
            if ($password === '') {
                fleet_redirect('drivers', 'err', 'A password is required for a new driver.');
            }
            $pdo->prepare('INSERT INTO drivers (name, username, password_hash, status) VALUES (?, ?, ?, ?)')
                ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $status]);
            fleet_redirect('drivers', 'ok', 'Driver created.');
        }
        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($password !== '') {
                $pdo->prepare('UPDATE drivers SET name=?, username=?, password_hash=?, status=? WHERE id=?')
                    ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $status, $id]);
            } else {
                $pdo->prepare('UPDATE drivers SET name=?, username=?, status=? WHERE id=?')
                    ->execute([$name, $username, $status, $id]);
            }
            fleet_redirect('drivers', 'ok', 'Driver saved.', $id);
        }
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            fleet_redirect('drivers', 'err', 'That username is already taken.');
        }
        throw $e;
    }
    fleet_redirect('drivers', 'err', 'Unknown action.');
}

function fleet_handle_route(PDO $pdo): void
{
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM routes WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        } catch (PDOException $e) {
            fleet_redirect('routes', 'err', 'Cannot delete a route with shift history.');
        }
        fleet_redirect('routes', 'ok', 'Route deleted.');
    }

    $num  = trim($_POST['route_number'] ?? '');
    $name = trim($_POST['name'] ?? '');
    if ($num === '' || $name === '') {
        fleet_redirect('routes', 'err', 'Route number and name are required.');
    }

    if ($action === 'create') {
        $pdo->prepare('INSERT INTO routes (route_number, name) VALUES (?, ?)')->execute([$num, $name]);
        fleet_redirect('routes', 'ok', 'Route created.');
    }
    if ($action === 'update') {
        $pdo->prepare('UPDATE routes SET route_number=?, name=? WHERE id=?')
            ->execute([$num, $name, (int) ($_POST['id'] ?? 0)]);
        fleet_redirect('routes', 'ok', 'Route saved.');
    }
    fleet_redirect('routes', 'err', 'Unknown action.');
}

function fleet_handle_owner(PDO $pdo): void
{
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        // Safe: vehicles.owner_id is ON DELETE SET NULL.
        $pdo->prepare('DELETE FROM owners WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        fleet_redirect('owners', 'ok', 'Owner deleted.');
    }

    $name    = trim($_POST['name'] ?? '');
    $contact = fleet_str_or_null($_POST['contact_name'] ?? '');
    $email   = fleet_str_or_null($_POST['email'] ?? '');
    $phone   = fleet_str_or_null($_POST['phone'] ?? '');
    $status  = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($name === '') {
        fleet_redirect('owners', 'err', 'Owner name is required.');
    }
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fleet_redirect('owners', 'err', 'Email address is not valid.');
    }

    if ($action === 'create') {
        $pdo->prepare('INSERT INTO owners (name, contact_name, email, phone, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $contact, $email, $phone, $status]);
        fleet_redirect('owners', 'ok', 'Owner created.');
    }
    if ($action === 'update') {
        $pdo->prepare('UPDATE owners SET name=?, contact_name=?, email=?, phone=?, status=? WHERE id=?')
            ->execute([$name, $contact, $email, $phone, $status, (int) ($_POST['id'] ?? 0)]);
        fleet_redirect('owners', 'ok', 'Owner saved.');
    }
    fleet_redirect('owners', 'err', 'Unknown action.');
}
