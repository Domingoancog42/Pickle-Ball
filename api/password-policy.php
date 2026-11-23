<?php
function password_policy(): array {
    $s=security_settings();
    return ['minimum'=>(int)$s['minimum_password_length'],'maximum'=>(int)$s['maximum_password_length'],'common'=>['password','password123','123456789012','qwerty123456','admin123456','pikolcastle123'],'labels'=>['length'=>'Uses '.$s['minimum_password_length'].' to '.$s['maximum_password_length'].' characters','common'=>'Is not an obvious/common password']];
}
function password_requirements(string $password,string $email): array {
    $p=password_policy();$n=mb_strlen($password,'UTF-8');
    return ['length'=>$n>=$p['minimum']&&$n<=$p['maximum']&&!str_contains($password,chr(0)),'common'=>!in_array(strtolower($password),$p['common'],true)];
}
