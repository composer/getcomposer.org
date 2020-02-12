<?php

// Generates a phar signature (as .sig next to the phar file)

// args: phar path, privkey, privkey password
$pkeyid = openssl_pkey_get_private("file://".realpath($_SERVER['argv'][2]), $_SERVER['argv'][3]);
if (!openssl_sign(file_get_contents($_SERVER['argv'][1]), $sha384sig, $pkeyid, OPENSSL_ALGO_SHA384)) {
    echo 'Failed to sign';
    exit(1);
}
openssl_free_key($pkeyid);

$sha384sig = base64_encode($sha384sig);
file_put_contents($_SERVER['argv'][1].'.sig', json_encode(['sha384' => $sha384sig], JSON_UNESCAPED_SLASHES));
