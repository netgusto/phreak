<?php

interface Edible extends Burnable {
    public function digest();
}

interface Buyable {
    public function price();
}

interface Burnable {
    public function calories();
}

abstract class Bonbon implements Edible {

    protected $a;
    protected $b;
    protected $c = "hello-depuis-c";
    protected $d = "world-depuis-d";

    public function __construct($a, $b, $c = null) {
        $this->a = $a;
        $this->b = $b;
        //$this->e = array('cinq', 'six', 8 => 'neuf');

        if(!is_null($c)) { $this->c = $c; }
    }

    public function say($something) {
        return $something . ": " . $this->a . ", " . $this->b . ", " . $this->c;
    }

    public abstract function digest();
}

class Sucette extends Bonbon {

    public function __construct($a, $b) {
        parent::__construct('a-super', 'b-super');
    }

    public function say($something) {
        return "SUCETTE !:" . parent::say($something);
    }
}

$a = 5;
$b = 2;
$d = 4 + $b * 3 / 1.3;
$a = 2;
$o = new Sucette($d, 5);

for($k = 5; $k < $d; $k++) {
    hey($a + $b + 3 . '-hello-' . $d . "\n");
    echo $o->say('Hello') . "\n";
}

function hey($something) {
    echo $something;
}

echo $o instanceof Bonbon . "\n";
echo $o instanceof Burnable;