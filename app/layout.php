<?php
declare(strict_types=1);
function page_start(string $title, ?array $user = null): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?></title><link rel="icon" href="assets/favicon.svg"><link rel="stylesheet" href="assets/css/app.css"></head><body><header class="topbar"><div class="brand"><img src="assets/stratastaff-logo.png" alt="Strata Staff Global"><span class="brand-divider"></span><div class="client-mark">JAMESONS</div></div><nav class="nav"><a class="active" href="index.php">Tickets</a><?php if($user):?><a href="logout.php">Sign out</a><?php endif;?></nav><div class="account"><?=e($user['full_name'] ?? '')?></div></header><main class="page">
<?php }
function page_end(): void { ?></main></body></html><?php }
