<?php
Fl::loadClass ( 'Fl_Base' );
Fl::loadClass ( 'Fl_Html_Static' );
Fl::loadClass ( 'Fl_Define' );
Fl::loadClass ( 'Fl_Tpl' );
/**
 * 
 * HTML Compress support tpl
 * @author welefen
 *
 */
class Fl_Html_Compress extends Fl_Base {

	/**
	 * 
	 * 压缩选项
	 * @var array
	 */
	public $options = array (
		"remove_comment" => true,  //移除注释
		"simple_doctype" => true,  //简化doctype
		"simple_charset" => true,  //简化charset
		"newline_to_space" => true,  //换行符转为空格
		"tag_to_lower" => true,  //小写标签名
		"remove_html_xmlns" => true,  //移除html的命名空间
		"remove_inter_tag_space" => false,  //移除标签之间的空格，非安全
		"remove_inter_block_tag_space" => false,  //移除块级元素之间的空格，非安全
		"replace_multi_space" => " ",  //将多个空格替换为一个 
		"remove_empty_script" => false,  //移除空的script标签
		"remove_empty_style" => false,  //移除空的style标签
		"remove_optional_attrs" => true,  //移除可选的属性
		"remove_attrs_quote" => true,  //移除属性值的引号
		"remove_attrs_optional_value" => true,  //移除可选属性的值
		"remove_http_protocal" => false,  //移除http协议
		"remove_https_protocal" => false,  //移除https协议
		"remove_optional_end_tag" => true,  //移除可选的结束标签
		"remove_optional_end_tag_list" => array (),  //结束标签列表
		"chars_line_break" => 8000,  //
		"compress_style_value" => true,  //压缩标签的style值 
		"compress_inline_css" => true,  //压缩内联的CSS
		"compress_inline_js" => true,  //压缩内联的JS
		'remove_inline_js_cdata' => true,  //
		"compress_tpl_script" => false,  //压缩前端模版
		"compress_tag" => true,  //压缩标签
		"merge_adjacent_css" => true,  //合并相邻的css
		"merge_adjacent_js" => false  //合并相邻的js
	);

	/**
	 * 
	 * is a xml
	 * @var boolean
	 */
	public $isXML = false;

	/**
	 * 自定义内联JS压缩方法
	 * @var string
	 */
	public $jsCompressMethod = '';

	/**
	 * 
	 * 前端模版压缩方法
	 * @var string
	 */
	public $jsTplCompressMethod = '';

	/**
	 * 自定义内联CSS压缩方法
	 * @var string
	 */
	public $cssCompressMethod = '';

	/**
	 * prev token
	 * @var array
	 */
	protected $preToken = array ();

	/**
	 * 
	 * next token
	 * @var array
	 */
	protected $nextToken = array ();

	/**
	 * 
	 * output
	 * @var string
	 */
	protected $output = '';

	/**
	 * 
	 * prev output text
	 * @var string
	 */
	protected $preOutputText = '';

	/**
	 * 
	 * token instance
	 * @var object
	 */
	protected $tokenInstance = null;

	/**
	 * 
	 * pre is script
	 * @var boolean
	 */
	protected $preIsScript = false;

