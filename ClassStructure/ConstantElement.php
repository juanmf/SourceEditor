<?php

namespace DocDigital\Lib\SourceEditor\ClassStructure;

use DocDigital\Lib\SourceEditor\ElementBuilder;

/**
 * Constants definition
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
 * @author Juan Manuel Fernandez <juanmf@gmail.com>
 * @see \DocDigital\Lib\SourceEditor\PhpClassEditor
 * @see Element
 */
class ConstantElement extends Element
{

    /**
     * const NAME
     * 
     * @var string
     */
    private $name;
    
    /**
     * the containing class
     * 
     * @var ClassElement
     */
    private $parentClass;
    
    /**
     * Intializes Parent Class.
     * 
     * If you don't pass a parent class, precedence issues can come up, if you don't 
     * add this method to a parent class first {@link ClassElement::addConst()}, 
     * when adding elements with {@self::addElement()} as the elements get forwarded 
     * to parenClass.
     * 
     * @param ClassElement $parentClass The parent Class.
     * @param string $code The line of code containing the "const THE_CONST = ... ;" code
     */
    public function __construct(ClassElement $parentClass = null, $code = null)
    {
        $this->parentClass = $parentClass;
        if (null !== $code) {
            $this->populateConstant($code);
        }
    }
    
    /**
     * creates a Constant to add to the Classelement representing the editing class.
     * 
     * @param string $code The line of code containing the "const THE_CONST = ... ;" code
     * 
     * @return ConstantElement The Constant to add
     */
    private function populateConstant($code)
    {
        $element = new ElementBuilder($code . "\n");
        $this->addBodyElement($element);
        $this->setName($element->tokens[3][1]);
    }
    
    /**
     * Returns const name 
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * sets const name.
     * 
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
    
    /**
     * 
     * @param boolean $raw
     * 
     * @return type
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
                "\n    %s\n%s", 
                implode("", $this->docBlock), 
                implode("", $this->body)
            );
        }
        return $out;
    }   
    
    /**
     * Forwards the element addition to the class
     * 
     * @param ElementBuilder $element
     */
    public function addElement(ElementBuilder $element)
    {
        $this->parentClass->addElement($element);
        parent::addElement($element);
    }
    
    /**
     * returns the containing Class
     * 
     * @return ClassElement the containing Class
     */
    public function getParentClass()
    {
        return $this->parentClass;
    }

    /**
     * sets the containing Class
     * 
     * @param ClassElement $parentClass
     */
    public function setParentClass(ClassElement $parentClass)
    {
        $this->parentClass = $parentClass;
    }
}
