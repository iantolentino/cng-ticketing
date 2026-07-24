<?php
require __DIR__ . '/app/bootstrap.php';
if (app_is_setup()) redirect('login.php');

$error = '';
$credentials = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    if ($name === '' || !preg_match('/^[a-z0-9._-]{3,80}$/', $username) || strlen($password) < 12) {
        $error = 'Enter your name, a valid username, and a password of at least 12 characters.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'super-admin'")->fetchColumn();
            $insert = $pdo->prepare('INSERT INTO users(role_id, username, full_name, password_hash, must_change_password) VALUES(?,?,?,?,0)');
            $insert->execute([$roleId, $username, $name, password_hash($password, PASSWORD_DEFAULT)]);
            $seeds = [
                ['Patricia Puno','management'], ['Michael O\'Bryan','management'], ['Neptalie Vitug','management'], ['Dan Fabros','management'], ['Karl Parson','management'],
                ['Leonard Sunga','team-leader'], ['Sheena Magdaraog','team-leader'], ['Trisha Balingit','team-leader'],
                ['Divina Rabago','pod-leader'], ['Rhon Mhiel Romano','pod-leader'], ['Paulyn Joyce Lino','pod-leader'], ['John Henry Casingal','pod-leader'],
                ['Cyril Anne Bayaya','sme'],
                ['Paul Culbi','department-head','rm'], ['Justine Lee-Daly','department-head','customer-care'], ['Michael Lamb','department-head','rm'], ['Abby Hoff','department-head','compliance'], ['Angela Culbi','department-head','admin'], ['Catherine Pilarski','department-head','insurance'],
            ];
            $roleLookup = $pdo->query('SELECT slug,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
            $departmentLookup = $pdo->query('SELECT code,id FROM departments')->fetchAll(PDO::FETCH_KEY_PAIR);
            $seedInsert = $pdo->prepare('INSERT INTO users(role_id, department_id, username, full_name, password_hash) VALUES(?,?,?,?,?)');
            foreach ($seeds as $seed) {
                $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $seed[0]));
                $base = trim($base, '.');
                $temporary = strtoupper(bin2hex(random_bytes(5)));
                $seedInsert->execute([$roleLookup[$seed[1]], $departmentLookup[$seed[2] ?? ''] ?? null, $base, $seed[0], password_hash($temporary, PASSWORD_DEFAULT)]);
                $credentials[] = [$seed[0], $base, $temporary];
            }
            $pdo->prepare("INSERT INTO app_settings(setting_key,setting_value) VALUES('setup_complete','1')")->execute();
            $pdo->commit();
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = 'Setup could not be completed. Check the database configuration and imported schema.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Initial setup</title><style>body{font:16px Arial,sans-serif;background:#F4F5F7;color:#111827;margin:0}main{max-width:760px;margin:48px auto;background:#fff;border:1px solid #E4E4E7;padding:32px}label{display:block;margin-top:16px;font-weight:600}input{box-sizing:border-box;width:100%;padding:10px;margin-top:6px;border:1px solid #E4E4E7}button{margin-top:22px;background:#1E5AA8;color:#fff;border:0;padding:11px 16px;font-weight:700}.error{color:#a21c1c}.credentials{width:100%;border-collapse:collapse}.credentials th,.credentials td{border:1px solid #E4E4E7;padding:8px;text-align:left}</style></head><body><main>
<h1>CNG / Jamesons initial setup</h1>
<?php if ($credentials): ?><p><strong>Save these temporary credentials now.</strong> They are shown only once; each seeded user must change their password after signing in.</p><table class="credentials"><tr><th>Name</th><th>Username</th><th>Temporary password</th></tr><?php foreach ($credentials as [$person,$user,$temp]): ?><tr><td><?=e($person)?></td><td><?=e($user)?></td><td><?=e($temp)?></td></tr><?php endforeach; ?></table><p><a href="login.php">Continue to login</a></p><?php else: ?>
<?php if ($error): ?><p class="error"><?=e($error)?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>Your name<input required name="full_name" autocomplete="name"></label><label>Super Admin username<input required name="username" pattern="[A-Za-z0-9._-]{3,80}" autocomplete="username"></label><label>Super Admin password<input required name="password" type="password" minlength="12" autocomplete="new-password"></label><button>Create system</button></form><?php endif; ?>
</main></body></html>

