* Transformer l'AST PHP en AST javascript (SpiderMonkey AST) compatible avec Babel
* Transformer l'AST babel en ES6 (ou en ES5 si pas possible)
    * https://github.com/babel/babel/issues/1122
    * https://github.com/benjamn/recast
        * https://www.npmjs.com/package/5to6
    * https://github.com/estools/escodegen
    * https://github.com/estools/escope
    * http://kamicane.github.io/harmonizer-demo/

===============================================

* Hoist classes
    * Resolve class graph before hoisting (class order of definition)

* Handle abstract classes
* Handle interfaces and runtime resolving of instanceof
* Handle instanceof