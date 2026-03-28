<?php
/**
 * SEO article analysis (on-page signals) for WEBO MCP — WordPress/PHP only.
 *
 * Ported from Agentic-SEO-Skill article_seo.py; output includes seo_score, issues, content_gaps.
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Tools;

use DOMDocument;
use DOMElement;
use DOMXPath;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP tool callback: seo/article-analysis.
 */
class SeoArticleAnalysis {

	/**
	 * Tool entry: returns { ok, result } | { ok, error }.
	 *
	 * WordPress-only: requires a published (readable) post_id. No raw HTML or external URL inputs.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function analyze_tool( array $arguments ) {
		$post_id           = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
		$keyword           = isset( $arguments['keyword'] ) ? (string) $arguments['keyword'] : '';
		$no_autocomplete   = ! empty( $arguments['no_autocomplete'] );
		$include_rank_math = ! array_key_exists( 'include_rank_math', $arguments ) || ! empty( $arguments['include_rank_math'] );

		if ( $post_id <= 0 ) {
			return array(
				'ok'    => false,
				'error' => __( 'post_id is required (positive WordPress post ID).', 'webo-mcp' ),
			);
		}

		if ( ! current_user_can( 'read_post', $post_id ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'You do not have permission to read this post', 'webo-mcp' ),
			);
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'ok'    => false,
				'error' => __( 'Post not found', 'webo-mcp' ),
			);
		}

		$rank_bundle = array(
			'seo_meta' => array(),
			'source'   => 'none',
		);

		$title     = get_the_title( $post );
		$body      = apply_filters( 'the_content', $post->post_content );
		$permalink = get_permalink( $post );

		if ( $include_rank_math ) {
			$rank_bundle = self::collect_rank_math_bundle( $post_id );
		}

		$rm = isset( $rank_bundle['seo_meta'] ) && is_array( $rank_bundle['seo_meta'] ) ? $rank_bundle['seo_meta'] : array();

		$head_title = $title;
		if ( ! empty( $rm['rank_math_title'] ) && is_string( $rm['rank_math_title'] ) ) {
			$head_title = wp_strip_all_tags( $rm['rank_math_title'] );
		}

		$head_meta = '';
		if ( ! empty( $rm['rank_math_description'] ) && is_string( $rm['rank_math_description'] ) ) {
			$head_meta = '<meta name="description" content="'
				. esc_attr( wp_strip_all_tags( $rm['rank_math_description'] ) )
				. '">';
		}

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
			. esc_html( $head_title )
			. '</title>'
			. $head_meta
			. '</head><body><article class="entry-content post-content">'
			. $body
			. '</article></body></html>';

		$effective_url = is_string( $permalink ) ? $permalink : '';


		try {
			$extracted       = self::extract_content( $html );
			$extracted       = self::enrich_extracted_from_post( $extracted, $post );
			$structured_data = self::extract_structured_data( $html );
			$all_parts       = array_merge(
				$extracted['h1'],
				$extracted['h2s'],
				$extracted['h3s'],
				$extracted['paragraphs']
			);
			$full_text       = implode( ' ', $all_parts );
			$readability     = self::compute_readability( $full_text );
			$extracted_kws = self::extract_keywords_frequency( $full_text );
			$target_kw     = '' !== trim( $keyword ) ? strtolower( trim( $keyword ) ) : ( $extracted_kws[0] ?? '' );

			if ( '' === $target_kw && $include_rank_math ) {
				$rm_kw = isset( $rank_bundle['seo_meta']['rank_math_focus_keyword'] ) ? $rank_bundle['seo_meta']['rank_math_focus_keyword'] : '';
				if ( is_string( $rm_kw ) && '' !== trim( $rm_kw ) ) {
					$target_kw = strtolower( trim( $rm_kw ) );
				}
			}

			$related_kws = array();
			if ( '' !== $target_kw && ! $no_autocomplete ) {
				$related_kws = self::get_google_autocomplete( $target_kw );
				$related_kws = array_values(
					array_filter(
						$related_kws,
						static function ( $k ) use ( $target_kw ) {
							return strtolower( (string) $k ) !== $target_kw;
						}
					)
				);
			}

			if ( count( $related_kws ) < 5 ) {
				foreach ( $extracted_kws as $k ) {
					if ( ! in_array( $k, $related_kws, true ) && $k !== $target_kw ) {
						$related_kws[] = $k;
					}
				}
			}
			$related_kws = array_slice( $related_kws, 0, 15 );

			$issues        = self::detect_seo_issues( $extracted, $structured_data, $readability );
			$seo_score     = self::compute_seo_score( $issues );
			$content_gaps  = self::compute_content_gaps( strtolower( $full_text ), $related_kws, $extracted_kws, $extracted, $readability );

			$rank_math_block = self::build_rank_math_result_block( $include_rank_math, $rank_bundle );

			return array(
				'ok'     => true,
				'result' => array(
					'source'             => 'wordpress_post',
					'post_id'            => $post_id,
					'permalink'          => '' !== $effective_url ? $effective_url : null,
					'post_type'          => $post->post_type,
					'cms_detected'       => 'wordpress',
					'title'              => $extracted['title'],
					'meta_description'   => $extracted['meta_description'],
					'og_description'     => $extracted['og_description'],
					'author'             => $extracted['author'],
					'publish_date'       => $extracted['publish_date'],
					'labels'             => $extracted['labels'],
					'headings'           => array(
						'h1' => $extracted['h1'],
						'h2' => $extracted['h2s'],
						'h3' => $extracted['h3s'],
					),
					'paragraphs'         => $extracted['paragraphs'],
					'images'             => $extracted['images'],
					'structured_data'    => $structured_data,
					'readability'        => $readability,
					'target_keyword'     => $target_kw,
					'extracted_keywords' => $extracted_kws,
					'related_keywords'   => $related_kws,
					'seo_score'          => $seo_score,
					'issues'             => $issues,
					'content_gaps'       => $content_gaps,
					'rank_math'          => $rank_math_block,
					'integration'        => array(
						'ability_get_post_seo_meta' => 'webo-rank-math/get-post-seo-meta',
						'ability_update_post_seo_meta' => 'webo-rank-math/update-post-seo-meta',
						'wordpress_get_post'         => 'webo/get-post',
						'wordpress_update_post'      => 'webo/update-post',
					),
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'ok'    => false,
				'error' => 'analysis_failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Merge WP post author, date, and public taxonomy terms into extracted signals (synthetic HTML has no theme footer).
	 *
	 * @param array<string, mixed> $extracted Parsed DOM signals.
	 * @param WP_Post              $post      Post object.
	 * @return array<string, mixed>
	 */
	private static function enrich_extracted_from_post( array $extracted, WP_Post $post ) {
		$extracted['labels'] = self::labels_from_post_terms( $post->ID );
		if ( empty( $extracted['author'] ) ) {
			$name = get_the_author_meta( 'display_name', (int) $post->post_author );
			if ( is_string( $name ) && '' !== $name ) {
				$extracted['author'] = self::mb_limit( $name, 100 );
			}
		}
		if ( empty( $extracted['publish_date'] ) ) {
			$d = get_the_date( 'c', $post );
			if ( is_string( $d ) && '' !== $d ) {
				$extracted['publish_date'] = self::mb_limit( $d, 50 );
			} else {
				$extracted['publish_date'] = self::mb_limit( (string) $post->post_date, 50 );
			}
		}
		return $extracted;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return list<string>
	 */
	private static function labels_from_post_terms( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$names = array();
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax => $obj ) {
			if ( empty( $obj->public ) ) {
				continue;
			}
			$terms = get_the_terms( $post_id, $tax );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$names[] = $t->name;
				}
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Parse synthetic full document built from post content + optional Rank Math head.
	 *
	 * @param string $html HTML.
	 * @return array<string, mixed>
	 */
	private static function extract_content( string $html ) {
		$result = array(
			'title'            => '',
			'meta_description' => '',
			'og_description'   => '',
			'h1'               => array(),
			'h2s'              => array(),
			'h3s'              => array(),
			'paragraphs'       => array(),
			'images'           => array(),
			'labels'           => array(),
			'publish_date'     => '',
			'author'           => '',
		);

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		$titles = $xpath->query( '//title' );
		if ( $titles && $titles->length > 0 && $titles->item( 0 ) ) {
			$result['title'] = trim( $titles->item( 0 )->textContent );
		}

		$meta_desc = $xpath->query( '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]' );
		if ( $meta_desc ) {
			foreach ( $meta_desc as $node ) {
				if ( $node instanceof DOMElement ) {
					$result['meta_description'] = (string) $node->getAttribute( 'content' );
					break;
				}
			}
		}

		$og_desc = $xpath->query( '//meta[@property="og:description"]' );
		if ( $og_desc ) {
			foreach ( $og_desc as $node ) {
				if ( $node instanceof DOMElement ) {
					$result['og_description'] = (string) $node->getAttribute( 'content' );
					break;
				}
			}
		}

		$author_node = $xpath->query( "//*[contains(@class,'author') or contains(@class,'byline')] | //span[@itemprop='author'] | //a[@rel='author']" )->item( 0 );
		if ( $author_node ) {
			$result['author'] = self::mb_limit( trim( $author_node->textContent ), 100 );
		}

		$date_node = $xpath->query( "//*[@itemprop='datePublished'] | //meta[@name='article:published_time'] | //*[contains(@class,'published') or contains(@class,'post-date') or contains(@class,'entry-date')]" )->item( 0 );
		if ( $date_node && $date_node instanceof DOMElement ) {
			$c = $date_node->getAttribute( 'content' );
			if ( '' === $c ) {
				$c = $date_node->getAttribute( 'datetime' );
			}
			if ( '' === $c ) {
				$c = trim( $date_node->textContent );
			}
			$result['publish_date'] = self::mb_limit( $c, 50 );
		}

		$body = $xpath->query(
			"//*[contains(@class,'entry-content') or contains(@class,'post-content') or contains(@class,'article-content')]"
		)->item( 0 );
		if ( ! $body ) {
			$body = $xpath->query( '//article' )->item( 0 );
		}

		$scope = $body;
		if ( ! $scope ) {
			$scope = $dom->documentElement;
		}

		$sx = $scope ? new DOMXPath( $dom ) : null;
		if ( $scope && $sx ) {
			$h1n = $sx->query( './/h1', $scope );
			foreach ( $h1n ? iterator_to_array( $h1n ) : array() as $h ) {
				$t = trim( $h->textContent );
				if ( '' !== $t ) {
					$result['h1'][] = $t;
				}
			}
			$h2n = $sx->query( './/h2', $scope );
			foreach ( $h2n ? iterator_to_array( $h2n ) : array() as $h ) {
				$t = trim( $h->textContent );
				if ( '' !== $t ) {
					$result['h2s'][] = $t;
				}
			}
			$h3n = $sx->query( './/h3', $scope );
			foreach ( $h3n ? iterator_to_array( $h3n ) : array() as $h ) {
				$t = trim( $h->textContent );
				if ( '' !== $t ) {
					$result['h3s'][] = $t;
				}
			}
			$pnodes = $sx->query( './/p', $scope );
			foreach ( $pnodes ? iterator_to_array( $pnodes ) : array() as $p ) {
				$text = preg_replace( '/\s+/u', ' ', trim( $p->textContent ) );
				$w    = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
				if ( count( $w ) > 8 ) {
					$result['paragraphs'][] = $text;
				}
			}
			$imgs = $sx->query( './/img', $scope );
			foreach ( $imgs ? iterator_to_array( $imgs ) : array() as $img ) {
				if ( ! $img instanceof DOMElement ) {
					continue;
				}
				$src = $img->getAttribute( 'src' );
				if ( '' === $src ) {
					$src = $img->getAttribute( 'data-src' );
				}
				$result['images'][] = array(
					'src'     => $src,
					'alt'     => $img->getAttribute( 'alt' ),
					'width'   => $img->getAttribute( 'width' ),
					'height'  => $img->getAttribute( 'height' ),
					'loading' => $img->getAttribute( 'loading' ),
				);
			}
		}

		return $result;
	}

	/**
	 * @param string $html HTML.
	 * @return list<array<string, mixed>>
	 */
	private static function extract_structured_data( string $html ) {
		$blocks = array();
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		foreach ( $dom->getElementsByTagName( 'script' ) as $script ) {
			if ( ! $script instanceof DOMElement ) {
				continue;
			}
			if ( 'application/ld+json' !== $script->getAttribute( 'type' ) ) {
				continue;
			}
			$raw = trim( $script->textContent );
			if ( '' === $raw ) {
				continue;
			}
			$data = json_decode( $raw, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$blocks[] = array(
					'error'       => 'invalid_json',
					'raw_snippet' => self::mb_limit( $raw, 120 ),
				);
				continue;
			}
			$nodes = self::ld_iter_nodes( $data );
			foreach ( $nodes as $node ) {
				$schema_type = $node['@type'] ?? 'Unknown';
				if ( is_array( $schema_type ) ) {
					$schema_type = $schema_type[0] ?? 'Unknown';
				}
				$st     = (string) $schema_type;
				$status = 'active';
				$note   = '';
				if ( in_array( $st, self::deprecated_schema(), true ) ) {
					$status = 'deprecated';
					$note   = $st . ' was deprecated/removed from rich results. Remove or replace.';
				} elseif ( in_array( $st, self::restricted_schema(), true ) ) {
					$status = 'restricted';
					$note   = $st . ' is restricted to government/healthcare authority sites only.';
				}
				$blocks[] = array(
					'@type'       => $st,
					'@context'    => isset( $node['@context'] ) ? $node['@context'] : '',
					'status'      => $status,
					'note'        => $note,
					'has_context' => isset( $node['@context'] ),
					'has_type'    => isset( $node['@type'] ),
					'raw'         => $node,
				);
			}
		}
		return $blocks;
	}

	/**
	 * @param mixed $data JSON-LD decoded.
	 * @return list<array<string, mixed>>
	 */
	private static function ld_iter_nodes( $data ) {
		$out = array();
		if ( ! is_array( $data ) ) {
			return $out;
		}
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $item ) {
				if ( is_array( $item ) ) {
					$out[] = $item;
				}
			}
			return $out;
		}
		if ( self::is_sequential_array( $data ) ) {
			foreach ( $data as $item ) {
				$out = array_merge( $out, self::ld_iter_nodes( $item ) );
			}
			return $out;
		}
		$out[] = $data;
		return $out;
	}

	/**
	 * PHP 7.4-safe list detector (sequential integer keys from 0).
	 *
	 * @param array<int|string, mixed> $arr Array.
	 * @return bool
	 */
	private static function is_sequential_array( array $arr ) {
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/**
	 * @return list<string>
	 */
	private static function deprecated_schema() {
		return array(
			'HowTo',
			'SpecialAnnouncement',
			'CourseInfo',
			'EstimatedSalary',
			'LearningVideo',
			'ClaimReview',
			'VehicleListing',
			'PracticeProblems',
		);
	}

	/**
	 * @return list<string>
	 */
	private static function restricted_schema() {
		return array( 'FAQPage' );
	}

	/**
	 * @param string $word Word.
	 * @return int
	 */
	private static function count_syllables( string $word ) {
		$word = strtolower( rtrim( $word, ".,!?;:" ) );
		if ( strlen( $word ) <= 3 ) {
			return 1;
		}
		$vowels     = 'aeiouy';
		$count      = 0;
		$prev_vowel = false;
		$len        = strlen( $word );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch      = $word[ $i ];
			$is_v    = false !== strpos( $vowels, $ch );
			if ( $is_v && ! $prev_vowel ) {
				++$count;
			}
			$prev_vowel = $is_v;
		}
		if ( 'e' === substr( $word, -1 ) ) {
			--$count;
		}
		return max( 1, $count );
	}

	/**
	 * @param string $text Full text.
	 * @return array<string, mixed>
	 */
	private static function compute_readability( string $text ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return array(
				'flesch_reading_ease' => null,
				'fkgl'                => null,
				'grade_label'         => 'N/A',
				'word_count'          => 0,
				'sentence_count'      => 0,
			);
		}

		$parts    = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = array();
		foreach ( $parts as $s ) {
			$s = trim( $s );
			if ( '' !== $s ) {
				$sentences[] = $s;
			}
		}
		$sentence_count = max( 1, count( $sentences ) );
		preg_match_all( "/\b[a-zA-Z'-]+\b/u", $text, $mw );
		$words = $mw[0] ?? array();
		$word_count = max( 1, count( $words ) );
		$syllable_count = 0;
		foreach ( $words as $w ) {
			$syllable_count += self::count_syllables( $w );
		}
		$avg_sentence_len       = $word_count / $sentence_count;
		$avg_syllables_per_word = $syllable_count / $word_count;
		$fre                      = 206.835 - 1.015 * $avg_sentence_len - 84.6 * $avg_syllables_per_word;
		$fkgl                     = 0.39 * $avg_sentence_len + 11.8 * $avg_syllables_per_word - 15.59;
		$fre                      = round( max( 0, min( 100, $fre ) ), 1 );
		$fkgl                     = round( max( 0, $fkgl ), 1 );

		if ( $fre >= 70 ) {
			$grade = 'Easy (suitable for general audience)';
		} elseif ( $fre >= 50 ) {
			$grade = 'Medium (suitable for high school / college)';
		} else {
			$grade = 'Difficult (technical / specialist audience)';
		}

		return array(
			'flesch_reading_ease'   => $fre,
			'fkgl'                  => $fkgl,
			'grade_label'           => $grade,
			'word_count'            => $word_count,
			'sentence_count'        => $sentence_count,
			'avg_sentence_length'   => round( $avg_sentence_len, 1 ),
			'avg_syllables_per_word' => round( $avg_syllables_per_word, 2 ),
		);
	}

	/**
	 * @return list<string>
	 */
	private static function stop_words() {
		return array(
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
			'with', 'by', 'of', 'from', 'as', 'is', 'are', 'was', 'were', 'be',
			'been', 'this', 'that', 'these', 'those', 'it', 'he', 'she', 'they',
			'we', 'you', 'i', 'your', 'my', 'their', 'our', 'its', 'which', 'who',
			'whom', 'whose', 'what', 'where', 'when', 'why', 'how', 'all', 'any',
			'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no',
			'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'can',
			'will', 'just', 'should', 'have', 'has', 'had', 'do', 'does', 'did',
			'get', 'got', 'make', 'use', 'used', 'also', 'about', 'into', 'then',
			'there', 'would', 'could', 'here',
		);
	}

	/**
	 * @param string $text Text.
	 * @param int    $top_n Top.
	 * @return list<string>
	 */
	private static function extract_keywords_frequency( string $text, int $top_n = 12 ) {
		preg_match_all( '/\b[a-z]{3,}\b/', strtolower( $text ), $m );
		$words    = $m[0] ?? array();
		$stops    = array_flip( self::stop_words() );
		$filtered = array();
		foreach ( $words as $w ) {
			if ( ! isset( $stops[ $w ] ) ) {
				$filtered[] = $w;
			}
		}

		$unigrams = array_count_values( $filtered );
		$bigrams  = array();
		$n        = count( $filtered );
		for ( $i = 0; $i < $n - 1; $i++ ) {
			$b = $filtered[ $i ] . ' ' . $filtered[ $i + 1 ];
			if ( ! isset( $bigrams[ $b ] ) ) {
				$bigrams[ $b ] = 0;
			}
			++$bigrams[ $b ];
		}
		$trigrams = array();
		for ( $i = 0; $i < $n - 2; $i++ ) {
			$t = $filtered[ $i ] . ' ' . $filtered[ $i + 1 ] . ' ' . $filtered[ $i + 2 ];
			if ( ! isset( $trigrams[ $t ] ) ) {
				$trigrams[ $t ] = 0;
			}
			++$trigrams[ $t ];
		}

		$scored = array();
		foreach ( $unigrams as $term => $cnt ) {
			if ( $cnt > 3 ) {
				$scored[] = array( $term, (float) $cnt );
			}
		}
		foreach ( $bigrams as $term => $cnt ) {
			if ( $cnt > 1 ) {
				$scored[] = array( $term, $cnt * 3.0 );
			}
		}
		foreach ( $trigrams as $term => $cnt ) {
			if ( $cnt > 1 ) {
				$scored[] = array( $term, $cnt * 5.0 );
			}
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				if ( $a[1] === $b[1] ) {
					return 0;
				}
				return ( $a[1] < $b[1] ) ? 1 : -1;
			}
		);

		$all_terms = array();
		foreach ( $scored as $row ) {
			$all_terms[] = $row[0];
		}
		$final = array();
		foreach ( $scored as $row ) {
			$term = $row[0];
			$skip = false;
			foreach ( array_slice( $all_terms, 0, $top_n * 3 ) as $other ) {
				if ( $term !== $other && false !== strpos( $other, $term ) ) {
					$skip = true;
					break;
				}
			}
			if ( ! $skip ) {
				$final[] = $term;
			}
			if ( count( $final ) >= $top_n ) {
				break;
			}
		}
		return $final;
	}

	/**
	 * @param string $query Query.
	 * @return list<string>
	 */
	private static function get_google_autocomplete( string $query ) {
		$url = 'https://suggestqueries.google.com/complete/search?client=chrome&q=' . rawurlencode( $query );
		$r   = wp_remote_get(
			$url,
			array(
				'timeout'    => 6,
				'user-agent' => 'Mozilla/5.0 (compatible; WEBO-MCP/1.0)',
			)
		);
		if ( is_wp_error( $r ) ) {
			return array();
		}
		$body = wp_remote_retrieve_body( $r );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data[1] ) || ! is_array( $data[1] ) ) {
			return array();
		}
		$list = array();
		foreach ( $data[1] as $item ) {
			$list[] = (string) $item;
		}
		return $list;
	}

	/**
	 * @param array<string, mixed>        $content         Extracted content.
	 * @param list<array<string, mixed>> $structured_data LD blocks.
	 * @param array<string, mixed>       $readability     Metrics.
	 * @return list<array<string, string>>
	 */
	private static function detect_seo_issues( array $content, array $structured_data, array $readability ) {
		$issues     = array();
		$title      = isset( $content['title'] ) ? (string) $content['title'] : '';
		$meta       = isset( $content['meta_description'] ) ? (string) $content['meta_description'] : '';
		$h1s        = isset( $content['h1'] ) && is_array( $content['h1'] ) ? $content['h1'] : array();
		$word_count = isset( $readability['word_count'] ) ? (int) $readability['word_count'] : 0;
		$images     = isset( $content['images'] ) && is_array( $content['images'] ) ? $content['images'] : array();

		if ( '' === $title ) {
			$issues[] = array(
				'severity' => 'Critical',
				'area'     => 'Title',
				'finding'  => 'No <title> tag found.',
				'fix'      => 'Add a descriptive title tag (50-60 chars).',
			);
		} elseif ( strlen( $title ) < 30 ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'Title',
				'finding'  => 'Title too short (' . strlen( $title ) . ' chars).',
				'fix'      => 'Expand title to 50-60 characters with primary keyword near the start.',
			);
		} elseif ( strlen( $title ) > 65 ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'Title',
				'finding'  => 'Title may be truncated in SERPs (' . strlen( $title ) . ' chars).',
				'fix'      => 'Keep title under 60 characters.',
			);
		}

		if ( '' === $meta ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'Meta Description',
				'finding'  => 'No meta description found.',
				'fix'      => 'Add a compelling 120-155 character meta description with a CTA.',
			);
		} elseif ( strlen( $meta ) < 100 ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'Meta Description',
				'finding'  => 'Meta description too short (' . strlen( $meta ) . ' chars).',
				'fix'      => 'Expand to 120-155 characters.',
			);
		} elseif ( strlen( $meta ) > 165 ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'Meta Description',
				'finding'  => 'Meta description may be truncated (' . strlen( $meta ) . ' chars).',
				'fix'      => 'Keep under 155 characters.',
			);
		}

		if ( count( $h1s ) === 0 ) {
			$issues[] = array(
				'severity' => 'Critical',
				'area'     => 'H1',
				'finding'  => 'No H1 tag detected.',
				'fix'      => 'Add a single, descriptive H1 containing the primary keyword.',
			);
		} elseif ( count( $h1s ) > 1 ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'H1',
				'finding'  => 'Multiple H1 tags found (' . count( $h1s ) . ').',
				'fix'      => 'Use exactly one H1 per page.',
			);
		}

		if ( $word_count < 300 ) {
			$issues[] = array(
				'severity' => 'Critical',
				'area'     => 'Content',
				'finding'  => 'Very thin content (' . $word_count . ' words).',
				'fix'      => 'Expand content to at least 1,500 words for blog posts.',
			);
		} elseif ( $word_count < 1000 ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'Content',
				'finding'  => 'Content may be thin for a blog post (' . $word_count . ' words).',
				'fix'      => 'Aim for 1,500+ words of substantive, unique content.',
			);
		}

		if ( empty( $content['author'] ) ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'E-E-A-T',
				'finding'  => 'No author attribution detected.',
				'fix'      => 'Add a visible author byline with credentials.',
			);
		}

		if ( empty( $content['publish_date'] ) ) {
			$issues[] = array(
				'severity' => 'Info',
				'area'     => 'Freshness',
				'finding'  => 'No publish date detected in markup.',
				'fix'      => 'Add visible publish/update date and datePublished in Article schema.',
			);
		}

		$missing_alt = 0;
		foreach ( $images as $img ) {
			if ( empty( $img['alt'] ) ) {
				++$missing_alt;
			}
		}
		if ( $missing_alt > 0 ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'Images',
				'finding'  => $missing_alt . ' image(s) missing alt text.',
				'fix'      => 'Add descriptive alt text (10-125 chars) to all non-decorative images.',
			);
		}

		$no_lazy = 0;
		foreach ( $images as $img ) {
			if ( ! empty( $img['src'] ) && ( ! isset( $img['loading'] ) || 'lazy' !== $img['loading'] ) ) {
				++$no_lazy;
			}
		}
		if ( $no_lazy > 3 ) {
			$issues[] = array(
				'severity' => 'Info',
				'area'     => 'Images',
				'finding'  => $no_lazy . " image(s) without loading='lazy'.",
				'fix'      => "Add loading='lazy' to below-the-fold images to improve LCP.",
			);
		}

		if ( count( $structured_data ) === 0 ) {
			$issues[] = array(
				'severity' => 'Warning',
				'area'     => 'Schema',
				'finding'  => 'No JSON-LD structured data found.',
				'fix'      => 'Add Article/BlogPosting schema with author, datePublished, image, and publisher.',
			);
		} else {
			foreach ( $structured_data as $sd ) {
				if ( isset( $sd['status'] ) && 'deprecated' === $sd['status'] ) {
					$issues[] = array(
						'severity' => 'Critical',
						'area'     => 'Schema',
						'finding'  => isset( $sd['note'] ) ? (string) $sd['note'] : 'Deprecated schema type.',
						'fix'      => 'Remove deprecated schema type immediately.',
					);
				} elseif ( isset( $sd['status'] ) && 'restricted' === $sd['status'] ) {
					$issues[] = array(
						'severity' => 'Warning',
						'area'     => 'Schema',
						'finding'  => isset( $sd['note'] ) ? (string) $sd['note'] : 'Restricted schema type.',
						'fix'      => 'Remove FAQPage schema unless you are a government or healthcare authority site.',
					);
				}
			}
		}

		$fre = isset( $readability['flesch_reading_ease'] ) ? $readability['flesch_reading_ease'] : null;
		if ( null !== $fre && $fre < 30 ) {
			$issues[] = array(
				'severity' => 'Info',
				'area'     => 'Readability',
				'finding'  => 'Content is very difficult to read (Flesch score: ' . $fre . ').',
				'fix'      => 'Simplify sentences. Aim for Flesch score ≥ 50 for broader audience reach.',
			);
		}

		return $issues;
	}

	/**
	 * @param list<array<string, string>> $issues Issues.
	 * @return array<string, int|string>
	 */
	private static function compute_seo_score( array $issues ) {
		$crit = 0;
		$warn = 0;
		$info = 0;
		foreach ( $issues as $i ) {
			$s = $i['severity'] ?? '';
			if ( 'Critical' === $s ) {
				++$crit;
			} elseif ( 'Warning' === $s ) {
				++$warn;
			} elseif ( 'Info' === $s ) {
				++$info;
			}
		}
		$raw   = 100 - ( $crit * 18 ) - ( $warn * 7 ) - ( $info * 2 );
		$value = (int) max( 0, min( 100, round( $raw ) ) );
		return array(
			'value'           => $value,
			'scale'           => '0-100',
			'critical_count'  => $crit,
			'warning_count'   => $warn,
			'info_count'      => $info,
		);
	}

	/**
	 * @param string                     $full_text_lower Lowercase full text.
	 * @param list<string>               $related_kws     Related keywords.
	 * @param list<string>               $extracted_kws   Extracted.
	 * @param array<string, mixed>       $content         Content blob.
	 * @param array<string, mixed>       $readability     Readability.
	 * @return list<string>
	 */
	private static function compute_content_gaps(
		string $full_text_lower,
		array $related_kws,
		array $extracted_kws,
		array $content,
		array $readability
	) {
		$gaps = array();
		$wc   = isset( $readability['word_count'] ) ? (int) $readability['word_count'] : 0;
		if ( $wc < 1000 ) {
			$gaps[] = 'Depth: word count is below the tool blog-style warning threshold; consider expanding with unique sections and evidence.';
		}
		$h2s = isset( $content['h2s'] ) && is_array( $content['h2s'] ) ? $content['h2s'] : array();
		if ( count( $h2s ) === 0 ) {
			$gaps[] = 'Structure: no H2 headings detected in the main content scope; add subtopic sections with H2s.';
		}
		foreach ( array_slice( $related_kws, 0, 15 ) as $phrase ) {
			$pl = strtolower( trim( $phrase ) );
			if ( '' !== $pl && false === strpos( $full_text_lower, $pl ) ) {
				$gaps[] = 'Coverage gap (suggested phrase not in body text): ' . $phrase;
			}
		}
		foreach ( array_slice( $extracted_kws, 0, 8 ) as $phrase ) {
			if ( false === strpos( $full_text_lower, $phrase ) ) {
				$all_miss = true;
				foreach ( explode( ' ', $phrase ) as $tok ) {
					if ( strlen( $tok ) <= 3 ) {
						continue;
					}
					if ( false !== strpos( $full_text_lower, $tok ) ) {
						$all_miss = false;
						break;
					}
				}
				if ( $all_miss ) {
					$gaps[] = 'On-page emphasis: high-scoring extracted phrase thin or absent in running text: ' . $phrase;
				}
			}
		}
		if ( empty( $content['meta_description'] ) ) {
			$gaps[] = 'Snippet gap: missing meta description reduces controlled SERP messaging.';
		}
		return $gaps;
	}

	/**
	 * Rank Math SEO meta: prefer Abilities API tool parity via `webo-rank-math/get-post-seo-meta`, then addon helper, then raw post_meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array{seo_meta: array<string, mixed>, source: string}
	 */
	private static function collect_rank_math_bundle( int $post_id ) {
		$bundle = array(
			'seo_meta' => array(),
			'source'   => 'none',
		);

		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'webo-rank-math/get-post-seo-meta' ) : null;
		if ( $ability && is_object( $ability ) && method_exists( $ability, 'execute' ) ) {
			$result = $ability->execute( array( 'post_id' => $post_id ) );
			if ( ! is_wp_error( $result ) && is_array( $result ) && isset( $result['seo_meta'] ) && is_array( $result['seo_meta'] ) ) {
				$bundle['seo_meta'] = $result['seo_meta'];
				$bundle['source']   = 'ability';
				return apply_filters( 'webo_mcp_seo_article_rank_math_bundle', $bundle, $post_id );
			}
		}

		if ( function_exists( 'webo_rank_math_collect_post_meta' ) ) {
			$meta = webo_rank_math_collect_post_meta( $post_id );
			if ( is_array( $meta ) && ! empty( $meta ) ) {
				$bundle['seo_meta'] = $meta;
				$bundle['source']   = 'addon_helper';
				return apply_filters( 'webo_mcp_seo_article_rank_math_bundle', $bundle, $post_id );
			}
		}

		$native = self::rank_math_meta_from_post( $post_id );
		if ( ! empty( $native ) ) {
			$bundle['seo_meta'] = $native;
			$bundle['source']   = 'post_meta';
		}

		return apply_filters( 'webo_mcp_seo_article_rank_math_bundle', $bundle, $post_id );
	}

	/**
	 * Known Rank Math post meta keys (aligned with webo-mcp-rank-math helpers).
	 *
	 * @return list<string>
	 */
	private static function rank_math_meta_key_list() {
		return array(
			'rank_math_title',
			'rank_math_description',
			'rank_math_focus_keyword',
			'rank_math_canonical_url',
			'rank_math_robots',
			'rank_math_facebook_title',
			'rank_math_facebook_description',
			'rank_math_facebook_image',
			'rank_math_twitter_title',
			'rank_math_twitter_description',
			'rank_math_twitter_image',
			'rank_math_twitter_card_type',
			'rank_math_schema_type',
			'rank_math_schema_Article',
			'rank_math_schema_Product',
			'rank_math_pillar_content',
			'rank_math_seo_score',
			'rank_math_primary_category',
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private static function rank_math_meta_from_post( int $post_id ) {
		$out = array();
		foreach ( self::rank_math_meta_key_list() as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' !== $value && null !== $value ) {
				$out[ $key ] = maybe_unserialize( $value );
			}
		}
		return $out;
	}

	/**
	 * @param bool  $include_flag Whether Rank Math was requested.
	 * @param array $rank_bundle  Bundle from collect_rank_math_bundle.
	 * @return array<string, mixed>
	 */
	private static function build_rank_math_result_block( bool $include_flag, array $rank_bundle ) {
		if ( ! $include_flag ) {
			return array(
				'used'     => false,
				'source'   => 'none',
				'seo_meta' => array(),
				'note'     => __( 'Rank Math merge disabled (include_rank_math false).', 'webo-mcp' ),
			);
		}

		return array(
			'used'              => true,
			'source'            => isset( $rank_bundle['source'] ) ? (string) $rank_bundle['source'] : 'none',
			'seo_meta'          => isset( $rank_bundle['seo_meta'] ) && is_array( $rank_bundle['seo_meta'] ) ? $rank_bundle['seo_meta'] : array(),
			'rank_math_version' => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : null,
		);
	}

	/**
	 * @param string $s   String.
	 * @param int    $max Max length.
	 * @return string
	 */
	private static function mb_limit( string $s, int $max ) {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $s, 0, $max );
		}
		return substr( $s, 0, $max );
	}
}