	/**
	 * 
	 * 压缩HTML
	 * @param string $text
	 * @param array $options
	 */
	public function run($options = array()) {
		$this->options = array_merge ( $this->options, $options );
		if ($this->isXML) {
			$this->options ['tag_to_lower'] = false;
		}
		$this->tokenInstance = $this->getInstance ( 'Fl_Html_Token' );
		$token = array ();
		$this->nextToken = $this->getNextToken ();
		$end = false;
		$num = 0;
		$charsLineBreak = intval ( $this->options ['chars_line_break'] );
		while ( true ) {
			$this->preToken = $token;
			$token = $this->nextToken;
			$this->nextToken = $this->getNextToken ();
			if (! $this->nextToken) {
				$end = true;
				$this->nextToken = array ();
			}
			if ($token ['type'] === FL_TOKEN_XML_HEAD) {
				$this->isXML = true;
				$this->options ['tag_to_lower'] = false;
			}
			$this->preOutputText = $this->compressToken ( $token );
			if ($token ['type'] !== FL_TOKEN_HTML_SCRIPT_TAG) {
				$this->preIsScript = false;
			}
			$len = strlen ( $this->preOutputText );
			if ($charsLineBreak && ($num + $len) > $charsLineBreak) {
				$this->output .= FL_NEWLINE;
				$num = $len;
			} else {
				$num += $len;
			}
			if ($this->output {strlen ( $this->output ) - 1} === FL_SPACE) {
				$this->preOutputText = ltrim ( $this->preOutputText );
			}
			$this->output .= $this->preOutputText;
			if ($end) {
				break;
			}
		}
		return $this->output;
	}

	/**
	 * 
	 * 获取下一个token
	 */
	public function getNextToken() {
		$nextToken = $this->tokenInstance->getNextToken ();
		if (! $nextToken) {
			return false;
		}
		if ($this->options ['compress_tag']) {
			if ($nextToken ['type'] === FL_TOKEN_HTML_TAG_START) {
				$result = $this->getInstance ( "Fl_Html_TagToken", $nextToken ['value'] )->run ();
				$nextToken ['tag'] = $result ['tag'];
				$nextToken ['attrs'] = $result ['attrs'];
			} elseif ($nextToken ['type'] === FL_TOKEN_HTML_TAG_END) {
				$nextToken ['tag'] = Fl_Html_Static::getTagName ( $nextToken ['value'], $this );
			}
		} else {
			if (Fl_Html_Static::isTag ( $nextToken )) {
				$nextToken ['tag'] = Fl_Html_Static::getTagName ( $nextToken ['value'], $this );
			}
		}
		if ($nextToken ['tag']) {
			$nextToken ['lowerTag'] = strtolower ( $nextToken ['tag'] );
		}
		return $nextToken;
	}

	/**
	 * 
	 * 压缩每个token
	 * @param array $token
	 */
	public function compressToken($token) {
		$result = $this->compressCommon ( $token );
		switch ($token ['type']) {
			case FL_TOKEN_HTML_DOCTYPE :
				if ($this->options ['simple_doctype']) {
					return Fl_Html_Static::SIMPLE_DOCTYPE;
				} else {
					$result .= $token ['value'];
					break;
				}
			case FL_TOKEN_HTML_TAG_START :
				$result .= $this->compressStartTag ( $token );
				break;
			case FL_TOKEN_HTML_TEXT :
				$result .= $this->compressText ( $token );
				$result = preg_replace ( "/ +/", " ", $result ); //将多个空格替换为一个
				if ($result == FL_SPACE) {
					if ($this->preToken ['type'] == FL_TOKEN_HTML_TAG_END && $this->nextToken ['type'] == FL_TOKEN_HTML_TAG_END) {
						$result = "";
					} else {
						if (Fl_Html_Static::isTag ( $this->nextToken ) || Fl_Html_Static::isTag ( $this->preToken )) {
							if ($this->options ['remove_inter_tag_space']) {
								$result = '';
							} else if ($this->options ['remove_inter_block_tag_space'] && (Fl_Html_Static::isBlockTag ( $this->nextToken ['lowerTag'] ) || Fl_Html_Static::isBlockTag ( $this->preToken ['lowerTag'] ))) {
								$result = '';
							} elseif (Fl_Html_Static::isSafeTag ( $this->nextToken ['lowerTag'] ) || Fl_Html_Static::isSafeTag ( $this->preToken ['lowerTag'] )) {
								$result = "";
							}
						}
					}
				}
				break;
			case FL_TOKEN_TPL :
				$result .= $this->compressTpl ( $token );
				break;
			case FL_TOKEN_HTML_TAG_END :
				$result .= $this->compressEndTag ( $token );
				break;
			case FL_TOKEN_HTML_SCRIPT_TAG :
				$result .= $this->compressScript ( $token );
				break;
			case FL_TOKEN_HTML_STYLE_TAG :
				$result .= $this->compressStyle ( $token );
				break;
			default :
				$result .= $this->compressDefault ( $token );
		}
		return $result;
	}

