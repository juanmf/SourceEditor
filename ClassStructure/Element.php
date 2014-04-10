<?php

namespace DocDigital\Lib\SourceEditor\ClassStructure;

use \DocDigital\Lib\SourceEditor\ElementBuilder;

/**
 * Base class For Class Structure Composite Pattern:
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
 */
abstract class Element
{
    /**
     * This Element's /** DocBlock * /. might be one or more
     * @var DocBlock[]
     */
    protected $docBlock = array();
    
    /**
     * Flat array of elements should keep the order found in original class file.
     * If used, this array will be used for rendering, instead of the structured one. 
     * 
     * @var ElementBuilder[] 
     */
    protected $elements = array();
    
    /**
     * Element Builders that constitute the Element. 
     * 
     * @var ElementBuilder[]
     */
    protected $body = array();
    
    /**
     * Sets the body of this element
     * 
     * @param ElementBuilder[] $elements
     */
    public function setBody(array $elements)
    {
        foreach ($elements as $e) {
            $this->addBodyElement($e);
        }
    }
    
    /**
     * Sets the body of this element
     * 
     * @param ElementBuilder[] $element
     */
    public function addBodyElement(ElementBuilder $element)
    {
        $this->addElement($element);
        $this->body[] = $element;
    }
    
    /**
     * returns this Elemsnt's body
     * 
     * @return ElementBuilder
     */
    public function getBody()
    {
        return $this->body;
    }
    
    /**
     * Adds an Annotation to this element's DocBlock
     * 
     * @param string $text The annotation text e.g. @ ORM\Column(name="id", type="integer")
     * 
     * @return void.
     */
    public function addAnnotation($text, $spaces = 0)
    {
        end($this->docBlock)->addAnnotation($text, $spaces);
    }
    
    /**
     * Returns this element's DocBlock array.
     * 
     * @return DocBlock[]
     */
    public function getDocBlock()
    {
        return $this->docBlock;
    }
    
    /**
     * Sets this element's DocBlock array.
     * 
     * @param DocBlock[]
     */
    public function setDocBlock($docBlock)
    {
        $this->docBlock = $docBlock;
    }
    
    /**
     * Several DocBlock elements might precede an attribute, class or method.
     * 
     * @param type $element
     */
    public function addDocBlock($docBlock = null)
    {
        ! ($docBlock instanceof DocBlock) && $docBlock = new DocBlock($docBlock);
        $this->docBlock[] = $docBlock;
    }
    
    /**
     * Adds mixed elements to flat elements array. If this array gets filled.
     * rendering the class will respect original disposition of elements. 
     * 
     * Be ware that you need to call this for every typed element you add, i.e.
     * after calling any of the set* or add
     * 
     * @param ElementBuilder $element
     */
    public function addElement(ElementBuilder $element)
    {
        // addElementBody() Forwards to this method. if user also calls addElement, there 
        // are dupplicated elements.
        $repeated = array_filter(
            $this->elements, 
            function($e) use ($element) {
                return $e === $element;
            }
        );
        if (empty($repeated)) {
            $this->elements[] = $element;
        }
    }
    
    /**
     * Renders this Class element's source code.
     * 
     * @param bool $raw If true renders ElementBuilder present in flat array of elements,
     * respecting gaps, spaces and non Doc comments (//). if fasle, renders in order.
     * 
     * @return string Element's code
     */
    abstract public function render($raw = true);
    
    /**
     * Renders Element's code
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }
}
