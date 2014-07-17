<?php

namespace DocDigital\Lib\SourceEditor\ClassStructure;

/**
 * Class Element Definition:
 * <pre>
 * <elemnet class>
 *     <docBlock/>
 *     <element attribute>
 *         <docBlock/>
 *     </element >
 *     <element method>
 *         <docBlock/>
 *     </element >
 * </elemnet> 
 * </pre>
 * 
 * Be ware that the {@link self::render()} with $raw set to true is useful as long as you don't add
 * new elements to any collection, as the added element is not inserted in the flat
 * elements collection thats used in the raw strategy. I.e. if you invoke {@link self::addUse()}
 * for instance, this use statement wont be rendered in raw mode. But editing an existing
 * docBlock will have impact in raw render.
 *
 * @author Juan Manuel Fernandez <juanmf@gmail.com>
 * @see \DocDigital\Lib\SourceEditor\PhpClassEditor
 * @see Element
 */
class ClassElement extends Element 
{
    /**
     * Class Name
     * 
     * @var string 
     */
    private $name;
    
    /**
     * This node contains other nodes:
     *  constants.
     * 
     * @var string[]
     */
    private $traits = array();

    /**
     * This node contains other nodes:
     *  constants.
     * 
     * @var Element[]
     */
    private $constants = array();
    
    /**
     * This node contains other nodes:
     *   attributes.
     * 
     * @var Element[]
     */
    private $attributes = array();
    
    /**
     * This node contains other nodes:
     *  Methods.
     * 
     * @var Element[]
     */
    private $methods = array();
    
    /**
     * Class Use statements, included namespaces 
     * 
     * @var string[]
     */
    private $classDeps;
    
    /**
     * Single Line class parts
     * @var string 
     */
    private $classDefLine;
    private $namespace;
    
    /**
     * Namespace
     * 
     * @param type $nameSpaceLine
     */
    public function setNameSpace($nameSpaceLine)
    {
        $this->namespace = $nameSpaceLine;
    }
    
    /**
     * Dependencies
     * 
     * @param type $use
     */
    public function addUse($use)
    {
        $this->classDeps[] = $use;
    }
    
    /**
     * Traits
     *
     * @param type $trait
     */
    public function addTrait($trait)
    {
        if (! ($trait instanceof TraitElement)) {
            $trait = new TraitElement($this, $trait);
        }
        ($trait->getParentClass() !== $this) && $trait->setParentClass($this);
        $this->traits[] = $trait;
    }

    /**
     * Add DockBlock
     * 
     * @param DocBlock $docBlock
     */
    public function setClassDef($classLine)
    {
        $this->classDefLine = $classLine;
    }
    
    /**
     * Adds a Method definition to this class representation
     * 
     * @param MethodElement $method
     * 
     * @return void.
     */
    public function addMethod(MethodElement $method, $docBlock = null)
    {
        ($method->getParentClass() !== $this) && $method->setParentClass($this);
        $this->methods[$method->getName()] = $method; 
    }

    /**
     * Adds an Attribute definition to this class representation
     * 
     * @param AttributeElement|string $attr Either the literal PHP code or an 
     * AttributeElement object.
     * 
     * @return void.
     */
    public function addAttribute($attr, $docBlock = null)
    {
        if (! ($attr instanceof AttributeElement)) {
            $attr = new AttributeElement($this, $attr);
            $attr->addDocBlock($docBlock);
        }
        ($attr->getParentClass() !== $this) && $attr->setParentClass($this);
        $this->attributes[$attr->getName()] = $attr;
    }

    /**
     * Adds a Const definition to this class representation, const could be provided also 
     * as a string containing the line of source code that holds the "const ... ;" code.
     * In this case $name should be given.
     * 
     * @param ConstantElement|string $const The ConstElement ready for adition or 
     * the const line of code;
     * 
     * @return void.
     */
    public function addConst($const, $docBlock = null)
    {
        if (! ($const instanceof ConstantElement)) {
            $const = new ConstantElement($this, $const);
            $const->addDocBlock($docBlock);
        }
        ($const->getParentClass() !== $this) && $const->setParentClass($this);
        $this->constants[] = $const; 
    }
    
    /**
     * Returns the method definiton after $name
     * 
     * @param string $name
     * 
     * @return MethodElement The requested method definition
     */
    public function getMethod($name)
    {
        if (! isset($this->methods[$name])) {
            return null;
        }
        return $this->methods[$name];
    }
    
    /**
     * Returns the attribute definiton after $name
     * 
     * @param string $name
     * 
     * @return AttributeElement The requested method definition
     */
    public function getAttribute($name)
    {
        if (! isset($this->attributes[$name])) {
            return null;
        }
        return $this->attributes[$name];
    }
    
    /**
     * Returns class name 
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * sets class name.
     * 
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
    
    /**
     * Renders this entire class.
     * 
     * Be ware that the {@link self::render()} with $raw set to true is useful as long as you don't add
     * new elements to any collection
     * 
     * @param bool $raw If true renders ElementBuilder present in flat array of elements,
     * respecting gaps, spaces and non Doc comments (//). if fasle, renders in order.
     *
     * @return string The Class Code.
     */
    public function render($raw = true)
    {
        $out = '';
        if ($raw) {
            foreach ($this->elements as $element) {
                $out .= (string) $element;
            }
        } else {
            $out = sprintf(
                "<?php\n\n%s\n\n%s\n\n%s\n%s{\n%s\n}\n",
                $this->namespace, implode("\n", array_unique($this->classDeps)),
                implode('', $this->docBlock), $this->classDefLine, $this->renderBody()
            );
        }
        return $out;
    }

    /**
     * Renders the class body. If it gets to this point, then its not a raw render.
     */
    private function renderBody() 
    {
        $traits = $this->renderTraits();
        $consts = $this->renderConstants();
        $attrs = $this->renderAttributes();
        $methods = $this->renderMethods();
        return implode("", array($traits, $consts, $attrs, $methods));
    }

    /**
     * renders every Element of the given Element[] array en $raw mode.
     * 
     * @param array $elements
     */
    private function renderAll(array &$elements, $raw = true)
    {
        while($e = each($elements)) {
            $elements[$e['key']] = $e['value']->render($raw);
        }
        reset($elements);
    }
    
    /**
     * Render Traits
     */
    private function renderTraits()
    {
        $this->renderAll($this->traits, false);
        return implode('', $this->traits);
    }

    /**
     * Render Constants
     */
    private function renderConstants()
    {
        $this->renderAll($this->constants, false);
        return implode('', $this->constants);
    }

    /**
     * Render Attributes
     */
    private function renderAttributes()
    {
        $this->renderAll($this->attributes, false);
        return implode('', $this->attributes);
    }

    /**
     * Render Methods
     */
    private function renderMethods()
    {
        $this->renderAll($this->methods, false);
        return implode('', $this->methods);
    }
}
