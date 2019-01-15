<?php
    foreach (range(0, 99) as $i) {
        echo "INSERT INTO `lotto`.`number` (`id`, `number`) VALUES ('".sha1('bowii'.sprintf('%02d',$i))."', '".sprintf('%02d',$i)."');"."\n";
    }

    foreach (range(0, 999) as $i) {
        echo "INSERT INTO `lotto`.`number` (`id`, `number`) VALUES ('".sha1('bowii'.sprintf('%03d',$i))."', '".sprintf('%03d',$i)."');"."\n";
    }