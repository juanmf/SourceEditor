<?php

namespace DocDigital\Lib\SourceEditor;

/**
 * Handles Token clasification, code {@link ElementBuilder} creation and code context/scope
 * changes detection. Each ElementBuilder will contain either a significant code part or gap code,
 * like T_WHITESPACE or unclassified code, like method body (as currently not inspecting inside method).
 * 
 * Every time an ElementBuilder is closed, or a context changes (which also closes an ElementBuilder)
 * it gets forwarded to a couple of callback closures given by client code:<pre>
 *    {@link self::$contextChangeClosure}
 *    {@link self::$processElementClosure}
 *</pre>
 * Which are passed as parameters to {@link self::setSource()}
 * 
 * The basic sequence is:<pre>
 * +------------+  +--------------+
 * | :ClientObj |  | :TokenParser |
 * +------------+  +--------------+
 *      |              |
 *      |--setSource-->|
 *      |--parseCode-->|--readToken--+[forEach Token, this iteration changes context as token is a contextStart]
 *      |              ||<-----------+
 *      |              ||--read<CONTEXT>Token--+
 *      |              |||<--------------------+
 *      |              |||--_checkContextChange--+
 *      |              ||||<---------------------+
 *      |              ||||--_isContextStart--+
 *      |              |||||<-----------------+
 *      |              ||||--_changeContext--+ [if Context changed e.g. class=>method]
 *      |              |||||<----------------+
 *      |              |||||--_startNewElement--+
 *      |              ||||||<------------------+
 *      |              ||||||------------------------------+
 *      ||<--$processElementClosure(self::elementBuilder)--+
 *      |              |||||
 *      |              |||||------------------------------------------------------------+
 *      ||<--$contextChangeClosure($newContext, $currentContext, self::elementBuilder)--+
 *      |              |||||
 *      |              |||||--readToken--+ [reReads Token without increasing {@link self::pointer}]
 *      |              ||||||<-----------+
 *      |              |
 *      |              |[continue Looping forEach Token at parseCode, now reads a token that doesn't change context]
 *      |              |
 *      |              |--readToken--+[forEach Token]
 *      |              ||<-----------+
 *      |              ||--read<CONTEXT>Token--+
 *      |              |||<--------------------+
 *      |              |||--_checkContextChange--+
 *      |              ||||<---------------------+
 *      |              ||||--_isContextStart--+
 *      |              |||||<-----------------+
 *      |              ||||--_isContextEnd--+ [Called only if _isContextStart returns false, also 
 *      |              |||||<---------------+      returns false for this token]
 *      |              |||
 *      |              |||--_loadTokenInElement--+ [context didn't change, add token to ElementBuilder]
 *      |              ||||<---------------------+
 *      |              ||||--_startNewElement--+   [Only if token is a delimiter Flag that closes current 
 *      |              |||||<------------------+       self::elementBuilder again calling $processElementClosure]
 *      |              |
 *      |              |[continue Looping forEach Token at parseCode, now reads a token that doesn't change context]
 *      |              |
 * 
 * @author Juan Manuel Fernandez <juanmf@gmail.com>
 */
class TokenParser
{
    /**
     * Expected Element Openning tokens per Context:
     * <pre> 
     * File:
     *     <?php
     * PHP:
     *     expected: exit, if, integer, double, identifier, STRING_VARNAME, variable, 
     *     inline html, String, echo, do, while, for, foreach, declare, clone, switch, 
     *     break, continue, goto, function, const, return, try, throw, use, global, unset, 
     *     isset, empty, __halt_compiler, class, interface, list, array, __CLASS__, 
     *     __METHOD__, __FUNCTION__, __LINE__, __FILE__, <<<"...", {, }, namespace, __NAMESPACE__, 
     *     __DIR__, \, VAR_COMMENT, define, include, include_once, eval, require, require_once, 
     *     print, ;, +, -, !, ~, ++, --, (int), (double), (string), (array), (object), 
     *     (bool), (unset), @, [, new, static, abstract, final, (, $, '"', '`', <<<'...', trait
     * Class:
     *     expected:function, const, use, var, }, VAR_COMMENT, static, abstract, final, private, 
     *     protected, public
     * method:
     *     expected:	exit, if, integer, double, identifier, STRING_VARNAME, variable, 
     *     inline html, String, echo, do, while, for, foreach, declare, clone, switch, 
     *     break, continue, goto, function, return, try, throw, use, global, unset, 
     *     isset, empty, class, interface, list, array, __CLASS__, __METHOD__,
     *     __FUNCTION__, __LINE__, __FILE__, <<<"...", {, }, namespace, __NAMESPACE__,
     *     __DIR__, \, VAR_COMMENT, define, include, include_once, eval, require, 
     *     require_once, print, ;, +, -, !, ~, ++, --, (int), (double), (string), (array), 
     *     (object), (bool), (unset), @, [, new, static, abstract, final, (, $, '"', 
     *     '`', <<<'...', trait
     * </pre>
     */
    const CONTEXT_FILE   = 'file';
    const CONTEXT_PHP    = 'file_inside_php_tag';
    const CONTEXT_CLASS  = 'class';
    const CONTEXT_METHOD = 'method';

