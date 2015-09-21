<?php

namespace Main;

interface tutorial {
    public function greet(array $one = null, world $two = null);
}

class hello {
    public function hello() {
        return "hello";
    }
}

class world extends hello implements tutorial {
    public function greet(array $one = null, world $two = null) {
        return $this->hello() . ", World !";
    }
}

$h = new world();
echo $h->greet();