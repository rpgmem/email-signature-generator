<?php
/**
 * The regional offices and the central department.
 *
 * @package EmailSignatureGenerator
 */

declare(strict_types=1);

namespace EmailSignatureGenerator;

/**
 * Each office is a name and an acronym. A signature prints them together as
 * "Name / ACRONYM"; the acronym on its own identifies the office, so it is
 * required and unique.
 *
 * @phpstan-type Region array{name: string, acronym: string}
 */
final class Regions {

	/**
	 * Capitals and digits, as the offices write them (DREBT, SMESP).
	 */
	public const ACRONYM_PATTERN = '/^[A-Z0-9]{1,12}$/';

	/**
	 * Separator between the name and the acronym in a printed label.
	 */
	public const SEPARATOR = ' / ';

	/**
	 * The offices a new install starts with.
	 *
	 * @return list<Region>
	 */
	public static function defaults(): array {
		$out = array();
		foreach ( array(
			'DREBT' => 'Diretoria Regional de Educação Butantã',
			'DRECL' => 'Diretoria Regional de Educação Campo Limpo',
			'DRECS' => 'Diretoria Regional de Educação Capela do Socorro',
			'DREFB' => 'Diretoria Regional de Educação Freguesia Brasilândia',
			'DREG'  => 'Diretoria Regional de Educação Guaianases',
			'DREIP' => 'Diretoria Regional de Educação Ipiranga',
			'DREIQ' => 'Diretoria Regional de Educação Itaquera',
			'DREJT' => 'Diretoria Regional de Educação Jaçanã Tremembé',
			'DREPE' => 'Diretoria Regional de Educação Penha',
			'DREPJ' => 'Diretoria Regional de Educação Pirituba Jaraguá',
			'DRESA' => 'Diretoria Regional de Educação Santo Amaro',
			'DRESM' => 'Diretoria Regional de Educação São Mateus',
			'DREMP' => 'Diretoria Regional de Educação São Miguel',
			'SMESP' => 'Secretaria Municipal de Educação de São Paulo',
		) as $acronym => $name ) {
			$out[] = array(
				'name'    => $name,
				'acronym' => $acronym,
			);
		}
		return $out;
	}

	/**
	 * How a signature prints an office.
	 *
	 * @param array{name: string, acronym: string} $region Office.
	 */
	public static function label( array $region ): string {
		return $region['name'] . self::SEPARATOR . $region['acronym'];
	}

	/**
	 * The printed labels, in order.
	 *
	 * @param list<array{name: string, acronym: string}> $regions Offices.
	 * @return list<string>
	 */
	public static function labels( array $regions ): array {
		return array_map( array( self::class, 'label' ), $regions );
	}

	/**
	 * Clean a list of offices from the settings form or from storage.
	 *
	 * Accepts the current shape (name and acronym per row) and the one stored
	 * before acronyms were separate: "Name / ACRONYM" lines, as a list or as
	 * one text block, split at the last separator. A row with an empty name,
	 * a missing or malformed acronym, or an acronym already used is left out
	 * and reported, never stored half right.
	 *
	 * @param mixed $input Raw rows.
	 * @return array{regions: list<Region>, rejected: list<string>} Kept rows, and a description of each row left out.
	 */
	public static function parse( $input ): array {
		if ( is_string( $input ) ) {
			$input = preg_split( '/\R/', $input );
		}

		$regions  = array();
		$rejected = array();
		$seen     = array();
		foreach ( is_array( $input ) ? $input : array() as $row ) {
			if ( is_array( $row ) ) {
				$name    = (string) ( $row['name'] ?? '' );
				$acronym = (string) ( $row['acronym'] ?? '' );
			} elseif ( is_scalar( $row ) ) {
				list( $name, $acronym ) = self::split( (string) $row );
			} else {
				continue;
			}

			$name    = sanitize_text_field( $name );
			$acronym = strtoupper( (string) preg_replace( '/\s+/', '', sanitize_text_field( $acronym ) ) );
			if ( '' === $name && '' === $acronym ) {
				continue;
			}

			$shown = '' !== $name ? $name : $acronym;
			if ( '' === $name || ! preg_match( self::ACRONYM_PATTERN, $acronym ) ) {
				$rejected[] = $shown;
				continue;
			}
			if ( isset( $seen[ $acronym ] ) ) {
				$rejected[] = $shown;
				continue;
			}

			$seen[ $acronym ] = true;
			$regions[]        = array(
				'name'    => $name,
				'acronym' => $acronym,
			);
		}

		return array(
			'regions'  => $regions,
			'rejected' => $rejected,
		);
	}

	/**
	 * Split a printed label at its last separator.
	 *
	 * @param string $label "Name / ACRONYM".
	 * @return array{0: string, 1: string} Name and acronym; the acronym is '' without a separator.
	 */
	private static function split( string $label ): array {
		$at = strrpos( $label, '/' );
		if ( false === $at ) {
			return array( trim( $label ), '' );
		}
		return array( trim( substr( $label, 0, $at ) ), trim( substr( $label, $at + 1 ) ) );
	}
}