    /**
     * This is not a context start flag, but context's elements start flag. e.g.
     * Inside a Class context, class is not a start flag, since PHP does not support
     * inner classes.
     *  
     * @var array 
     */
    private $elementStartFlags = array(
        self::CONTEXT_PHP    => array(T_NAMESPACE, T_USE, T_DOC_COMMENT),
        self::CONTEXT_CLASS  => array(
            T_PRIVATE, T_PUBLIC, T_PROTECTED, T_CONST, T_DOC_COMMENT,
            T_USE, T_STATIC, T_FINAL, T_VAR,
        ),
        // Currenlty not looking inside methods.
        self::CONTEXT_METHOD => array(),
    );
    
    private $elementEndFlags = array(
        self::CONTEXT_PHP     => array(';', '}'),
        self::CONTEXT_CLASS   => array(';', '}'),
        // Currenlty not looking inside methods.
        self::CONTEXT_METHOD => array(),
    );

    /**
     * In order to generate neat context Starter Elements, this tokens should cut
     * them before they get merged with Gap tokens. Also generated ElementBuilders should 
     * contain only these token, no Gap.
     * 
     * @var array 
     */
    public $contextStartElementEndFlags = array(
        self::CONTEXT_PHP     => array(T_WHITESPACE),
        self::CONTEXT_CLASS   => array('{'),
        // Currenlty not looking inside methods.
        self::CONTEXT_METHOD => array('{'),
    );
    
    /**
     * This are flags that you expect to find in a context, meaning the context is 
     * changing, either to an outer, or an inner one. i.e. class => method 
     * or method => class
     * 
     * @var array 
     */
    private  $contextStartFlags = array(
        self::CONTEXT_PHP    => array(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO),
        self::CONTEXT_CLASS  => array(T_ABSTRACT, T_CLASS, T_FINAL),
        // TODO: the only required one is T_FUNCTION but once found, the function 
        // modifier must be included. T_STATIC, T_PRIVATE, T_PUBLIC might start Attributes Elements too.
        self::CONTEXT_METHOD => array(T_FUNCTION, T_ABSTRACT, T_STATIC, T_PRIVATE, T_PUBLIC, T_PROTECTED, T_FINAL),
    );
    
    private $contextEndFlags = array(
        self::CONTEXT_PHP     => array(T_CLOSE_TAG),
        self::CONTEXT_CLASS   => array('}'),
        // Abstract Methods ends with ';'.
        self::CONTEXT_METHOD => array('}', ';'),
    );
    
    /**
     * Current Context for the reading Token.
     * 
     * @var string
     * @see self::CONTEXT_* 
     */
    private $context = self::CONTEXT_FILE;
    
    /**
     * Source code that gets gathered before a delimiter is found. This is the source
     * of the Element
     * 
     * @var string 
     */
    private $collectedSource = '';
    
    /**
     * Array of Tokens.
     * 
     * @var array
     */
    private $tokens;
    
    /**
     * Current Token index
     * 
     * @var int 
     */
    private $pointer;
    
    /**
     * Count Tokens in Source
     * 
     * @var int
     */
    private $numTokens;
    
    /**
     * Opening relevant scopes stack. tracks the nesting level of a '{' that is openning
     * a relevant scope. {class, method} contexts.
     * 
     * @var int[] 
     */
    private $OpeningCurlyNestingLevel = array();
    
