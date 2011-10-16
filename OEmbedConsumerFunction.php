<?php

class OEmbedConsumerFunction {
	
	/**
	 * @todo XSS security / use iframe
	 * @todo turn into a parser function
	 * @todo accept size preferences
	 * @todo error handling
	 * @todo async?
	 *
	 * @param type $content
	 * @param type $args 
	 * @param type $parser
	 */
	public static function oembedTag( $content, $args, $parser ) {
		// simple for now ;)
		$url = $content;

		try {	
			$data = self::discoverAndFetchInfo( $url );
			return self::formatEmbed( $data );
		} catch (MWException $e) {
			return htmlspecialchars( $e->getMessage() );
		}
		//return '<pre>' . htmlspecialchars( var_export( $data, true ) ) . '</pre>';
	}

	/**
	 * @todo handle redirects
	 * @todo cache stuff
	 * @todo alternate provider modes?
	 * @todo error handling
	 * @todo relative link handling
	 *
	 * @param string $url to do discovery on
	 */
	static function discoverAndFetchInfo( $url ) {
		$html = Http::get( $url );
		if ($html === false) {
			throw new MWException( 'bad URL for oEmbed discovery' );
		}

		$dom = new DOMDocument();

		wfSuppressWarnings();
		$ok = $dom->loadHTML( $html );
		wfRestoreWarnings();

		if (!$ok ) {
			throw new MWException( 'bad HTML for oEmbed discovery' );
		}
		
		//       <link rel="alternate" type="application/json+oembed" href="http://www.youtube.com/oembed?url=http%3A//www.youtube.com/watch?v%3DXlpiNvzYcng&amp;format=json" title="Apple Mac OS X Lion Touch Gestures Animations">
		$links = $dom->getElementsByTagName( 'link' );
		foreach ( $links as $link ) {
			if ( $link->getAttribute( 'rel' ) == 'alternate' &&
					$link->getAttribute( 'type' ) == 'application/json+oembed' ) {
				$target = $link->getAttribute( 'href' );
				$info = self::fetchInfo( $target );
				$info->original_url = $url;
				return $info;
			}
		}
		
		throw new MWException( 'no oEmbed discovery info' );
	}
	
	/**
	 *
	 * @param string $url to actually fetch from
	 */
	static function fetchInfo( $url ) {
		$json = Http::get( $url );
		if( $json === false ) {
			throw new MWException( 'bad return from oEmbed provider' );
		}
		
		$data = FormatJson::decode( $json );
		return $data;
	}
	
	static function formatEmbed( $data ) {
		switch ($data->type) {
			case 'video':
			case 'rich':
				// ..
				return Html::rawElement(
					'div',
					array(
						'style' => 'width: ' . intval($data->width) . 'px; height: ' . intval($data->height) . 'px'
					),
					$data->html
				);
			case 'photo':
				return Html::element(
					'img',
					array(
						'src' => $data->url,
						'width' => $data->width,
						'height' => $data->height
					)
				);
			case 'link':
				return Html::element(
					'a',
					array(
						'href' => $data->original_link,
						'class' => 'extlink oembed'
					),
					$data->title
				);
			default:
				throw new MWException( 'unknown oEmbed data type' );
		}
	}
}
