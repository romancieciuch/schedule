<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$storage = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';

if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
	http_response_code(500);
	echo json_encode(['error' => 'Nie można przygotować magazynu danych.']);
	exit;
}

function respond(int $status, array $body): never
{
	http_response_code($status);
	echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function input(): array
{
	$v = json_decode(file_get_contents('php://input') ?: '', true);
	return is_array($v) ? $v : [];
}

function valid_id(mixed $id): bool
{
	return is_string($id) && preg_match('/^[a-f0-9]{32}$/', $id) === 1;
}

function read_plan(string $storage, string $id): array|false
{
	$file = $storage . DIRECTORY_SEPARATOR . $id . '.json';
	if (!is_file($file))
		return false;

	$p = json_decode(file_get_contents($file) ?: '', true);

	return is_array($p) ? $p : false;
}

function write_plan(string $storage, string $id, array $plan): bool
{
	$file = $storage . DIRECTORY_SEPARATOR . $id . '.json';
	$json = json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

	if ($json === false)
		return false;

	$tmp = tempnam($storage, 'plan-');
	if ($tmp === false)
		return false;

	$ok = file_put_contents($tmp, $json, LOCK_EX) !== false && rename($tmp, $file);
	if (!$ok && is_file($tmp))
		unlink($tmp);

	return $ok;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method !== 'POST')
	respond(405, ['error' => 'Dozwolone jest żądanie POST.']);

$body = input();

if ($action === 'create') {
	$password = $body['password'] ?? '';
	if (!is_string($password) || strlen($password) < 8)
		respond(400, ['error' => 'Hasło musi mieć co najmniej 8 znaków.']);

	$id = bin2hex(random_bytes(16));

	$hash = password_hash($password, PASSWORD_DEFAULT);
	if ($hash === false)
		respond(500, ['error' => 'Nie udało się zabezpieczyć hasła.']);

	$tasks = $body['tasks'] ?? [];
	if (!is_array($tasks))
		respond(400, ['error' => 'Nieprawidłowe dane kalendarza.']);

	if (!write_plan($storage, $id, ['id' => $id, 'password_hash' => $hash, 'tasks' => $tasks, 'created_at' => gmdate('c'), 'updated_at' => gmdate('c')]))
		respond(500, ['error' => 'Nie udało się zapisać kalendarza.']);

	respond(201, ['id' => $id]);
}

if ($action === 'load' || $action === 'save') {
	$id = $body['id'] ?? ($_GET['id'] ?? '');

	if (!valid_id($id))
		respond(400, ['error' => 'Nieprawidłowy identyfikator kalendarza.']);

	$plan = read_plan($storage, $id);
	if ($plan === false)
		respond(404, ['error' => 'Nie znaleziono kalendarza.']);

	$password = $body['password'] ?? '';
	if (!is_string($password) || !isset($plan['password_hash']) || !password_verify($password, $plan['password_hash']))
		respond(403, ['error' => 'Nieprawidłowe hasło dostępu.']);

	if ($action === 'load')
		respond(200, ['tasks' => $plan['tasks'] ?? []]);

	$tasks = $body['tasks'] ?? null;
	if (!is_array($tasks))
		respond(400, ['error' => 'Nieprawidłowe dane kalendarza.']);

	$plan['tasks'] = $tasks;
	$plan['updated_at'] = gmdate('c');

	if (!write_plan($storage, $id, $plan))
		respond(500, ['error' => 'Nie udało się zapisać zmian.']);

	respond(200, ['saved' => true]);
}

respond(404, ['error' => 'Nieznana operacja.']);