	/**
	 * 
	 * compress script
	 * @param array $token
	 */
	public function compressScript($token) {
		if (! $this->options ['compress_tag']) {
			return $token ['value'];
		}
		$info = Fl_Html_Static::splitSpecialValue ( $token ['value'], 'script', $this );
		$content = $info ['content'];
		$tagInfo = Fl_Html_Static::getScriptTagInfo ( $info ['tag_start'], $this );
		$isExternal = $tagInfo ['external'];
		$isScript = $tagInfo ['script'];
		//移除空的script标签
		if ($this->options ['remove_empty_script']) {
			if (! $isExternal && empty ( $content )) {
				return '';
			}
		}
		//压缩内联的JS
		if ($this->options ['compress_inline_js']) {
			if ($content && ! $isExternal && $isScript) {
				$containTpl = $this->containTpl ( $content );
				//自定义内联JS压缩方法
				if (! $containTpl && $this->jsCompressMethod) {
					$compressContent = call_user_func ( $this->jsCompressMethod, $content, $this );
				} else {
					$compressContent = $this->getInstance ( "Fl_Js_Compress", $content )->run ();
				}
				if (! empty ( $compressContent ) && ! is_array ( $compressContent )) {
					$content = $compressContent;
				}
			}
		}
		//移除可选的属性
		if ($this->options ['remove_optional_attrs']) {
			$tagInfo ['lowerTag'] = strtolower ( $tagInfo ['tag'] );
			$info ['tag_start'] = $this->compressStartTag ( $tagInfo );
		}
		//合并相邻的script标签
		if ($this->options ['merge_adjacent_js']) {
			if ($isScript && ! $isExternal && $this->preIsScript && $this->preIsScript ['script'] && ! $this->preIsScript ['external']) {
				$endStyle = '</script>';
				$outputLen = strlen ( $this->output );
				$last = substr ( $this->output, $outputLen - 9 );
				if (strtolower ( $last ) === $endStyle) {
					$this->output = rtrim ( substr ( $this->output, 0, $outputLen - 9 ), ";" );
					return ';' . rtrim ( $content, ";" ) . $info ['tag_end'];
				}
			}
		}
		//不移除CDATA
		if (! $this->options ['remove_inline_js_cdata'] || $this->isXML) {
			$content = $info ['cprefix'] . $content . $info ['csuffix'];
		}
		//压缩前端模版
		if ($this->options ['compress_tpl_script']) {
			if ($tagInfo ['tpl'] && ! $tagInfo ['external'] && $this->jsTplCompressMethod) {
				$c = call_user_func ( $this->jsTplCompressMethod, $content, $this );
				if (! empty ( $c )) {
					$content = $c;
				}
			}
		}
		$this->preIsScript = $tagInfo;
		return $info ['tag_start'] . $content . $info ['tag_end'];
	}

