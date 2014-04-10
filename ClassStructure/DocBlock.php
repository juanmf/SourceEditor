<?php

namespace DocDigital\Lib\SourceEditor\ClassStructure;

use \DocDigital\Lib\SourceEditor\ElementBuilder;

/**
 * Description of DocBlock 
 * (fisrt time Netbeans IDE documents a calss for me ;P)
 *
 * @author Juan Manuel Fernandez <juanmf@gmail.com>
 * @see Element
 */
class DocBlock extends Element
{
    /**
     * Contains the DocBlock Token
     * 
     * @var ElementBuilder
     */
    private $elementBuilder;

    /**
     * Splits DocBlock lines, or initializes DocBlock if empty
     * 
     * @param string|ElementBuilder  $docText Either Doc Text or a token which $t[0] === T_DOC_COMMENT
     */
    public function __construct($docText = null, $spaces = 4)
    {
        switch (true) {
            case (null === $docText): 
            case is_string($docText): 
                $docText = new ElementBuilder($docText); 
                break;
            case ($docText instanceof ElementBuilder): 
                break;
            default:
                throw new \InvalidArgumentException(
                    sprintf('$docText should be either string|ElementBuilder. %s given', $docText)
                );
        }
        $this->elementBuilder = $docText;

        if (empty($docText->tokens)) {
            $s = $spaces + 1;
            $text = sprintf(
                    "/**\n%s*\n%s*/", 
                    str_repeat(' ', $s), str_repeat(' ', $s)
                );
            // fake token.
            $docText->tokens[] = array(T_DOC_COMMENT, $text, 1);
        }
    }
        
    /**
     * Adds an Annotation to this DocBlock
     * 
     * @param string $text The annotation text e.g. @ ORM\Column(name="id", type="integer")
     * 
     * @return void.
     */
    public function addAnnotation($text, $spaces = 0)
    {
        $lines = explode("\n", $this->elementBuilder->tokens[0][1]);
        $closing = array_pop($lines);
        $lines[] = sprintf('%s * %s', str_repeat(' ', $spaces), $text);
        $lines[] = $closing;
        $this->elementBuilder->tokens[0][1] = implode("\n", $lines);
    }

    /**
     * Renders this DocBlock
     */
    public function render($raw = true)
    {
        return $this->elementBuilder->tokens[0][1];
    }
}
