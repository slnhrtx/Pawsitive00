<?php
echo password_hash("MagicTurtle002.", PASSWORD_DEFAULT);
?>

$2y$10$AzMzBMcp2lJMDC67LQQB8uwvEAKi5e1GXTVnMAfEbVbyIXAQ.5FIa<?php
$dir = realpath("../uploads/pet_avatars/");
echo "Directory: " . $dir . "<br>";
echo "Exists: " . (is_dir($dir) ? "Yes" : "No") . "<br>";
echo "Writable: " . (is_writable($dir) ? "Yes" : "No") . "<br>";
echo "Owner: " . posix_getpwuid(fileowner($dir))['name'] . "<br>";
echo "Group: " . posix_getgrgid(filegroup($dir))['name'] . "<br>";
echo "Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "<br>";
?>