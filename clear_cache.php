<?php

$files = glob('cache/*');
foreach($files as $file){
    if(is_file($file)) {
		@unlink($file);
	}
}

unset($files);

$files = glob('cache/mobile/*');
foreach($files as $file){
    if(is_file($file)) {
		@unlink($file);
	}
}

echo 'ok';
exit;