	/**
	 * 
	 * compress style
	 * @param array $token
	 */
	public function compressStyle($token) {
		if (! $this->options ['compress_tag']) {
			return $token ['value'];
		}
		$info = Fl_Html_Static::splitSpecialValue ( $token ['value'], 'style', $this );
		$content = trim ( $info ['content'] );
		if ($this->options ['remove_empty_style'] && ! $content) {
			return '';
		}
		if ($this->options ['compress_inline_css'] && $content) {
			Fl::loadClass ( "Fl_Css_Static" );
			$value = Fl_Css_Static::getStyleDetail ( $content );
			$containTpl = $this->containTpl ( $value ['value'] );
			//自定义内联CSS压缩方法
			if (! $containTpl && $this->cssCompressMethod) {
				$content = call_user_func ( $this->cssCompressMethod, $value ['value'], $this );
			} else {
				$content = $this->getInstance ( "Fl_Css_Compress", $value ['value'] )->run ();
			}
		}
		if ($this->options ['remove_optional_attrs']) {
			$tagInfo = $this->getInstance ( "Fl_Html_TagToken", $info ['tag_start'] )->run ();
			$tagInfo ['lowerTag'] = strtolower ( $tagInfo ['tag'] );
			$info ['tag_start'] = $this->compressStartTag ( $tagInfo );
		}
		if ($this->options ['merge_adjacent_css']) {
			$endStyle = '</style>';
			$outputLen = strlen ( $this->output );
			$last = substr ( $this->output, $outputLen - 8 );
			if (strtolower ( $last ) === $endStyle) {
				$this->output = substr ( $this->output, 0, $outputLen - 8 );
				return $content . $info ['tag_end'];
			}
		}
		return $info ['tag_start'] . $content . $info ['tag_end'];
	}

	/**
	 * 
	 * 压缩一些通用的，如：注释，换行等
	 * @param array $token
	 */
	public function compressCommon($token) {
		$return = $comment = $newline = '';
		foreach ( $token ['commentBefore'] as $item ) {
			//如果注释内容第一个字符是!，则保留该注释
			if (preg_match ( '/^\<\!\-\-\!/', $item ['text'] ) || ! $this->options ['remove_comment']) {
				$comment .= $item ['text'];
			}
		}
		if (! $token ['newlineBefore']) {
			return $comment;
		}
		if (Fl_Html_Static::isNoNewlineToken ( $token ['type'] )) {
			return $comment;
		}
		//如果是模版语法且不会输出，则不添加空白字符
		if ($token ['type'] === FL_TOKEN_TPL && ! $this->checkTplHasOutput ( $token ['value'] )) {
			return $comment;
		}
		if ($this->options ['newline_to_space']) {
			$newline = FL_SPACE;
		} else {
			$newline = FL_NEWLINE;
		}
		$preText = $this->preOutputText;
		if (! $this->isXML && $preText && substr ( $preText, strlen ( $preText ) - 1, 1 ) == $newline) {
			return $comment;
		}
		if (Fl_Html_Static::isTag ( $token )) {
			if (Fl_Html_Static::isSafeTag ( $token ['tag'] )) {
				return $comment;
			}
			if ($this->options ['remove_inter_tag_space']) {
				return $comment;
			}
			if ($this->options ['remove_inter_block_tag_space'] && Fl_Html_Static::isBlockTag ( $token ['tag'] )) {
				return $comment;
			}
		}
		if (Fl_Html_Static::isTag ( $this->nextToken ) || Fl_Html_Static::isTag ( $this->preToken )) {
			if ($this->options ['remove_inter_tag_space']) {
				return $comment;
			} else if ($this->options ['remove_inter_block_tag_space'] && (Fl_Html_Static::isBlockTag ( $this->nextToken ['lowerTag'] ) || Fl_Html_Static::isBlockTag ( $this->preToken ['lowerTag'] ))) {
				return $comment;
			}
		}
		if ($token ['col'] == 0) {
			$return .= $comment . $newline;
		} else {
			$return .= $newline . $comment;
		}
		return $return;
	}

	/**
	 * 
	 * 压缩默认的token
	 * @param array $token
	 */
	public function compressDefault($token) {
		return $token ['value'];
	}

