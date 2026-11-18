<?php
declare(strict_types=1);
function cfg(): array { static $c=null; if ($c===null) { if (!is_file(__DIR__.'/config.php')) throw new RuntimeException('Copy config.example.php to config.php'); $c=require __DIR__.'/config.php'; } return $c; }
function db(): PDO { static $db=null; if (!$db) { $db=new PDO('sqlite:'.__DIR__.'/campaign.sqlite'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC); $db->exec('PRAGMA busy_timeout=5000'); } return $db; }
function boot(): void { date_default_timezone_set(cfg()['timezone']); if (session_status() === PHP_SESSION_NONE && PHP_SAPI!=='cli') { ini_set('session.cookie_httponly','1'); ini_set('session.cookie_samesite','Strict'); if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off') ini_set('session.cookie_secure','1'); session_start(); } }