    /**
     * Openning '{' might be opening a context {class, method} or just a control 
     * structure. if {@link self::_isContextStart()} is true, for one of those contexts,
     * the next value of {@link self::$curlyBrace} will be cached in 
     * {@link self::$OpeningCurlyNestingLevel}
     * 
     * @var bool
     */
    private $saveOpeningCurlyNestingLevel;
    
    /**
     * tracks the nesting level of a '{'.
     *
     * @var int 
     */
    private $curlyBrace = 0;
    
    /**
     * When in self::CONTEXT_METHOD, if method is abstract this should be true,
     * otherwise false, used to solve ambiguity for closing method context.
     * 
     * @var bool 
     */
    private $contextMethodIsAbstract = false;
    
    /**
     * When a new element gets created as a result of context Change, this flag
     * turns on, and {@link self::$contextStartElementEndFlags} gets tested for
     * every token since, to close the context change element without gap garbage.
     * 
     * @var bool 
     * @see self::_loadTokenInElement()
     */
    private $startingNewContext = false;
    
    /**
     * When end context flag is reached, this flag helps making a single ElementBuilder
     * that holds only this token.
     * 
     * @var type 
     * @see self::$contextEndFlags
     */
    private $endingContext = false;
    
    /**
     * Buffer for current parsing element's tokens
     * 
     * @var ElementBuilder
     */
    private $elementBuilder;
    
    /**
     * Closure that handles an Element, this gets called whenever a new element starts
     * before overriding self::$elementBuilder. Except for the first time.
     * 
     * @var \Closure 
     */
    private $processElementClosure;
    
    /**
     * Closure that handles an Element, this gets called whenever a new context changes.
     * 
     * @var \Closure 
     */
    private $contextChangeClosure;

    /**
     * Init tokens
     */
    public function setSource($source, $processElementClosure, $processContextChangeClosure)
    {
        $this->tokens = token_get_all($source);
        $this->pointer = 0;
        $this->numTokens = count($this->tokens);
        $this->processElementClosure = $processElementClosure;
        $this->contextChangeClosure = $processContextChangeClosure;
        $this->startNewElement(self::CONTEXT_FILE);
    }

    /**
     * Iterate through tokens by calling {@link self::readToken()} until tokens end.
     */
    public function parseCode()
    {
        while ($this->readToken()) {
        }
    }
    
    /**
     * Receives each token and attempts to find delimiters, when the end of a relevant
     * element is reached, fires an element creation with its source.
     * 
     * @param type $token
     */
    public function readToken($repeatToken = false)
    {
        
        if ($this->pointer === $this->numTokens) {
            return false;
        }
        $token = $this->tokens[$this->pointer];
        if (! $repeatToken) {
            $this->makeTrakings($token);
        }
        try {
            switch ($this->context) {
                case self::CONTEXT_FILE:
                    $this->readFileToken($token);
                    break;
                case self::CONTEXT_PHP:
                    $this->readPhpToken($token);
                    break;
                case self::CONTEXT_CLASS:
                    $this->readClassToken($token);
                    break;
                case self::CONTEXT_METHOD:
                    $this->readMethodToken($token);
                    break;
                default:
                    throw new Exception(sprintf('Invalid Context: %s', $this->context));
                    break;
            }
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        }
        if (! $repeatToken) {
            $this->pointer++;
        }
        return true;
    }
  
    /**
     * No matter the context some Tokens must be tracked to be aware for instance
     * of scope nesting levels we are runnign in. This is crusial to know if '}' is
     * endinf a Class or Method, or is something else.
     * 
     * @param array $token
     */
    private function makeTrakings($token)
    {
        switch ($token[0]) {
            case '{':
                if ($this->saveOpeningCurlyNestingLevel) {
                    $this->saveOpeningCurlyNestingLevel = false;
                    $this->OpeningCurlyNestingLevel[] = $this->curlyBrace;
                }
                $this->curlyBrace++;
                break;
            case '}':
                $this->curlyBrace--;
                break;
            case T_ABSTRACT: 
                if (self::CONTEXT_CLASS === $this->context || self::CONTEXT_METHOD === $this->context) {
                    /* If context is method and [public,private,protected] was 1st then 
                     * context changed before T_ABSTRACT, that never happens for Classes.
                     * So either calss or method, this T_ABSTRACT is a method's one.
                     */
                    $this->contextMethodIsAbstract = true;
                }
                break;
            default:
                break;
        }
    }
    
