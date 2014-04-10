<?php

namespace DocDigital\Lib\SourceEditor\ClassStructure;

use \DocDigital\Lib\SourceEditor\ElementBuilder;
use \DocDigital\Lib\SourceEditor\TokenParser;

/**
 * Attribute Definition
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
class AttributeElement extends Element
{
    
    /**
     * Attr $name
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
     * add this method to a parent class first {@link ClassElement::addAttribute()}, 
     * when adding elements with {@self::addElement()} as the elements get forwarded 
     * to parenClass.
     * 
     * @param ClassElement $parentClass The parent Class.
     * @param string       $code        The literal PHP code to convert to this instance.
     */
    public function __construct(ClassElement $parentClass = null, $code = null)
    {
        $this->parentClass = $parentClass;
        if (null !== $code) {
            $this->populateAttribute($code);
        }
    }
    
    /**
     * creates a ElementBuilder for this Attribute with given literal PHP code.
     * 
     * @param string $code The lines of code containing the attribute code
     * 
     * @return ConstantElement The Constant to add
     */
    private function populateAttribute($code)
    {
        $element = new ElementBuilder($code . "\n\n");
        $this->addBodyElement($element);
        $this->setName(TokenParser::staticFindElementName($element));
    }
    
    /**
     * Returns Attr name 
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * sets Attr name.
     * 
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
    
    public function render($raw = true)
    {
        $out = '';
        if ($raw) {
            foreach ($this->elements as $element) {
                $out .= (string) $element;
            }
        } else {
            $out = sprintf(
                "\n    %s\n    %s", 
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
        parent::addElement($element);
        $this->parentClass->addElement($element);
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