	/**
	 * 
	 * 压缩文本
	 */
	public function compressText($token) {
		$value = $token ['value'];
		//如果文本中含有//，则不去除换行等，主要是一些异步接口（JS环境）会被识别成HTML环境，如果有JS的//注释就要注意了
		if (strpos ( $value, '//' ) !== false) {
			return $value;
		}
		if ($this->options ['newline_to_space']) {
			$value = str_replace ( FL_NEWLINE, FL_SPACE, $value );
		}
		$value = str_replace ( "\t", " ", $value );
		if ($this->options ['replace_multi_space'] !== false) {
			$value = preg_replace ( FL_SPACE_PATTERN, $this->options ['replace_multi_space'], $value );
		}
		if ($this->options ['remove_inter_tag_space']) {
			$value = rtrim ( $value );
		} elseif (Fl_Html_Static::isTag ( $this->nextToken )) {
			if (Fl_Html_Static::isSafeTag ( $this->nextToken ['lowerTag'] )) {
				$value = rtrim ( $value );
			} else {
				if ($this->options ['remove_inter_block_tag_space'] && Fl_Html_Static::isBlockTag ( $this->nextToken ['lowerTag'] )) {
					$value = rtrim ( $value );
				}
			}
		}
		return $value;
	}

	/**
	 * 
	 * return simple charset
	 * @param array $token
	 */
	public function compressCharset($token) {
		$attrs = $token ['attrs'];
		$isCharset = 0;
		$contentValue = '';
		foreach ( $attrs as $item ) {
			if ($item [1] !== '=') {
				return false;
			}
			$valueDetail = Fl_Html_Static::getUnquoteText ( $item [2] );
			$nameLower = strtolower ( $item [0] );
			if ($nameLower === 'http-equiv' && strtolower ( $valueDetail ['text'] ) === 'content-type') {
				$isCharset ++;
			} else if ($nameLower === 'content' && strpos ( $valueDetail ['text'], 'charset=' ) != false) {
				$isCharset ++;
				$contentValue = $valueDetail ['text'];
			} else {
				return false;
			}
		}
		if ($isCharset != 2 || ! $contentValue) {
			return false;
		}
		$charsetPattern = "/charset=([\w\-]+)/i";
		preg_match ( $charsetPattern, $contentValue, $matches );
		if ($matches [1]) {
			$charset = $matches [1];
			return '<meta charset=' . $charset . '>';
		}
		return false;
	}