    /**
     * Each Context token reader must first check that this token falls inside current 
     * context or triggers a context change.
     * 
     * @param array|string $token          The current Token
     * @param string       $childContext   One of the {@link self::ContextStartFlags} keys.
     * i.e. one of the self::CONTEXT_* constants. Defaults to null, as InnerMost context
     * does not have a ContextStartFlags key.
     * @param string       $currentContext One of the {@link self::ContextEndFlags} keys
     * i.e. one of the self::CONTEXT_* constants. Defaults to null, as OuterMost context
     * does not have a ContextEndFlags key.
     * 
     * @return bool TRUE if context changed.
     */
    private function checkContextChange($token, $childContext = null, $currentContext = null, $parentContext = null)
    {
        $changed = false;
        $t = $token[0];
        if (null !== $childContext && ! $this->startingNewContext && $this->isContextStart($childContext, $t)) {
            $this->startingNewContext = true;
            $this->changeContext($childContext);
            $changed = true;
        } else if (null !== $currentContext && ! $this->endingContext && $this->isContextEnd($currentContext, $t)) {
            $this->endingContext = true;
            $this->changeContext($parentContext, false, ElementBuilder::TYPE_END_CONTEXT_TOKEN);
            $changed = true;
        }
        return $changed;
    }
    
    /**
     * Ensures that the current Token changes to outer Context.
     * 
     * @param string $context
     * @param string $token
     * 
     * @return bool TRUE if must change context.
     */
    private function isContextEnd($context, $token)
    {
        $solveAmbiguity = function($token) {return true;};
        if (self::CONTEXT_CLASS === $context 
            || (self::CONTEXT_METHOD === $context && ! $this->contextMethodIsAbstract)) {
            $solveAmbiguity = function($token) {
                if ($this->curlyBrace === end($this->OpeningCurlyNestingLevel)) {
                    array_pop($this->OpeningCurlyNestingLevel);
                    return true;
                }
                return false;
            };
        } else if (self::CONTEXT_METHOD === $context && $this->contextMethodIsAbstract) {
            // We can only find ';' and its an abstract method closing one
            $solveAmbiguity = function($token) {
                $this->contextMethodIsAbstract = false; 
                return true;
            };
        
        }
        return in_array($token, $this->contextEndFlags[$context]) && $solveAmbiguity($token);
    }
    
    /**
     * Ensures that the current Token changes to inner Context, solving token ambiguity when 
     * possible.
     * 
     * @param string $context
     * @param string $token
     * 
     * @return bool TRUE if must change context.
     */
    private function isContextStart($context, $token)
    {
        $solveAmbiguity = function($token) {return true;};
        if (self::CONTEXT_METHOD === $context) {
            $solveAmbiguity = function($token) {
                $ambiguous = array(T_STATIC, T_PRIVATE, T_PUBLIC, T_PROTECTED);
                if (in_array($token, $ambiguous)) {
                    if (T_VARIABLE === $this->lookAhead()[0]) {
                        // After [T_STATIC, T_PRIVATE, T_PUBLIC] we found an attribute.
                        return false;
                    }
                }
                return true;
            };
        }
        $change = in_array($token, $this->contextStartFlags[$context]) && $solveAmbiguity($token);
        if ($change && (self::CONTEXT_METHOD === $context || self::CONTEXT_CLASS === $context)) {
            $this->saveOpeningCurlyNestingLevel = true;
        }
        return $change;
    }
    
    /**
     * Changes context and reRead $token. Also invokes {@link self::$contextChangeClosure} 
     * For client Code context hange handling.
     * 
     * This Token is not buffered yet, first context gets changed and token is reprocessed.
     * Client code must wait or {@link self::lookAhead()}, to find the complete context header.
     * 
     * @param string $newContext     The new context found.
     * @param bool   $changeBefore   Optional. Change context before (true) or after (false) 
     * starting new element {@link self::_startNewElement()}. Defaults to true.
     * @param string $elementType    Optional, override the default element Type generation
     * i.e. {@link self::figureOutElementType($newContext, $currentContext)}.
     */
    private function changeContext($newContext, $changeBefore = true, $elementType = null)
    {
        /** 
         * this new element will contain the context header [function, class, ..] 
         * until next {@link self::$contextStartElementEndFlags}'s current context flag, if openning.
         * Or de right {@link self::$contextEndFlags} for current Context if closing.
         */
        $currentContext = $this->context;
        
        $changeBefore && $this->context = $newContext;
        $this->startNewElement($elementType ? : $this->figureOutElementType($newContext, $currentContext));
        ! $changeBefore && $this->context = $newContext;
        
        $fn = $this->contextChangeClosure;
        // Sending no Tokens in $this->elementBuilder, but with useful startPointer
        $fn($newContext, $currentContext, $this->elementBuilder);
        $this->readToken(true);
    }
          
