<?php

require('vendor/autoload.php');
require('JSConverter.php');

class MyNodeVisitor extends PhpParser\NodeVisitorAbstract {

    var $isInConstructor = false;
    var $constructorInitializedProperties = array();

    public function beforeTraverse(array $nodes) { }

    public function enterNode(PhpParser\Node $node) {

        if($node instanceof PhpParser\Node\Stmt\Class_) {
            $this->constructorInitializedProperties = array();
        }

        if($node instanceof PhpParser\Node\Stmt\ClassMethod && $node->name === '__construct') {
            $this->isInConstructor = true;
        }
    }

    public function leaveNode(PhpParser\Node $node) {

        if($node instanceof PhpParser\Node\Stmt\Property && $node->type === 2) {
            // Move property initialization in constructor if default value

            foreach($node->props as $prop) {
                if(is_null($prop->default)) { continue; }
                $this->constructorInitializedProperties[] = $prop;
            }

            return false;

        } else if($node instanceof PhpParser\Node\Expr\StaticCall && $node->class->parts[0] === "parent") {

            if($this->isInConstructor && $node->name === "__construct") {
                // Cas 1: méthode courante === constructor => super()
                return array(new PhpParser\Node\Expr\FuncCall(
                    new PhpParser\Node\Name('super'),
                    $node->args
                ));
            } else {
                // Cas 2: méthode courante !== constructor => super.
                // On transforme l'appel statique en appel d'instance
                return new PhpParser\Node\Expr\MethodCall(
                    new PhpParser\Node\Expr\Variable(
                        new PhpParser\Node\Name('super')
                    ),
                    $node->name,
                    $node->args
                );
            }
        } else if($node instanceof PhpParser\Node\Stmt\ClassMethod && $node->name === '__construct') {
            $this->isInConstructor = false;
        } else if($node instanceof PhpParser\Node\Stmt\ClassMethod && $node->isAbstract()) {
            // exclude abstract methods;
            return false;
        } else if($node instanceof PhpParser\Node\Stmt\Class_) {
            if(!empty($this->constructorInitializedProperties)) {

                // il faut déterminer si la classe définit un constructeur

                $constructor = null;

                foreach($node->stmts as $classstmt) {
                    if(
                        $classstmt instanceof PhpParser\Node\Stmt\ClassMethod &&
                        $classstmt->name === '__construct'
                    ) {
                        $constructor =& $classstmt;
                        break;
                    }
                }

                if(is_null($constructor)) {
                    // si aucun constructeur n'est défini, il faut l'ajouter

                    if(!is_null($node->extends)) {
                        // la classe dispose d'un parent
                        // le constructeur doit appeler super() pour transférer l'initialisation au parent
                        throw new \Exception('TODO:' . __LINE__);
                    }
                }

                // on ajoute l'initialisation des propriétés d'instance au constructeur
                foreach(array_reverse($this->constructorInitializedProperties) as $prop) {  # reverse because of unshift
                    array_unshift($constructor->stmts, new PhpParser\Node\Expr\Assign(
                        new PhpParser\Node\Expr\PropertyFetch(
                            new PhpParser\Node\Expr\Variable(
                                new PhpParser\Node\Name('this')
                            ),
                            $prop->name
                        ),
                        $prop->default
                    ));
                }

                $this->constructorInitializedProperties = array();
            }
        } else if($node instanceof PhpParser\Node\Expr\FuncCall) {
            if($node->name->__toString() === 'is_null') {
                return new PhpParser\Node\Expr\BinaryOp\Identical(
                    $node->args[0]->value,
                    new PhpParser\Node\Expr\ConstFetch(
                        new PhpParser\Node\Name('null')
                    )
                );
            }
        }

        if($node instanceof PhpParser\Node\Expr\Instanceof_) {
            return new PhpParser\Node\Expr\BinaryOp\BooleanOr(
                $node,
                new PhpParser\Node\Expr\BinaryOp\BooleanAnd(
                    new PhpParser\Node\Expr\BinaryOp\BooleanAnd(
                        new PhpParser\Node\Expr\PropertyFetch(
                            $node->expr,
                            new PhpParser\Node\Name('constructor')
                        ),
                        new PhpParser\Node\Expr\PropertyFetch(
                            new PhpParser\Node\Expr\PropertyFetch(
                                $node->expr,
                                new PhpParser\Node\Name('constructor')
                            ),
                            new PhpParser\Node\Name('_implements')
                        )
                    ),
                    new PhpParser\Node\Expr\MethodCall(
                        new PhpParser\Node\Expr\PropertyFetch(
                            $node->expr,
                            new PhpParser\Node\Name('constructor')
                        ),
                        '_implements',
                        array(
                            $node->class
                        )
                    )
                )
            );
        }

        if($node instanceof PhpParser\Node\Stmt\Interface_ || $node instanceof PhpParser\Node\Stmt\Class_) {

            /*
            class Edible {
                static _implements(what) {
                    const ifaces = [Burnable];
                    return !!(
                        ifaces.indexOf(what) > -1 ||
                        ifaces.map(v => v._implements(what)).reduce((a, v) => a || v, false) ||
                        (super._implements && super._implements(what))
                    );
                }
            }
            */

            $ifaces = array();

            if($node instanceof PhpParser\Node\Stmt\Interface_ && $node->extends) {
                $ifaces = $node->extends;
            } else if($node instanceof PhpParser\Node\Stmt\Class_ && $node->implements) {
                $ifaces = $node->implements;
            }

            $implementsmethod = new PhpParser\Node\Stmt\ClassMethod(
                new PhpParser\Node\Name('_implements'),
                array(
                    'type' => PhpParser\Node\Stmt\Class_::MODIFIER_STATIC,
                    'params' => array(
                        new PhpParser\Node\Param(
                            new PhpParser\Node\Name('what')
                        )
                    ),
                    'stmts' => array(
                        new PhpParser\Node\Expr\Assign(
                            new PhpParser\Node\Expr\Variable(
                                new PhpParser\Node\Name('ifaces')
                            ),
                            new PhpParser\Node\Expr\Array_(
                                array_map(function($iface) {
                                    return new PhpParser\Node\Expr\ArrayItem(
                                        new PhpParser\Node\Expr\Variable($iface)
                                    );
                                }, $ifaces)
                            )
                        ),
                        new PhpParser\Node\Stmt\Return_(
                            new PhpParser\Node\Expr\BooleanNot(
                                new PhpParser\Node\Expr\BooleanNot(
                                    new PhpParser\Node\Expr\BinaryOp\BooleanOr(
                                        new PhpParser\Node\Expr\BinaryOp\Greater(
                                            new PhpParser\Node\Expr\MethodCall(
                                                new PhpParser\Node\Expr\Variable(
                                                    new PhpParser\Node\Name('ifaces')
                                                ),
                                                'indexOf',
                                                array(
                                                    new PhpParser\Node\Arg(
                                                        new PhpParser\Node\Expr\Variable(
                                                            new PhpParser\Node\Name('what')
                                                        )
                                                    )
                                                )
                                            ),
                                            new PhpParser\Node\Scalar\LNumber(-1)
                                        ),
                                        new PhpParser\Node\Expr\BinaryOp\BooleanOr(
                                            new PhpParser\Node\Expr\MethodCall(
                                                new PhpParser\Node\Expr\MethodCall(
                                                    new PhpParser\Node\Expr\Variable(
                                                        new PhpParser\Node\Name('ifaces')
                                                    ),
                                                    'map',
                                                    array(
                                                        new PhpParser\Node\Arg(
                                                            new PhpParser\Node\Expr\Closure(
                                                                array(
                                                                    'params' => array(
                                                                        new PhpParser\Node\Param(
                                                                            new PhpParser\Node\Name('v')
                                                                        )
                                                                    ),
                                                                    'stmts' => array(
                                                                        new PhpParser\Node\Stmt\Return_(
                                                                            new PhpParser\Node\Expr\MethodCall(
                                                                                new PhpParser\Node\Expr\Variable(
                                                                                    new PhpParser\Node\Name('v')
                                                                                ),
                                                                                '_implements',
                                                                                array(
                                                                                    new PhpParser\Node\Arg(
                                                                                        new PhpParser\Node\Expr\Variable(
                                                                                            new PhpParser\Node\Name('what')
                                                                                        )
                                                                                    )
                                                                                )
                                                                            )
                                                                        )
                                                                    )
                                                                )
                                                            )
                                                        )
                                                    )
                                                ),
                                                'reduce',
                                                array(
                                                    new PhpParser\Node\Arg(
                                                        new PhpParser\Node\Expr\Closure(
                                                            array(
                                                                'params' => array(
                                                                    new PhpParser\Node\Param(
                                                                        new PhpParser\Node\Name('a')
                                                                    ),
                                                                    new PhpParser\Node\Param(
                                                                        new PhpParser\Node\Name('v')
                                                                    ),
                                                                ),
                                                                'stmts' => array(
                                                                    new PhpParser\Node\Stmt\Return_(
                                                                        new PhpParser\Node\Expr\BinaryOp\BooleanOr(
                                                                            new PhpParser\Node\Expr\Variable(
                                                                                new PhpParser\Node\Name('a')
                                                                            ),
                                                                            new PhpParser\Node\Expr\Variable(
                                                                                new PhpParser\Node\Name('v')
                                                                            )
                                                                        )
                                                                    )
                                                                )
                                                            )
                                                        )
                                                    ),
                                                    new PhpParser\Node\Arg(
                                                        new PhpParser\Node\Expr\ConstFetch(
                                                            new PhpParser\Node\Name('false')
                                                        )
                                                    )
                                                )
                                            ),
                                            new PhpParser\Node\Expr\BinaryOp\BooleanAnd(
                                                new PhpParser\Node\Expr\PropertyFetch(
                                                    new PhpParser\Node\Expr\Variable(
                                                        new PhpParser\Node\Name('super')
                                                    ),
                                                    new PhpParser\Node\Name('_implements')
                                                ),
                                                new PhpParser\Node\Expr\MethodCall(
                                                    new PhpParser\Node\Expr\Variable(
                                                        new PhpParser\Node\Name('super')
                                                    ),
                                                    '_implements',
                                                    array(
                                                        new PhpParser\Node\Arg(
                                                            new PhpParser\Node\Expr\Variable(
                                                                new PhpParser\Node\Name('what')
                                                            )
                                                        )
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            );

            if($node instanceof PhpParser\Node\Stmt\Interface_) {
                return new PhpParser\Node\Stmt\Class_(
                    new PhpParser\Node\Name($node->name),
                    array(
                        'stmts' => array($implementsmethod)
                    )
                );
            } else if($node instanceof PhpParser\Node\Stmt\Class_) {
                $node->stmts = array_merge(array($implementsmethod), $node->stmts);
                $node->implements = null;
                return $node;
            }
        }
    }

    public function afterTraverse(array $nodes) { }
}

$parser = new PhpParser\Parser(new PhpParser\Lexer\Emulative());
$traverser = new PhpParser\NodeTraverser();
$prettyPrinter = new JSConverter();
$nodeDumper = new PhpParser\NodeDumper();

// resolve namespaces as fully qualified namespaces
$traverser->addVisitor(new PhpParser\NodeVisitor\NameResolver()); // we will need resolved names

// add your visitor
$traverser->addVisitor(new MyNodeVisitor());

try {
    // parse
    $stmts = $parser->parse(file_get_contents($argv[1]));

    // traverse
    $stmts = $traverser->traverse($stmts);

    // pretty print
    //echo "/*", $nodeDumper->dump($stmts), "\n", "*/";
    #print_r($stmts);
    echo $prettyPrinter->prettyPrintFile($stmts);

} catch (PhpParser\Error $e) {
    echo 'Parse Error: ', $e->getMessage();
}
