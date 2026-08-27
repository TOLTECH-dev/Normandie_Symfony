<?php 
echo "post_max_size=".ini_get("post_max_size")."\n"; 
echo "upload_max_filesize=".ini_get("upload_max_filesize")."\n"; 
echo "memory_limit=".ini_get("memory_limit")."\n"; 
echo "max_file_uploads=".ini_get("max_file_uploads")."\n"; 
echo "Loaded ini: ".php_ini_loaded_file()."\n";
?>