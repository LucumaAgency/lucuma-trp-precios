<?php
/**
 * Plugin Name: Lucuma TRP Precios
 * Description: Precios por idioma de TranslatePress. Un precio por plan y por moneda; el shortcode [lucuma_precio plan="..."] muestra S/ en español y USD en /en/.
 * Version: 1.0.0
 * Author: Lucuma Agency
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LTP_VERSION', '1.0.0' );
define( 'LTP_OPTION_PLANES', 'ltp_planes' );
define( 'LTP_OPTION_MONEDAS', 'ltp_monedas' );

/* -------------------------------------------------------------------------
 * Datos
 * ---------------------------------------------------------------------- */

/**
 * Monedas y a qué idioma de TranslatePress corresponde cada una.
 * Formato: código => [simbolo, posicion (antes|despues), espacio, miles, decimales, idiomas[]].
 */
function ltp_monedas_por_defecto() {
	return array(
		'PEN' => array(
			'simbolo'   => 'S/',
			'posicion'  => 'antes',
			'espacio'   => true,
			'miles'     => '.',
			'decimales' => ',',
			'idiomas'   => array( 'es_PE', 'es_ES', 'es' ),
		),
		'USD' => array(
			'simbolo'   => '$',
			'posicion'  => 'antes',
			'espacio'   => false,
			'miles'     => ',',
			'decimales' => '.',
			'idiomas'   => array( 'en_US', 'en_GB', 'en' ),
		),
	);
}

function ltp_monedas() {
	$guardadas = get_option( LTP_OPTION_MONEDAS );
	$monedas   = is_array( $guardadas ) && $guardadas ? $guardadas : ltp_monedas_por_defecto();
	return apply_filters( 'ltp_monedas', $monedas );
}

/**
 * Planes: clave => [nombre, precios => [PEN => 2000, USD => 1200], sufijo => [PEN => '/mes', USD => '/mo']].
 */
function ltp_planes() {
	$planes = get_option( LTP_OPTION_PLANES, array() );
	return is_array( $planes ) ? $planes : array();
}

/** Idioma actual según TranslatePress; si no está activo, el locale de WordPress. */
function ltp_idioma_actual() {
	global $TRP_LANGUAGE;
	if ( ! empty( $TRP_LANGUAGE ) ) {
		return $TRP_LANGUAGE;
	}
	return determine_locale();
}

/** Moneda que corresponde a un idioma (por locale exacto, luego por prefijo "en", "es"). */
function ltp_moneda_para_idioma( $idioma = null ) {
	$idioma  = $idioma ? $idioma : ltp_idioma_actual();
	$monedas = ltp_monedas();
	$prefijo = strtolower( substr( $idioma, 0, 2 ) );

	foreach ( $monedas as $codigo => $m ) {
		if ( in_array( $idioma, (array) $m['idiomas'], true ) ) {
			return $codigo;
		}
	}
	foreach ( $monedas as $codigo => $m ) {
		if ( in_array( $prefijo, (array) $m['idiomas'], true ) ) {
			return $codigo;
		}
	}
	return apply_filters( 'ltp_moneda_por_defecto', array_key_first( $monedas ), $idioma );
}

/** Formatea un monto: 2000 PEN => "S/ 2.000", 1200 USD => "$1,200". Decimales solo si los hay. */
function ltp_formatear( $monto, $codigo ) {
	$monedas = ltp_monedas();
	if ( ! isset( $monedas[ $codigo ] ) ) {
		return '';
	}
	$m        = $monedas[ $codigo ];
	$monto    = (float) $monto;
	$dec      = ( floor( $monto ) == $monto ) ? 0 : 2;
	$numero   = number_format( $monto, $dec, $m['decimales'], $m['miles'] );
	$separa   = ! empty( $m['espacio'] ) ? ' ' : '';
	$texto    = ( 'despues' === $m['posicion'] ) ? $numero . $separa . $m['simbolo'] : $m['simbolo'] . $separa . $numero;
	return apply_filters( 'ltp_formatear', $texto, $monto, $codigo );
}

/**
 * Precio de un plan en el idioma actual.
 *
 * @param string $plan   Clave del plan.
 * @param string $campo  'texto' (S/ 2.000/mes), 'numero' (2000), 'moneda' (PEN), 'simbolo' (S/).
 * @return string|null   null si el plan o el precio no existe.
 */
function lucuma_trp_precio( $plan, $campo = 'texto' ) {
	$planes = ltp_planes();
	$plan   = sanitize_key( $plan );
	$codigo = ltp_moneda_para_idioma();

	if ( 'moneda' === $campo ) {
		return $codigo;
	}
	if ( 'simbolo' === $campo ) {
		$monedas = ltp_monedas();
		return isset( $monedas[ $codigo ] ) ? $monedas[ $codigo ]['simbolo'] : '';
	}
	if ( ! isset( $planes[ $plan ]['precios'][ $codigo ] ) || '' === $planes[ $plan ]['precios'][ $codigo ] ) {
		return null;
	}
	$monto = $planes[ $plan ]['precios'][ $codigo ];
	if ( 'numero' === $campo ) {
		return (string) $monto;
	}
	$sufijo = isset( $planes[ $plan ]['sufijo'][ $codigo ] ) ? $planes[ $plan ]['sufijo'][ $codigo ] : '';
	return ltp_formatear( $monto, $codigo ) . $sufijo;
}

