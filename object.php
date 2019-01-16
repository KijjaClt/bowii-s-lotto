<?php

class Lotto
{
    var $id;
    var $number;
    var $bought = false;
    var $total = 0;
    var $top;
    var $bottom;

    function __construct($id, $number, $top, $bottom)
    {
        $this->id = $id;
        $this->number = $number;
        $this->total = $top + $bottom;
        $this->bought = ($this->total > 0);
        $this->top = intval($top);
        $this->bottom = intval($bottom);
    }
}

class LottoDetail
{
    var $id;
    var $customer;
    var $total = 0;
    var $top;
    var $bottom;
    var $create_at;

    function __construct($id, $customer, $top, $bottom, $create_at)
    {
        $this->id = $id;
        $this->customer = $customer;
        $this->total = $top + $bottom;
        $this->top = intval($top);
        $this->bottom = intval($bottom);
        $this->create_at = $create_at;
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