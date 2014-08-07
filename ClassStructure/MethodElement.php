<?php

namespace DocDigital\Lib\SourceEditor\ClassStructure;

use DocDigital\Lib\SourceEditor\ElementBuilder;

/**
 * Methods Definition:
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
class MethodElement extends Element
{
    /**
     * method NAME
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
     * method NAME
     * 
     * @var ElementBuilder
     */
    private $signature;
    
    /**
     * Returns method name 
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * sets method name.
     * 
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
    
    /**
     * The Method Code
     * @var array 
     */
    private $code;
            
            
    /**
     * Intializes Parent Class.
     * 
     * If you don't pass a parent class, precedence issues can come up, if you don't 
     * add this method to a parent class first {@link ClassElement::addConst()}, 
     * when adding elements with {@self::addElement()} as the elements get forwarded 
     * to parenClass.
     * 
     * @param ClassElement $parentClass The parent Class.
     */
    public function __construct(ClassElement $parentClass = null)
    {
        $this->parentClass = $parentClass;
    }
    
    /**
     * Renders this method as string.
     * 
     * @return string
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
                "\n    %s\n    %s{%s}\n", 
                implode("", $this->docBlock), 
                $this->signature,  
                implode("", $this->body)
            );
        }
        return $out;
    }
    
    /**
     * Sets this method signature
     * 
     * @param ElementBuilder $e
     */
    public function setSignature(ElementBuilder $e)
    {
        $this->signature = $e;
    }
    /**
     * Gets this method signature.
     * 
     * @return type
     */
    public function getSignature()
    {
        return $this->signature;
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
