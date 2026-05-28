<?php
echo "<h2>Git Show 2b319bf Details:</h2>";
echo "<pre>" . shell_exec("git show 2b319bf --stat 2>&1") . "</pre>";
?>
