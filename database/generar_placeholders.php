<?php

$img = imagecreatetruecolor(400, 250);
$bg = imagecolorallocate($img, 230, 230, 230);
$texto_color = imagecolorallocate($img, 50, 50, 50);
imagefill($img, 0, 0, $bg);
imagestring($img, 5, 130, 110, 'MUESTRA', $texto_color);
imagepng($img, __DIR__ . '/../storage/cedulas/placeholder.png');

echo "Placeholder generado.\n";