/* -------------------------------------------------------------------------
 * Shortcodes
 * ---------------------------------------------------------------------- */

/**
 * [lucuma_precio plan="web-basica"]            => S/ 2.000   |  $1,200
 * [lucuma_precio plan="seo" campo="numero"]    => 900        |  450
 * [lucuma_precio campo="moneda"]               => PEN        |  USD
 *
 * Va dentro de un span con data-no-translation para que TranslatePress no lo toque.
 */
function ltp_shortcode_precio( $atts ) {
	$atts  = shortcode_atts(
		array(
			'plan'  => '',
			'campo' => 'texto',
			'clase' => '',
		),
		$atts,
		'lucuma_precio'
	);
	$valor = lucuma_trp_precio( $atts['plan'], $atts['campo'] );

	if ( null === $valor ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<span class="ltp-precio ltp-falta" style="background:#ffe08a;color:#000;padding:0 4px" data-no-translation>'
				. esc_html( sprintf( '[precio sin definir: %s / %s]', $atts['plan'], ltp_moneda_para_idioma() ) ) . '</span>';
		}
		return '';
	}

	$codigo = ltp_moneda_para_idioma();
	$clases = trim( 'ltp-precio ltp-' . strtolower( $codigo ) . ' ' . sanitize_html_class( $atts['clase'] ) );
	$numero = in_array( $atts['campo'], array( 'texto', 'numero' ), true ) ? lucuma_trp_precio( $atts['plan'], 'numero' ) : '';

	return sprintf(
		'<span class="%s" data-moneda="%s"%s data-no-translation>%s</span>',
		esc_attr( $clases ),
		esc_attr( $codigo ),
		'' !== $numero ? ' data-monto="' . esc_attr( $numero ) . '"' : '',
		esc_html( $valor )
	);
}
add_shortcode( 'lucuma_precio', 'ltp_shortcode_precio' );

/* Bricks: permite {echo:lucuma_trp_precio(web-basica)} en datos dinámicos. */
add_filter(
	'bricks/code/echo_function_names',
	function ( $nombres ) {
		$nombres   = is_array( $nombres ) ? $nombres : array();
		$nombres[] = 'lucuma_trp_precio';
		return $nombres;
	}
);

/* Bricks no siempre ejecuta shortcodes en el elemento de texto básico. */
add_filter( 'bricks/frontend/render_data', 'ltp_render_shortcodes_bricks', 10, 1 );
function ltp_render_shortcodes_bricks( $html ) {
	if ( is_string( $html ) && false !== strpos( $html, '[lucuma_precio' ) ) {
		$html = do_shortcode( $html );
	}
	return $html;
}

/* -------------------------------------------------------------------------
 * Pantalla de ajustes: Ajustes > Precios por idioma
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Precios por idioma', 'Precios por idioma', 'manage_options', 'lucuma-trp-precios', 'ltp_pantalla_ajustes' );
	}
);

function ltp_guardar_desde_post() {
	if ( ! isset( $_POST['ltp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ltp_nonce'] ) ), 'ltp_guardar' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$filas   = isset( $_POST['ltp'] ) && is_array( $_POST['ltp'] ) ? wp_unslash( $_POST['ltp'] ) : array(); // phpcs:ignore
	update_option( LTP_OPTION_PLANES, ltp_sanear_planes( $filas ), false );
	add_settings_error( 'ltp', 'ok', 'Precios guardados.', 'updated' );
}

/** Acepta lista de filas [{clave,nombre,precios{},sufijo{}}] o mapa clave => datos. */
function ltp_sanear_planes( $entrada ) {
	$monedas = array_keys( ltp_monedas() );
	$planes  = array();
	foreach ( (array) $entrada as $k => $fila ) {
		if ( ! is_array( $fila ) ) {
			continue;
		}
		$clave = sanitize_key( isset( $fila['clave'] ) ? $fila['clave'] : $k );
		if ( '' === $clave || is_numeric( $clave ) ) {
			continue;
		}
		$plan = array(
			'nombre'  => sanitize_text_field( isset( $fila['nombre'] ) ? $fila['nombre'] : '' ),
			'precios' => array(),
			'sufijo'  => array(),
		);
		foreach ( $monedas as $codigo ) {
			$p = isset( $fila['precios'][ $codigo ] ) ? str_replace( ',', '.', trim( (string) $fila['precios'][ $codigo ] ) ) : '';
			$plan['precios'][ $codigo ] = ( '' !== $p && is_numeric( $p ) ) ? (float) $p : '';
			$plan['sufijo'][ $codigo ]  = sanitize_text_field( isset( $fila['sufijo'][ $codigo ] ) ? $fila['sufijo'][ $codigo ] : '' );
		}
		$planes[ $clave ] = $plan;
	}
	return $planes;
}

