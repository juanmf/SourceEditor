<?php

namespace DocDigital\Lib\SourceEditor\ClassStructure;

use DocDigital\Lib\SourceEditor\ElementBuilder;

/**
 * Trait definition
 * <pre>
 * <elemnet class>
 *     <element attribute>
 *     </element >
 * </elemnet>
 * </pre>
 *
 * @author Juan Manuel Fernandez <juanmf@gmail.com>
 * @author Adam Misiorny <adam.misiorny@gmail.com>
 * @see \DocDigital\Lib\SourceEditor\PhpClassEditor
 * @see Element
 */
class TraitElement extends Element
{

    /**
     * trait NAME
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
     * add this method to a parent class first {@link ClassElement::addTrait()},
     * when adding elements with {@self::addElement()} as the elements get forwarded
     * to parenClass.
     *
     * @param ClassElement $parentClass The parent Class.
     * @param string $code The line of code containing the "use Abc;" code
     */
    public function __construct(ClassElement $parentClass = null, $code = null)
    {
        $this->parentClass = $parentClass;
        if (null !== $code) {
            $this->populateTrait($code);
        }
    }

    /**
     * creates a Trait to add to the Classelement representing the editing class.
     *
     * @param string $code The line of code containing the "use Abc;" code
     *
     * @return TraitElement The Trait to add
     */
    private function populateTrait($code)
    {
        $element = new ElementBuilder($code . "\n");
        $this->addBodyElement($element);
        $this->setName($element->tokens[3][1]);
    }

    /**
     * Returns trait name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * sets trait name.
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
                "%s",
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
