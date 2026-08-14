<?php
declare(strict_types=1);
function system_log(string $level, string $event, string $message, array $context = []): void { error_log('[CNG][' . $level . '][' . $event . '] ' . $message); try { db()->prepare('INSERT INTO system_logs(level,event,message,context) VALUES(?,?,?,?)')->execute([$level,$event,$message,json_encode($context)]); } catch (Throwable) {} }