    /**
     * Gets the next non whitespace and non comment token. Without mooving internal 
     * tokens {@ link self::pointer}
     *
     * @param $docCommentIsComment
     *     If TRUE then a doc comment is considered a comment and skipped.
     *     If FALSE then only whitespace and normal comments are skipped.
     *
     * @return array The token if exists, null otherwise.
     */
    public function lookAhead($docCommentIsComment = TRUE, $alternativePointer = null)
    {
        $pointer = (null === $alternativePointer) ? $this->pointer + 1 : $alternativePointer;
        for ($i = $pointer; $i < $this->numTokens; $i++) {
            if ($this->tokens[$i][0] === T_WHITESPACE
                || $this->tokens[$i][0] === T_COMMENT
                || ($docCommentIsComment && $this->tokens[$i][0] === T_DOC_COMMENT)
            ) {
                continue;
            }
            return $this->tokens[$i];
        }
        return null;
    }
    
    /**
     * Looks ahead to find attribute name. Assume we are in class context.
     * 
     * @param ElementBuilder $elementBuilder The ElementBuilder that holds the pointer of the
     * openning element delimiter token.
     * 
     * @return string The Literal value of the token containint the variabnle name.
     */
    public function findElementName(ElementBuilder $elementBuilder) 
    {
        $start = $elementBuilder->startPointer;
        while($token = $this->lookAhead(true, $start++)) {
            $tValue = is_array($token)? $token[1]: $token;
            if (in_array($token[0], array(T_STRING, T_VARIABLE))) {
                break;
            }
        }
        return $tValue;
    }
    
    /**
     * Same as {@link findElementName()} but in non object context. Uses only elementBuilder
     * tokens
     * 
     * @param ElementBuilder $elementBuilder The ElementBuilder with tokens.
     * 
     * @return string The Literal value of the token containint the variabnle name.
     */
    public static function staticFindElementName(ElementBuilder $elementBuilder)
    {
        $start = 0;
        while($token = static::staticLookAhead($elementBuilder, true, $start++)) {
            $tValue = is_array($token)? $token[1]: $token;
            if (in_array($token[0], array(T_STRING, T_VARIABLE))) {
                break;
            }
        }
        return $tValue;
    }

    /**
     * Like {@link self::lookAhead()} but in static Context, as there is no $this->tokens
     * uses tokens in given ElementBuilder.
     * 
     * @param ElementBuilder $elementBuilder      The ElementBuilder with tokens.
     * @param boolean        $docCommentIsComment true if skip DocBlock.
     * @param int            $pointer             position to start looking ahead from.
     * 
     * @return null|array The token or null.
     */
    public static function staticLookAhead(
        ElementBuilder $elementBuilder, $docCommentIsComment = true, $pointer = null
    ) {
        for ($i = $pointer; $i < count($elementBuilder->tokens); $i++) {
            if ($elementBuilder->tokens[$i][0] === T_WHITESPACE
                || $elementBuilder->tokens[$i][0] === T_COMMENT
                || ($docCommentIsComment && $elementBuilder->tokens[$i][0] === T_DOC_COMMENT)
            ) {
                continue;
            }
            return $elementBuilder->tokens[$i];
        }
        return null;
    }
    
    /**
     * Process Token knowing we are in File context, outside tags <?php ?>
     * 
     * @param type $token
     */
    public function readFileToken($token)
    {
        if ($this->checkContextChange($token, self::CONTEXT_PHP)) {
            return;
        }
        // echo $token[0];
    }
        
