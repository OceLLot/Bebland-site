        <?php
// Смотрите пример использования password_hash(), для понимания откуда это взялось.
$hash = 'BCRYPT$10$ZxGMnN5lWik/fW/QNNMyM.TNAbhD3HKILloiHJoh/QQBujoGFu4za';
        $bsub = substr($hash, 6, 0);
if (password_verify('12345qwert' , "$2y" . $bsub)) {
    echo 'Пароль правильный!';
} else {
    echo $bsub;
}
?>