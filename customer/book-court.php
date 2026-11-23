<?php
// The old new-booking screen is retired. This route still serves checkout and rescheduling.
if(empty($_GET['reschedule'])&&!(($_GET['embedded']??'')==='1'&&($_GET['checkout']??'')==='1')){
    header('Location: ../index.html?book-slots=1#booking');exit;
}
$guestCheckout=($_GET['guest']??'')==='1'&&($_GET['embedded']??'')==='1'&&($_GET['checkout']??'')==='1'&&empty($_GET['reschedule'])&&empty($_GET['payment_booking']);
$page='book';require __DIR__.'/includes/layout.php';
