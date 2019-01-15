<?php
    foreach (range('A', 'Z') as $char) {
        echo "INSERT INTO `lotto`.`user` (`id`, `name`) VALUES ('".sha1('bowii'.sprintf('%s',$char))."', '".sprintf('%s',$char)."');"."\n";
    }