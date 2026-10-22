<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_user('System Administrator');
$verificationId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$verificationId) {
	http_response_code(404);
	exit('Document not found.');
}
$stmt = $conn->prepare('SELECT document_path FROM user_verifications WHERE verification_id=?');
$stmt->bind_param('i', $verificationId);
$stmt->execute();
$filename = $stmt->get_result()->fetch_column();
$stmt->close();
if (!is_string($filename) || !preg_match('/\A[a-f0-9]{32}\.(pdf|jpg|png|webp)\z/', $filename)) {
	http_response_code(404);
	exit('Document not found.');
}
$directory = realpath(verification_storage_directory());
$path = $directory ? realpath($directory . DIRECTORY_SEPARATOR . $filename) : false;
if (!$directory || !$path || dirname($path) !== $directory || !is_file($path)) {
	http_response_code(404);
	exit('Document not found.');
}
$extension = pathinfo($filename, PATHINFO_EXTENSION);
header('Content-Type: ' . match ($extension) {
	'pdf' => 'application/pdf',
	'jpg' => 'image/jpeg',
	'png' => 'image/png',
	default => 'image/webp',
});
if (($_GET['download'] ?? '') === '1') {
	header('Content-Disposition: attachment; filename="AgriBid-verification-document.' . $extension . '"');
} else {
	header('Content-Disposition: inline; filename="AgriBid-verification-document.' . $extension . '"');
}
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'");
readfile($path);
