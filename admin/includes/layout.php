<?php
require __DIR__.'/auth-check.php';
$navigation=[
 'dashboard'=>['title'=>'Dashboard','icon'=>'layout-dashboard','file'=>'index.php'],
 'courts'=>['title'=>'Court Management','icon'=>'layout-grid','file'=>'courts.php'],
 'reservations'=>['title'=>'Reservations','icon'=>'calendar-check-2','file'=>'reservations.php'],
 'calendar'=>['title'=>'Booking Calendar','icon'=>'calendar-days','file'=>'calendar.php'],
 'customers'=>['title'=>'Customers','icon'=>'users-round','file'=>'customers.php'],
 'payments'=>['title'=>'Payments','icon'=>'wallet','file'=>'payments.php'],
 'booking-addons'=>['title'=>'Add-ons & Food','icon'=>'shopping-bag','file'=>'booking-addons.php'],
 'reports'=>['title'=>'Reports & Analytics','icon'=>'chart-no-axes-combined','file'=>'reports.php'],
 'settings'=>['title'=>'Settings','icon'=>'settings-2','file'=>'settings.php'],
];
foreach(['courts'=>'Courts','reservations'=>'Reservations','customers'=>'Customers','payments'=>'Payments'] as $module=>$label){
 $navigation['archived-'.$module]=['title'=>'Archived '.$label,'icon'=>'archive','file'=>'archived.php?module='.$module,'sidebar'=>false,'parent'=>$module];
}
$page=$page ?? 'dashboard';
$business=settings();
?><!doctype html>
<html lang="en" class="dark">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#181C20"><title><?= e($navigation[$page]['title']) ?> · Pikol Castle</title><link rel="icon" href="../assets/images/logo.png"><link rel="stylesheet" href="../css/styles.css"><link rel="stylesheet" href="../assets/css/admin.css?v=5"><link rel="stylesheet" href="../assets/css/settings.css?v=5"><link rel="stylesheet" href="../assets/css/badge-motion.css?v=2"><link rel="stylesheet" href="../assets/css/admin-theme.css?v=5"><script src="../assets/js/admin-theme.js?v=1"></script><link rel="stylesheet" href="../assets/css/admin-tables.css?v=6"><link rel="stylesheet" href="../assets/css/archives.css?v=2"><?php if(in_array($page,['customers','archived-customers'],true)): ?><link rel="stylesheet" href="../assets/css/admin-customers.css?v=<?= filemtime(__DIR__.'/../../assets/css/admin-customers.css') ?>"><?php endif; ?><link rel="stylesheet" href="../assets/css/admin-calendar.css?v=1"><link rel="stylesheet" href="../assets/css/admin-reports.css?v=1"><link rel="stylesheet" href="../assets/css/reservation-customer-picker.css?v=<?= filemtime(__DIR__.'/../../assets/css/reservation-customer-picker.css') ?>"><link rel="stylesheet" href="../assets/css/booking-addons-admin.css?v=1"></head>
<body class="admin-app min-h-screen antialiased">
  <a class="skip-link" href="#admin-main">Skip to content</a>
  <?php require __DIR__.'/sidebar.php'; ?>
  <div class="workspace flex flex-col"><?php require __DIR__.'/header.php'; ?><main id="admin-main" tabindex="-1"><div class="page-loading"><span class="spinner"></span>Loading your workspace…</div></main><footer class="admin-footer"><span>PIKOL CASTLE <span class="muted">/ Administration</span></span></footer></div>
  <div id="modal-root" class="admin-modal-backdrop" hidden></div><div id="toast-region" class="toast-region" aria-live="polite"></div>
  <script type="application/json" id="admin-config"><?= json_encode(['page'=>$page,'user'=>$admin,'csrf'=>csrf_token(),'navigation'=>$navigation],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
  <script src="../assets/vendor/lucide.min.js" defer></script><script src="../assets/vendor/gsap.min.js" defer></script><script src="../assets/vendor/chart.umd.js" defer></script><script src="../assets/vendor/fullcalendar.min.js" defer></script><script src="../assets/vendor/luxon.min.js" defer></script><script src="../assets/vendor/fullcalendar-luxon3.min.js" defer></script><script src="../assets/js/admin.js?v=24" defer></script><script src="../assets/vendor/sweetalert2.all.min.js" defer></script><script src="../assets/js/table-tools.js?v=13" defer></script><script src="../assets/js/action-menus.js?v=13" defer></script><script src="../assets/js/modals.js?v=13" defer></script><script src="../assets/js/charts.js?v=12" defer></script><script src="../assets/js/dashboard.js?v=13" defer></script><script src="../assets/js/calendar.js?v=13" defer></script><script src="../assets/js/archives.js?v=2" defer></script><script src="../assets/js/reservation-customer-picker.js?v=<?= filemtime(__DIR__.'/../../assets/js/reservation-customer-picker.js') ?>" defer></script><script src="../assets/js/management.js?v=<?= filemtime(__DIR__.'/../../assets/js/management.js') ?>" defer></script>
<script src="../assets/js/booking-addons-admin.js?v=2" defer></script><script src="../assets/js/report-types.js?v=2" defer></script><script src="../assets/js/report-pdf.js?v=1" defer></script><script src="../assets/js/reschedule-admin.js?v=11" defer></script><script src="../assets/js/settings-extended.js?v=<?= filemtime(__DIR__.'/../../assets/js/settings-extended.js') ?>" defer></script><script src="../assets/js/admin-table-layout.js?v=3" defer></script><script src="../assets/js/live-updates.js?v=6" defer></script></body></html>
