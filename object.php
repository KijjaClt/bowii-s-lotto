<?php

class Lotto
{
    var $id;
    var $number;
    var $bought = false;
    var $total = 0;

    function __construct($id, $number, $top, $bottom)
    {
        $this->id = $id;
        $this->number = $number;
        $this->total = $top + $bottom;
        $this->bought = ($this->total > 0);
    }
}

class User
{
    var $id;
    var $name;

    function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}