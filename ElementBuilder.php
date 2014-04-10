<?php

namespace DocDigital\Lib\SourceEditor;

/**
 * Buffers every token related to a single, relevant code element.
 * e.g.<pre>
 *  * use Namespace\Class;
 *  * namespace DocDigital\Lib\SourceEditor;
 *  * public $var = null;
 *</pre>
 * 
 * @author Juan Manuel Fernandez <juanmf@gmail.com>
 */
class ElementBuilder
{
    const TYPE_GAP                     = 'gap';
    const TYPE_START_CONTEXT_END_TOKEN = '{{';
    const TYPE_END_CONTEXT_TOKEN       = '}}';
    
    /**
     * Token Collector
     * 
     * @var array
     */
    public $tokens = array();
    
    /**
     * index at main Token array where the code element starts
     * 
     * @var int
     */
    public $startPointer;
    
    /**
     * index at main Token array where the code element ends
     * 
     * @var int
     */
    public $endPointer;
    
    /**
     * TokenParser::CONTEXT_* . '~' . StartToken
     * where StartToken is one of the TokenParser::$ElementStartFlags[TokenParser::CONTEXT_*]
     * tokens 
     * 
     * @var string
     * @see TokenParser::_figureOutElementType()
     */
    public $elementType;
    
    /**
     * TokenParser::CONTEXT_* of the current parsing element.
     * 
     * @var string 
     */
    public $context;
    
    /**
     * Generate tokens for given code.
     * 
     * @param type $code
     */
    public function __construct($code = null, $type = null, $context = null) {
        if (null === $code) {
            return;
        }
        $this->tokens = token_get_all(sprintf("<?php %s", $code));
        // Removing <?php 
        unset($this->tokens[0]);
        // this unset() leaves a gap that could cause undefined offset 0, redressing.
        $this->tokens = array_values($this->tokens);
        $this->startPointer = -1;
        $this->elementType = $type;
        $this->context = $context;
    }
    
    /**
     * Render concatenated token values.
     * 
     * @return string The tokens concatenated.
     */
    public function __toString()
    {
        $out = '';
        foreach ($this->tokens as $t) {
            if (is_scalar($t)) {
                $out .= $t;
            } else {
                $out .= $t[1];
            }
        }
        return $out;
    }
}
