<?php

use PhpParser\PrettyPrinter\Standard as PrettyPrinterStandard;
use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Stmt;
use PhpParser\Node\Name;

class JSConverter extends PrettyPrinterStandard {
    public function prettyPrintFile(array $stmts) {
        $p = rtrim($this->prettyPrint($stmts));
        return "'use strict';\n\n" . $p;
    }

    public function pStmt_Namespace(Stmt\Namespace_ $node) {
        return $this->pStmts($node->stmts);
    }

    public function pExpr_Variable(Expr\Variable $node) {
        if ($node->name instanceof Expr) {
            throw new \Ewception('pExpr_Variable: name is an expression');
        } else {
            return $node->name;
        }
    }

    public function pExpr_Assign(Expr\Assign $node) {

        $prefix = '';

        if($node->var instanceof Expr\Variable) {
            $prefix = 'var ';
        }

        return $prefix . $this->pInfixOp('Expr_Assign', $node->var, ' = ', $node->expr);
    }

    public function pStmt_Echo(Stmt\Echo_ $node) {
        return 'process.stdout.write(String(' . $this->pCommaSeparated($node->exprs) . '));';
    }

    public function pScalar_String(Scalar\String_ $node) {
        if(strpos($node->value, "\n") !== false) {
            if($node->value === "\n") return "'\\n'";

            return '`' . $this->pNoIndent(addcslashes($node->value, '`\\')) . '`';
        }

        return parent::pScalar_String($node);
    }

    public function pExpr_BinaryOp_Concat(BinaryOp\Concat $node) {
        return $this->pInfixOp('Expr_BinaryOp_Concat', $node->left, ' + ', $node->right);
    }

    public function pParam(Node\Param $node) {
        return /*($node->type ? $this->pType($node->type) . ' ' : '')
             . ($node->byRef ? '&' : '')
             . ($node->variadic ? '...' : '')
             . '$' . */$node->name
             . ($node->default ? ' = ' . $this->p($node->default) : '');
    }

    public function pExpr_PropertyFetch(Expr\PropertyFetch $node) {
        return $this->pVarOrNewExpr($node->var) . '.' . $this->pObjectProperty($node->name);
    }

    public function pStmt_ClassMethod(Stmt\ClassMethod $node) {

        $methodname = $node->name;
        if($node->name === '__construct') {
            $methodname = 'constructor';
        }

        return $this->pModifiers($node->type)
             . /*'function ' . ($node->byRef ? '&' : '') . */$methodname
             . '(' . $this->pCommaSeparated($node->params) . ')'
             . (null !== $node->returnType ? ' : ' . $this->pType($node->returnType) : '')
             . (null !== $node->stmts
                ? "\n" . '{' . $this->pStmts($node->stmts) . "\n" . '}'
                : ';');
    }

    public function pModifiers($modifiers) {
        return /*($modifiers & Stmt\Class_::MODIFIER_PUBLIC    ? 'public '    : '')
             . ($modifiers & Stmt\Class_::MODIFIER_PROTECTED ? 'protected ' : '')
             . ($modifiers & Stmt\Class_::MODIFIER_PRIVATE   ? 'private '   : '')
             . */($modifiers & Stmt\Class_::MODIFIER_STATIC    ? 'static '    : '')/*
             . ($modifiers & Stmt\Class_::MODIFIER_ABSTRACT  ? 'abstract '  : '')
             . ($modifiers & Stmt\Class_::MODIFIER_FINAL     ? 'final '     : '')*/;
    }

    public function pName_FullyQualified(Name\FullyQualified $node) {
        return /*'\\' . */implode('_', $node->parts);
    }

    protected function pComments(array $comments) {
        $result = '';

        foreach ($comments as $comment) {
            $text = $comment->getReformattedText();
            if($text{0} === "#") {
                $text = "//" . substr($text, 1);
            }

            $result .= $text . "\n";
        }

        return $result;
    }

    public function pExpr_MethodCall(Expr\MethodCall $node) {
        return $this->pVarOrNewExpr($node->var) . '.' . $this->pObjectProperty($node->name)
             . '(' . $this->pCommaSeparated($node->args) . ')';
    }

    public function pExpr_Array(Expr\Array_ $node) {
        return '[' . $this->pCommaSeparated($node->items) . ']';
    }

    /*public function pExpr_Instanceof(Expr\Instanceof_ $node) {
        return $this->pInfixOp('Expr_Instanceof', $node->expr, ' instanceof ', $node->class);
    }*/
}