    /**
     * Process Token knowing we are in PHP context, inside tags <?php ?>, but out of
     * Class and Class::method(). 
     * 
     * Here we might find:<pre>
     *   DocBlock:   /** DocBlock. This will be loaded as part of any of the following elements * /
     *   Class:      [abstract] class ClassName [extends Parent] [implements ...] {...}
     *   Attributes: [public|protected|private] [static] $attr [= [CONSTANT|'value'|array(...)|null]] ;
     * </pre>
     * 
     * For Methods self::context must change. 
     * 
     * @param type $token
     */
    public function readPhpToken($token)
    {
        if ($this->checkContextChange($token, self::CONTEXT_CLASS, self::CONTEXT_PHP, self::CONTEXT_FILE)) {
            return;
        }
        $this->loadTokenInElement($token, self::CONTEXT_PHP);
    }
    
    /**
     * Process Token knowing we are in Class context, inside tags class A... {}, but 
     * out of Class::method(). 
     * 
     * Here we might find:<pre>
     *   Constants:  const NAME = ['value'|array(...)] ;  
     *   Methods:    [public|protected|private] [static] function method(...) {...}
     * 
     * @param type $token
     * @return type
     */
    public function readClassToken($token)
    {
        if ($this->checkContextChange($token, self::CONTEXT_METHOD, self::CONTEXT_CLASS, self::CONTEXT_PHP)) {
            return;
        }
        $this->loadTokenInElement($token, self::CONTEXT_CLASS);
    }
    
    public function readMethodToken($token)
    {
        if ($this->checkContextChange($token, null, self::CONTEXT_METHOD, self::CONTEXT_CLASS)) {
            return;
        }
        $this->loadTokenInElement($token, self::CONTEXT_METHOD);
    }
    
    /**
     * Creates a new {@link ElementBuilder}. The last one gets forwarded to 
     * self::$processElementClosure for client code Element level processing.
     *
     * The current {@link self::pointer} is saved in the ElementBuilder::startPointer
     * attribute.
     * 
     * @param type $token
     * @param type $context
     */
    private function startNewElement($type)
    {
        if (null !== $this->elementBuilder && count($this->elementBuilder->tokens)) {
            $fn = $this->processElementClosure;
            $fn($this->elementBuilder);
        }
        $this->elementBuilder = new ElementBuilder();
        $this->elementBuilder->startPointer = $this->pointer;
        $this->elementBuilder->context = $this->context;
        $this->elementBuilder->elementType = $type;
    }
    
    /**
     * Saves pointer of last element's token.
     * TODO: not used. delete?
     */
    private function closeElement()
    {
        $this->elementBuilder->endPointer = $this->pointer;
    }
    
    /**
     * Either loads more tokens into current elementBuilder or triggers the 
     * instantiation of a new one. rependind on the token being a delimiter token or not.
     * 
     * When a new {@link ElementBuilder} is cerated the last one gets forwarded to 
     * self::$processElementClosure for client code Element level processing.
     * 
     * @param type $token
     * @param type $context
     */
    private function loadTokenInElement($token, $context)
    {
        if (in_array($token[0], $this->elementStartFlags[$context])) {
            $this->startNewElement($this->figureOutElementType($token, $context));
        }
        
        $singleTokenElement = false;
        if ($this->startingNewContext && 
            in_array($token[0], $this->contextStartElementEndFlags[$context])
        ) {
            $this->startingNewContext = false;
            $this->startNewElement(ElementBuilder::TYPE_START_CONTEXT_END_TOKEN);
            $singleTokenElement = true;
        }
        
        // TODO: nested Elements are not considered. 
        // When context changes, a new Element gets created.
        $this->elementBuilder->tokens[] = $token;
        
        if (T_DOC_COMMENT == $token[0] || in_array($token[0], $this->elementEndFlags[$context])
            || $singleTokenElement || $this->endingContext
        ) {
            $type = ElementBuilder::TYPE_GAP;
            $this->endingContext && $this->endingContext = false;
            $this->startNewElement($type);
        }
    }
    
    /**
     * Makes a unique type by concatenating context and token with '~' as glue.
     * 
     * @param string|array $token   The Token or TokenType i.e. $token[0].
     * @param string       $context The context.
     * 
     * @return string self::CONTEXT_* . '~' .  $token
     */
    public function figureOutElementType($token, $context)
    {
        $t = is_array($token) ? $token[0]: $token;
        return $context . '~' . $t;
    }
}