	/**
	 * 
	 * 压缩开始标签
	 */
	public function compressStartTag($token) {
		if ($this->isXML || ! $this->options ['compress_tag']) {
			return $token ['value'];
		}
		$tag = $token ['tag'];
		$lowerTag = $token ['lowerTag'];
		if ($lowerTag === 'meta' && $this->options ['simple_charset']) {
			$result = $this->compressCharset ( $token );
			if ($result) {
				return $result;
			}
		}
		$attrs = $token ['attrs'];
		$resultAttrs = array ();
		foreach ( $attrs as $item ) {
			if ($item [1] === '=') {
				$valueDetail = Fl_Html_Static::getUnquoteText ( $item [2] );
				$nameLower = strtolower ( $item [0] );
				if ($this->options ['remove_optional_attrs'] && Fl_Html_Static::isTagAttrDefaultValue ( $item [0], $valueDetail ['text'], $lowerTag )) {
					continue;
				}
				if ($this->options ['remove_attrs_optional_value'] && Fl_Html_Static::isTagOnlyNameAttr ( $item [0] )) {
					$item = array (
						$item [0] 
					);
				} else if ($this->options ['remove_attrs_quote'] && Fl_Html_Static::isAttrValueNoQuote ( $valueDetail ['text'], $this )) {
					if ($nameLower != 'style') {
						$item [2] = $valueDetail ['text'];
						$valueDetail ['quote'] = '';
					}
				}
				//$nameLower = strtolower ( $item [0] );
				//remove html xmlns attr
				if ($lowerTag === 'html' && $nameLower === 'xmlns' && $this->options ['remove_html_xmlns']) {
					continue;
				}
				if ($this->options ['remove_http_protocal'] || $this->options ['remove_https_protocal']) {
					if ($nameLower === 'href' || $nameLower === "src") {
						$valueDetail = Fl_Html_Static::getUnquoteText ( $item [2] );
						$value = $valueDetail ['text'];
						if ($this->options ['remove_http_protocal'] && strpos ( $value, "http://" ) === 0) {
							$value = substr ( $value, 5 );
							$item [2] = $valueDetail ['quote'] . $value . $valueDetail ['quote'];
						} elseif ($this->options ['remove_https_protocal'] && strpos ( $value, "https://" ) === 0) {
							$value = substr ( $value, 6 );
							$item [2] = $valueDetail ['quote'] . $value . $valueDetail ['quote'];
						}
					}
				}
				//$valueDetail = Fl_Html_Static::getUnquoteText ( $item [2] );
				//remove ext blank in class value
				if ($nameLower === 'class' && ! $this->containTpl ( $item [2] )) {
					$value = trim ( $valueDetail ['text'] );
					$value = preg_split ( FL_SPACE_PATTERN, $value );
					$item [2] = $valueDetail ['quote'] . join ( FL_SPACE, $value ) . $valueDetail ['quote'];
				} else if ($this->options ['compress_style_value'] && $nameLower === 'style' && ! $this->containTpl ( $valueDetail ["text"] )) {
					//如果压缩失败，则直接使用源代码
					try {
						if ($this->cssCompressMethod) {
							$value = call_user_func ( $this->cssCompressMethod, "*{" . $valueDetail ["text"] . "}", $this );
						} else {
							$value = $this->getInstance ( "Fl_Css_Compress", "*{" . $valueDetail ["text"] . "}" )->run ();
						}
						$item [2] = $valueDetail ['quote'] . substr ( $value, 2, strlen ( $value ) - 3 ) . $valueDetail ['quote'];
					} catch ( Fl_Exception $e ) {
						//这里不抛出异常
					}
				} else if (strpos ( $nameLower, "on" ) === 0) { //remove last ; in onxxx attr
					$value = trim ( trim ( $valueDetail ['text'] ), ';' );
					$item [2] = $valueDetail ['quote'] . $value . $valueDetail ['quote'];
				}
			}
			$resultAttrs [] = $item;
		}
		$return = Fl_Html_Static::LEFT;
		if ($this->options ['tag_to_lower']) {
			$return .= $lowerTag;
		} else {
			$return .= $tag;
		}
		$blankChar = FL_SPACE;
		$return .= $blankChar;
		foreach ( $resultAttrs as $item ) {
			$itemText = join ( '', $item );
			$lastChar = substr ( $return, strlen ( $return ) - 1 );
			if ($lastChar !== '"' && $lastChar !== "'" && $lastChar !== $blankChar) {
				if ($item [1] !== '=' && $this->isTpl ( $item [0] ) && ! $this->checkTplHasOutput ( $item [0] )) {
					//do nothing
				} else {
					$return .= $blankChar;
				}
			} else {
				$last2Char = substr ( $return, strlen ( $return ) - 2 );
				if ($last2Char === '\\"' || $last2Char === "\\'") {
					$return .= $blankChar;
				}
			}
			$return .= $itemText;
		}
		$return = rtrim ( $return ) . Fl_Html_Static::RIGHT;
		return $return;
	}

	/**
	 * 
	 * 压缩闭合标签
	 * @param array $token
	 */
	public function compressEndTag($token) {
		if ($this->options ['remove_optional_end_tag']) {
			if (Fl_Html_Static::isOptionalEndTag ( $token ['lowerTag'], $this->options ['remove_optional_end_tag_list'] )) {
				return '';
			}
		}
		$tag = $this->options ['tag_to_lower'] ? $token ['lowerTag'] : $token ['tag'];
		return '</' . $tag . '>';
	}

	/**
	 * 
	 * 压缩模版语法
	 * @param array $token
	 */
	public function compressTpl($token) {
		return Fl_Tpl::factory ( $this )->compress ( $token ['value'], $this );
	}
}