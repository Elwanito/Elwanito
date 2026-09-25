<?php
/**
 * A small, dependency-free Markdown-to-HTML converter for exactly the
 * subset of Markdown our own AI prompts ask the model to produce
 * (headings, bold/italic, unordered/ordered lists, GFM tables, blockquotes,
 * inline code, paragraphs). Not a general CommonMark implementation - there
 * is no Composer/SSH access on this host to pull in a library, and this
 * plugin controls the exact prompt that generates the input, so a compact
 * hand-written converter is both sufficient and easier to keep correct
 * than a partial third-party dependency vendored by hand.
 *
 * Line-by-line state machine (not blank-line block splitting) because AI
 * output very often writes "Intro sentence:\n- item\n- item" with no blank
 * line before the list - block splitting would wrongly treat that whole
 * thing as one paragraph.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_Markdown {

	public static function to_html( $markdown ) {
		$lines = explode( "\n", str_replace( "\r\n", "\n", trim( $markdown ) ) );
		$lines[] = ''; // Sentinel blank line so trailing buffers always flush.

		$output        = array();
		$paragraph_buf = array();
		$list_buf      = array();
		$list_tag      = null;
		$blockquote_buf = array();

		$flush_paragraph = function () use ( &$output, &$paragraph_buf ) {
			if ( ! empty( $paragraph_buf ) ) {
				$output[] = '<p>' . self::inline( implode( "\n", $paragraph_buf ) ) . '</p>';
				$paragraph_buf = array();
			}
		};
		$flush_list = function () use ( &$output, &$list_buf, &$list_tag ) {
			if ( ! empty( $list_buf ) ) {
				$items = array_map( function ( $item ) {
					return '<li>' . self::inline( $item ) . '</li>';
				}, $list_buf );
				$output[] = "<{$list_tag}>" . implode( '', $items ) . "</{$list_tag}>";
				$list_buf = array();
				$list_tag = null;
			}
		};
		$flush_blockquote = function () use ( &$output, &$blockquote_buf ) {
			if ( ! empty( $blockquote_buf ) ) {
				$output[] = '<blockquote><p>' . self::inline( implode( ' ', $blockquote_buf ) ) . '</p></blockquote>';
				$blockquote_buf = array();
			}
		};

		$count = count( $lines );
		for ( $i = 0; $i < $count; $i++ ) {
			$line = $lines[ $i ];
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				$flush_paragraph();
				$flush_list();
				$flush_blockquote();
				continue;
			}

			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $trimmed, $m ) ) {
				$flush_paragraph();
				$flush_list();
				$flush_blockquote();
				$level    = strlen( $m[1] );
				$output[] = "<h{$level}>" . self::inline( trim( $m[2] ) ) . "</h{$level}>";
				continue;
			}

			// Table: current line has a pipe and the next line is a separator row.
			if ( false !== strpos( $trimmed, '|' ) && isset( $lines[ $i + 1 ] ) && self::is_separator_row( $lines[ $i + 1 ] ) ) {
				$flush_paragraph();
				$flush_list();
				$flush_blockquote();

				$header_cells = self::split_row( $trimmed );
				$i += 2; // Skip header + separator.
				$body_rows = array();
				while ( $i < $count && false !== strpos( trim( $lines[ $i ] ), '|' ) ) {
					$body_rows[] = self::split_row( $lines[ $i ] );
					$i++;
				}
				$i--; // Compensate for the loop's own $i++.

				$output[] = self::render_table( $header_cells, $body_rows );
				continue;
			}

			if ( preg_match( '/^[-*]\s+(.*)$/', $trimmed, $m ) ) {
				$flush_paragraph();
				$flush_blockquote();
				if ( 'ul' !== $list_tag ) {
					$flush_list();
					$list_tag = 'ul';
				}
				$list_buf[] = $m[1];
				continue;
			}

			if ( preg_match( '/^\d+\.\s+(.*)$/', $trimmed, $m ) ) {
				$flush_paragraph();
				$flush_blockquote();
				if ( 'ol' !== $list_tag ) {
					$flush_list();
					$list_tag = 'ol';
				}
				$list_buf[] = $m[1];
				continue;
			}

			if ( '>' === substr( $trimmed, 0, 1 ) ) {
				$flush_paragraph();
				$flush_list();
				$blockquote_buf[] = ltrim( preg_replace( '/^>\s?/', '', $trimmed ) );
				continue;
			}

			// Plain text line: continues a list item if one is open (a
			// wrapped continuation line), otherwise accumulates as a
			// paragraph.
			if ( ! empty( $list_buf ) ) {
				$list_buf[ count( $list_buf ) - 1 ] .= ' ' . $trimmed;
			} else {
				$flush_blockquote();
				$paragraph_buf[] = $trimmed;
			}
		}

		return implode( "\n", $output );
	}

	private static function is_separator_row( $line ) {
		$line = trim( $line );
		return (bool) preg_match( '/^\|?(\s*:?-{2,}:?\s*\|)+\s*:?-{2,}:?\s*\|?$/', $line );
	}

	private static function split_row( $row ) {
		$row = trim( trim( $row ), '|' );
		return array_map( 'trim', explode( '|', $row ) );
	}

	private static function render_table( $header_cells, $body_rows ) {
		$html  = '<div class="elwanito-table-wrap"><table><thead><tr>';
		foreach ( $header_cells as $cell ) {
			$html .= '<th>' . self::inline( $cell ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $body_rows as $row ) {
			$html .= '<tr>';
			foreach ( $row as $cell ) {
				$html .= '<td>' . self::inline( $cell ) . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table></div>';
		return $html;
	}

	/**
	 * Inline formatting: bold, italic, inline code. Escape first, then
	 * re-introduce only the safe tags we generate ourselves - this also
	 * means any stray HTML/script the model wrote comes through as inert
	 * escaped text rather than live markup, which matters since content
	 * publishes with no human review when auto-publish is on.
	 */
	private static function inline( $text ) {
		$text = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text );
		$text = preg_replace( '/`(.+?)`/s', '<code>$1</code>', $text );
		$text = str_replace( "\n", '<br>', $text );
		return $text;
	}
}