function ltp_pantalla_ajustes() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	ltp_guardar_desde_post();
	$planes  = ltp_planes();
	$monedas = ltp_monedas();
	$filas   = array();
	foreach ( $planes as $clave => $p ) {
		$filas[] = array_merge( array( 'clave' => $clave ), $p );
	}
	$filas[] = array( 'clave' => '', 'nombre' => '', 'precios' => array(), 'sufijo' => array() ); // fila vacía para añadir
	?>
	<div class="wrap">
		<h1>Precios por idioma</h1>
		<?php settings_errors( 'ltp' ); ?>
		<p>Cada plan tiene un precio por moneda. En la web se usa <code>[lucuma_precio plan="clave"]</code>: muestra la moneda del idioma de TranslatePress
			(<?php echo esc_html( implode( ' · ', array_map( function ( $c, $m ) { return $c . ' → ' . implode( ', ', $m['idiomas'] ); }, array_keys( $monedas ), $monedas ) ) ); ?>).
			El sufijo es opcional (ej. <code>/mes</code>, <code>/mo</code>). Para borrar un plan, vacía su clave.</p>
		<form method="post">
			<?php wp_nonce_field( 'ltp_guardar', 'ltp_nonce' ); ?>
			<table class="widefat striped" id="ltp-tabla">
				<thead><tr>
					<th>Clave</th><th>Nombre interno</th>
					<?php foreach ( $monedas as $codigo => $m ) : ?>
						<th><?php echo esc_html( $codigo . ' (' . $m['simbolo'] . ')' ); ?></th><th>Sufijo <?php echo esc_html( $codigo ); ?></th>
					<?php endforeach; ?>
					<th>Shortcode</th>
				</tr></thead>
				<tbody>
				<?php foreach ( $filas as $i => $f ) : ?>
					<tr>
						<td><input type="text" name="ltp[<?php echo (int) $i; ?>][clave]" value="<?php echo esc_attr( $f['clave'] ); ?>" placeholder="web-basica" style="width:140px"></td>
						<td><input type="text" name="ltp[<?php echo (int) $i; ?>][nombre]" value="<?php echo esc_attr( $f['nombre'] ); ?>" style="width:200px"></td>
						<?php foreach ( $monedas as $codigo => $m ) : ?>
							<td><input type="text" inputmode="decimal" name="ltp[<?php echo (int) $i; ?>][precios][<?php echo esc_attr( $codigo ); ?>]" value="<?php echo esc_attr( isset( $f['precios'][ $codigo ] ) ? $f['precios'][ $codigo ] : '' ); ?>" style="width:90px"></td>
							<td><input type="text" name="ltp[<?php echo (int) $i; ?>][sufijo][<?php echo esc_attr( $codigo ); ?>]" value="<?php echo esc_attr( isset( $f['sufijo'][ $codigo ] ) ? $f['sufijo'][ $codigo ] : '' ); ?>" style="width:70px"></td>
						<?php endforeach; ?>
						<td><?php if ( $f['clave'] ) : ?><code>[lucuma_precio plan="<?php echo esc_html( $f['clave'] ); ?>"]</code><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="ltp-agregar">Añadir fila</button></p>
			<?php submit_button( 'Guardar precios' ); ?>
		</form>
		<script>
		document.getElementById('ltp-agregar').addEventListener('click', function () {
			var tbody = document.querySelector('#ltp-tabla tbody');
			var ultima = tbody.lastElementChild;
			var nueva = ultima.cloneNode(true);
			var n = tbody.children.length;
			nueva.querySelectorAll('input').forEach(function (inp) {
				inp.name = inp.name.replace(/^ltp\[\d+\]/, 'ltp[' + n + ']');
				inp.value = '';
			});
			nueva.lastElementChild.innerHTML = '';
			tbody.appendChild(nueva);
		});
		</script>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * REST (solo administradores): GET/POST /wp-json/lucuma-trp-precios/v1/planes
 * POST reemplaza todos los planes; PATCH fusiona (añade o actualiza claves).
 * ---------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		$permiso = function () {
			return current_user_can( 'manage_options' );
		};
		register_rest_route(
			'lucuma-trp-precios/v1',
			'/planes',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $permiso,
					'callback'            => function () {
						return array(
							'version' => LTP_VERSION,
							'monedas' => ltp_monedas(),
							'planes'  => ltp_planes(),
						);
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $permiso,
					'callback'            => function ( WP_REST_Request $req ) {
						$planes = ltp_sanear_planes( (array) $req->get_param( 'planes' ) );
						update_option( LTP_OPTION_PLANES, $planes, false );
						return array( 'planes' => $planes );
					},
				),
				array(
					'methods'             => 'PATCH',
					'permission_callback' => $permiso,
					'callback'            => function ( WP_REST_Request $req ) {
						$planes = array_merge( ltp_planes(), ltp_sanear_planes( (array) $req->get_param( 'planes' ) ) );
						update_option( LTP_OPTION_PLANES, $planes, false );
						return array( 'planes' => $planes );
					},
				),
			)
		);
	}
);
