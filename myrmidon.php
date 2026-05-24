<?php
/**
 * Plugin Name: VGT Myrmidon Core (Zero Trust Endpoint)
 * Plugin URI:  https://visiongaiatechnology.de
 * Description: OMEGA PLATINUM RECORD: Kryptografischer Zero-Trust Endpoint für WordPress. Bietet X25519 ECDH, Ed25519 Signaturen und AES-256-GCM Verschlüsselung für API-Telemetrie.
 * Version:     1.0.7
 * Author:      VisionGaia Intelligence System
 * Author URI:  https://visiongaiatechnology.de
 * License:     AGPLv3
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: vgt-myrmidon
 */

declare(strict_types=1);

// STATUS: DIAMANT VGT SUPREME

/**
 * VISIONGAIATECHNOLOGY SENTINEL
 * MODUL: MYRMIDON (Endpoint Integrity) - OMEGA PLATINUM RECORD
 * STATUS: DIAMANT VGT SUPREME (MATHEMATISCHES MAXIMUM)
 *
 * CRYPTOGRAPHIC INVENTORY:
 * - Key Exchange: X25519 (ECDH Curve25519 scalar multiplication)
 * - Signatures: Ed25519 (RFC 8032)
 * - Symmetric Cipher: AES-256-GCM (Authenticated Encryption with Associated Data)
 * - Key Erasure: Sodium memory zeroing (sodium_memzero)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // SILENCE IS GOLDEN
}

// =========================================================================================
// 0. EXCEPTION HIERARCHY (PATTERN 1.5.A - MANDATORY SECURITY BOUNDARY)
// =========================================================================================
class MyrmidonException   extends \Exception {}
class ValidationException extends MyrmidonException {} // USER-FACING: Safe diagnostics
class SecurityException   extends MyrmidonException {} // INTERNAL: Sensitive cryptographic anomalies
class StorageException    extends MyrmidonException {} // INTERNAL: Database state faults

// =========================================================================================
// 1. DASHBOARD ROUTING (ADMIN UI)
// =========================================================================================
if ( is_admin() ) {
    add_action( 'admin_menu', static function(): void {
        add_menu_page(
            'VGT Myrmidon', 
            'Myrmidon ZTNA', 
            'manage_options', 
            'vgt-myrmidon', 
            static function(): void {
                $dashboard_path = plugin_dir_path( __FILE__ ) . 'includes/dashboard.php';
                if ( file_exists( $dashboard_path ) ) {
                    require_once $dashboard_path;
                } else {
                    echo '<div class="notice notice-error"><p>VGT Myrmidon: Dashboard-Modul nicht gefunden.</p></div>';
                }
            }, 
            'dashicons-shield', 
            80 
        );
    });
}

// =========================================================================================
// 2. CRYPTOGRAPHIC KERNEL & RUNTIME ENFORCEMENT
// =========================================================================================
final class VIS_Myrmidon {

    private static ?VIS_Myrmidon $instance = null;
    private const DB_VERSION = '1.0.7';
    
    private string $namespace = 'visiongaia/v1';
    private string $table_name;
    private string $vault_key_option = '_vis_myrmidon_vault_key';
    
    private int $rate_limit = 20; 
    private int $replay_window = 30;
    private string $cipher_algo = 'aes-256-gcm';

    public static function get_instance(): VIS_Myrmidon {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'vis_myrmidon_ledger';

        // DEPENDENCY GUARD: FAIL FAST
        if ( ! extension_loaded( 'sodium' ) || ! extension_loaded( 'openssl' ) ) {
            error_log( '[FATAL] VIS_MYRMIDON CRITICAL: Crypto extensions (Sodium/OpenSSL) missing. Runtime execution suspended.' );
            add_action( 'admin_notices', array( $this, 'render_dependency_failure_notice' ) );
            return; 
        }

        $this->ensure_vault_infrastructure();

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_filter( 'vis_aegis_should_block_request', array( $this, 'aegis_coop_mode' ), 10, 2 );
        add_filter( 'determine_current_user', array( $this, 'recover_auth_user' ), 10 );
    }

    public function render_dependency_failure_notice(): void {
        echo '<div class="notice notice-error"><p><strong>VGT Myrmidon Core:</strong> Systemsicherheit gefährdet. Die PHP-Erweiterungen <code>sodium</code> und <code>openssl</code> müssen installiert sein.</p></div>';
    }

    private function ensure_vault_infrastructure(): void {
        // 1. Vault Key (AES-256 Master Key)
        if ( false === get_option( $this->vault_key_option ) ) {
            try {
                $key = random_bytes( 32 ); 
                add_option( $this->vault_key_option, base64_encode( $key ), '', 'no' );
                sodium_memzero( $key );
            } catch ( \Exception $e ) {
                error_log( '[FATAL] VIS_MYRMIDON: Entropy Generation Failure - ' . $e->getMessage() );
                return;
            }
        }

        // 2. Server Identity (X25519 for ECDH)
        if ( false === get_option( '_vis_myrmidon_server_keys' ) ) {
            try {
                $keypair = sodium_crypto_box_keypair();
                $keys = array(
                    'public'  => base64_encode( sodium_crypto_box_publickey( $keypair ) ),
                    'private' => base64_encode( sodium_crypto_box_secretkey( $keypair ) )
                );
                add_option( '_vis_myrmidon_server_keys', $keys, '', 'no' );
                sodium_memzero( $keypair );
            } catch ( \Throwable $e ) {
                error_log( '[FATAL] VIS_MYRMIDON: Keypair Generation Failure - ' . $e->getMessage() );
                return;
            }
        }

        // 3. Database Integrity
        $installed_ver = get_option( '_vis_myrmidon_db_version' );
        if ( $installed_ver !== self::DB_VERSION ) {
            $this->check_and_create_table();
            update_option( '_vis_myrmidon_db_version', self::DB_VERSION );
        }
    }

    private function check_and_create_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            device_id varchar(64) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            device_name varchar(100) NOT NULL,
            os_type varchar(20) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            integrity_score int(11) DEFAULT 0,
            public_key text NOT NULL,
            encrypted_telemetry longtext,
            last_seen datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            override_trust tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY device_id (device_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        }
        dbDelta( $sql );
    }

    // =========================================================================================
    // 3. STORAGE LAYER (AES-256-GCM AT REST WITH IN-MEMORY ZEROING)
    // =========================================================================================
    
    private function encrypt_at_rest( array $data ): string {
        $key_b64 = get_option( $this->vault_key_option );
        if ( ! is_string( $key_b64 ) ) {
            return '';
        }

        $key = base64_decode( $key_b64, true );
        if ( false === $key || strlen( $key ) !== 32 ) {
            return '';
        }

        try {
            $iv = random_bytes( 12 ); 
            
            $plaintext = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            if ( false === $plaintext ) {
                return ''; 
            }

            $tag = '';
            $ciphertext = openssl_encrypt( $plaintext, $this->cipher_algo, $key, OPENSSL_RAW_DATA, $iv, $tag );
            
            if ( $ciphertext === false ) {
                return '';
            }

            return base64_encode( "\x01" . $iv . $tag . $ciphertext );

        } catch ( \Throwable $e ) {
            error_log( '[STORAGE] Encryption error on persistence path: ' . $e->getMessage() );
            return '';
        } finally {
            sodium_memzero( $key );
        }
    }

    public function decrypt_at_rest( ?string $blob ): array {
        if ( empty( $blob ) ) {
            return array();
        }
        
        $key_b64 = get_option( $this->vault_key_option );
        if ( ! is_string( $key_b64 ) ) {
            return array();
        }
        
        $key = base64_decode( $key_b64, true );
        if ( false === $key ) {
            return array();
        }

        try {
            $decoded = base64_decode( $blob, true );
            if ( false === $decoded || strlen( $decoded ) < 30 ) {
                return array();
            }
            
            if ( ord( $decoded[0] ) !== 1 ) {
                return array();
            }

            $iv         = substr( $decoded, 1, 12 );
            $tag        = substr( $decoded, 13, 16 );
            $ciphertext = substr( $decoded, 29 );

            $json = openssl_decrypt( $ciphertext, $this->cipher_algo, $key, OPENSSL_RAW_DATA, $iv, $tag );
            
            if ( $json === false ) {
                return array();
            }
            
            $result = json_decode( $json, true );
            return ( json_last_error() === JSON_ERROR_NONE && is_array( $result ) ) ? $result : array();

        } catch ( \Throwable $e ) {
            error_log( '[STORAGE] Decryption error during recovery: ' . $e->getMessage() );
            return array();
        } finally {
            sodium_memzero( $key );
        }
    }

    // =========================================================================================
    // 4. TRANSPORT DECRYPTION ENGINE (X25519 ECDH + Ed25519 SIGNATURE MATCHING)
    // =========================================================================================

    /**
     * @return array{0: array, 1: string} [DecryptedData, RawSessionKey]
     */
    private function decrypt_transit_payload( array $params, string $client_pub_key_b64 ): array {
        $server_keys = get_option( '_vis_myrmidon_server_keys' );
        if ( ! is_array( $server_keys ) ) {
            throw new SecurityException( 'Server cryptographic keys are uninitialized.' );
        }
        
        $server_priv = base64_decode( $server_keys['private'], true );
        if ( false === $server_priv ) {
            throw new SecurityException( 'Server private key storage is corrupt.' );
        }

        try {
            $ephemeral_pub = base64_decode( $params['ephemeral_key'] ?? '', true ); 
            $iv            = base64_decode( $params['iv'] ?? '', true ); 
            $ciphertext    = base64_decode( $params['encrypted_payload'] ?? '', true );
            $tag           = base64_decode( $params['tag'] ?? '', true ); 

            if ( ! $ephemeral_pub || ! $iv || ! $ciphertext || ! $tag ) {
                throw new ValidationException( 'Cryptographic transport encoding error. Parameters missing.' );
            }

            // ECDH Exchange
            try {
                $shared_secret = sodium_crypto_scalarmult( $server_priv, $ephemeral_pub );
            } catch ( \SodiumException $e ) {
                throw new SecurityException( 'X25519 scalar multiplication failed: ' . $e->getMessage() );
            }

            // Session Key Derivation (HKDF-like context-bounded Hash)
            $session_key = sodium_crypto_generichash( $shared_secret, $ephemeral_pub, 32 );
            sodium_memzero( $shared_secret );

            // AES-256-GCM Payload Decryption
            $json_payload = openssl_decrypt( $ciphertext, $this->cipher_algo, $session_key, OPENSSL_RAW_DATA, $iv, $tag );

            if ( $json_payload === false ) {
                sodium_memzero( $session_key );
                throw new SecurityException( 'Symmetric authenticated decryption (AES-GCM) failed. Payload manipulated.' );
            }

            $inner = json_decode( $json_payload, true );
            if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $inner['signature'], $inner['data'] ) ) {
                sodium_memzero( $session_key );
                throw new ValidationException( 'Payload syntax error inside decrypted container.' );
            }

            $signature = base64_decode( $inner['signature'], true );
            if ( false === $signature ) {
                sodium_memzero( $session_key );
                throw new ValidationException( 'Signature block is not valid Base64.' );
            }

            $data_content = $inner['data']; 
            
            $client_pk = base64_decode( $client_pub_key_b64, true );
            if ( false === $client_pk ) {
                sodium_memzero( $session_key );
                throw new SecurityException( 'Stored client public key has corrupt structure.' );
            }

            // Ed25519 signature verification (Client Identity)
            if ( ! sodium_crypto_sign_verify_detached( $signature, $data_content, $client_pk ) ) {
                sodium_memzero( $session_key );
                throw new SecurityException( 'Ed25519 cryptographic signature verification failed.' );
            }

            $result = json_decode( $data_content, true );
            if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $result ) ) {
                sodium_memzero( $session_key );
                throw new ValidationException( 'De-serialized telemetry payload is not a valid JSON structure.' );
            }
            
            return array( $result, $session_key );

        } finally {
            sodium_memzero( $server_priv );
        }
    }

    // =========================================================================================
    // 5. API ROUTING & INTERFACE (OPAQUE ERROR BOUNDARIES - PATTERN 1.5.A)
    // =========================================================================================

    public function check_permission_open( \WP_REST_Request $request ): bool {
        return true;
    }

    /**
     * @return bool|\WP_Error
     */
    public function check_permission_strict( \WP_REST_Request $request ) {
        if ( ! $this->check_rate_limit( $this->get_client_ip() ) ) {
            return new \WP_Error( 'rate_limit', 'Zu viele Anfragen.', array( 'status' => 429 ) );
        }
        return true;
    }

    /**
     * @return bool|\WP_Error
     */
    public function check_permission_user( \WP_REST_Request $request ) {
        $limit_check = $this->check_permission_strict( $request );
        if ( is_wp_error( $limit_check ) ) {
            return $limit_check;
        }

        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            $user_id = $this->recover_auth_user( 0 );
        }
        
        if ( $user_id <= 0 ) {
            return new \WP_Error( 'unauthorized', 'Registrierung erfordert gültige Authentifizierung.', array( 'status' => 401 ) );
        }
        
        return true;
    }

    public function api_report( \WP_REST_Request $request ) {
        global $wpdb;
        $session_key = '';
        
        try {
            $params = $request->get_json_params();
            if ( ! is_array( $params ) ) {
                 throw new ValidationException( 'Payload body is not a valid JSON object.' );
            }

            $required = array( 'device_id', 'ephemeral_key', 'iv', 'tag', 'encrypted_payload' );
            foreach ( $required as $field ) {
                if ( empty( $params[$field] ) ) {
                    throw new ValidationException( "Missing transit parameters: {$field}" );
                }
            }

            $device_id = $params['device_id'];
            if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $device_id ) ) {
                throw new ValidationException( 'Illegal character sequences in device identifier.' );
            }
            
            // Replay-Schutz
            if ( ! $this->check_replay_nonce( $params['iv'], $device_id ) ) {
                throw new SecurityException( "Replay-Attacke unterbunden für Gerät: {$device_id}" );
            }

            $device = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE device_id = %s", $device_id ) );
            
            if ( ! $device ) {
                throw new SecurityException( "Report attempted from unregistered device identifier: {$device_id}" );
            }
            if ( $device->status !== 'active' ) {
                throw new SecurityException( "Report rejected. Device is not authorized by the administrator: {$device_id}" );
            }

            // Sichere Entschlüsselung
            list( $telemetry, $session_key ) = $this->decrypt_transit_payload( $params, $device->public_key );

            // Sendezeitpunkt-Validierung (Replay & Clock drift)
            if ( ! isset( $telemetry['timestamp'] ) || abs( time() - (int)$telemetry['timestamp'] ) > $this->replay_window ) {
                 throw new SecurityException( 'Drastic system clock delta detected.' );
            }

            // Integritäts-Evaluation
            $evaluation = $this->evaluate_integrity( $telemetry );
            
            if ( (int)$device->override_trust === 1 ) {
                $evaluation['status'] = 'secure';
                $evaluation['threats'][] = 'ADMIN_OVERRIDE';
            }

            $telemetry['threats'] = $evaluation['threats'];
            $encrypted_blob       = $this->encrypt_at_rest( $telemetry );

            $wpdb->update( 
                $this->table_name, 
                array( 
                    'last_seen'           => current_time( 'mysql' ),
                    'integrity_score'     => $evaluation['score'],
                    'encrypted_telemetry' => $encrypted_blob 
                ), 
                array( 'id' => $device->id ),
                array( '%s', '%d', '%s' ),
                array( '%d' )
            );

            $response_data = array( 
                'success'   => true, 
                'integrity' => $evaluation['status'],
                'score'     => $evaluation['score']
            );

            $response = $this->sign_response_with_session( $response_data, $session_key );
            
            sodium_memzero( $session_key );
            return $response;

        } catch ( ValidationException $e ) {
            if ( ! empty( $session_key ) ) {
                sodium_memzero( $session_key );
            }
            return new \WP_REST_Response( array( 'success' => false, 'error' => $e->getMessage() ), 400 );

        } catch ( SecurityException $e ) {
            if ( ! empty( $session_key ) ) {
                sodium_memzero( $session_key );
            }
            error_log( '[SEC] VGT Myrmidon: ' . $e->getMessage() );
            return new \WP_REST_Response( array( 'success' => false, 'error' => 'Request rejected for security reasons.' ), 401 );

        } catch ( StorageException $e ) {
            if ( ! empty( $session_key ) ) {
                sodium_memzero( $session_key );
            }
            error_log( '[STORAGE] VGT Myrmidon: ' . $e->getMessage() );
            return new \WP_REST_Response( array( 'success' => false, 'error' => 'A persistent server storage error occurred.' ), 500 );

        } catch ( \Throwable $e ) {
            if ( ! empty( $session_key ) ) {
                sodium_memzero( $session_key );
            }
            error_log( '[FATAL] VGT Myrmidon: ' . $e->getMessage() );
            return new \WP_REST_Response( array( 'success' => false, 'error' => 'Critical system fault.' ), 500 );
        }
    }

    public function api_register( \WP_REST_Request $request ) {
        global $wpdb;
        
        try {
            $user_id = get_current_user_id();
            if ( 0 === $user_id ) {
                $user_id = $this->recover_auth_user( 0 );
            }

            $params = $request->get_json_params();
            if ( ! is_array( $params ) ) {
                throw new ValidationException( 'Invalid registration payload structure.' );
            }

            $device_id  = $params['device_id'] ?? '';
            $public_key = $params['signing_key'] ?? '';

            if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $device_id ) ) {
                throw new ValidationException( 'Invalid device identifier pattern.' );
            }
            if ( ! preg_match( '/^[a-zA-Z0-9\/\+=]+$/', $public_key ) ) {
                throw new ValidationException( 'Invalid cryptographic public key formatting.' );
            }
            
            $existing_device = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM $this->table_name WHERE device_id = %s", $device_id ) );

            if ( $existing_device && $existing_device->status === 'active' ) {
                return new \WP_Error( 'conflict', 'Gerät bereits aktiv im Register. Reset erforderlich.', array( 'status' => 409 ) );
            }

            $data = array(
                'user_id'     => $user_id,
                'device_id'   => $device_id,
                'device_name' => sanitize_text_field( $params['device_name'] ?? 'Unbekannt' ),
                'os_type'     => sanitize_text_field( $params['os_type'] ?? 'unknown' ),
                'public_key'  => $public_key,
                'last_seen'   => current_time( 'mysql' ),
                'status'      => 'pending'
            );

            $formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );
            $result  = false;

            if ( $existing_device ) {
                $result = $wpdb->update( $this->table_name, $data, array( 'device_id' => $device_id ), $formats, array( '%s' ) );
            } else {
                $result = $wpdb->insert( $this->table_name, $data, $formats );
            }

            if ( false === $result ) {
                throw new StorageException( 'Database execution failed during device registration.' );
            }

            return rest_ensure_response( array( 
                'success' => true, 
                'status'  => 'pending', 
                'message' => 'Registriert. Warte auf administrative Freigabe.' 
            ) );

        } catch ( ValidationException $e ) {
            return new \WP_Error( 'bad_request', $e->getMessage(), array( 'status' => 400 ) );
        } catch ( SecurityException $e ) {
            error_log( '[SEC] VGT Myrmidon: ' . $e->getMessage() );
            return new \WP_Error( 'security_rejected', 'Request rejected for security reasons.', array( 'status' => 403 ) );
        } catch ( StorageException $e ) {
            error_log( '[STORAGE] VGT Myrmidon: ' . $e->getMessage() );
            return new \WP_Error( 'db_error', 'Database execution failure occurred.', array( 'status' => 500 ) );
        } catch ( \Throwable $e ) {
            error_log( '[FATAL] VGT Myrmidon: ' . $e->getMessage() );
            return new \WP_Error( 'critical_fault', 'Fatal system exception.', array( 'status' => 500 ) );
        }
    }

    // =========================================================================================
    // 6. PROTECTIVE SEC_OPS UTILITIES
    // =========================================================================================

    private function get_client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // CF-Connecting-IP ist vertrauenswürdig, WENN der Server nur Cloudflare IPs erlaubt
        if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
        }
        
        if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            return sanitize_text_field( trim( $ips[0] ) );
        }
        
        return $ip;
    }

    private function check_rate_limit( string $ip ): bool {
        $key   = 'vis_rl_' . hash( 'sha256', $ip );
        $count = (int) get_transient( $key );
        if ( 0 === $count ) {
            set_transient( $key, 1, 60 ); 
            return true;
        }
        if ( $count >= $this->rate_limit ) {
            return false;
        }
        set_transient( $key, $count + 1, 60 );
        return true;
    }

    private function check_replay_nonce( string $iv, string $device_id ): bool {
        $cache_key = 'vis_rpl_' . hash( 'sha256', $device_id . $iv );
        if ( false !== get_transient( $cache_key ) ) {
            return false; 
        }
        set_transient( $cache_key, 1, $this->replay_window );
        return true;
    }

    private function evaluate_integrity( array $data ): array {
        $score   = 100;
        $threats = array();
        $status  = 'secure';
        $os      = strtolower( (string) ($data['os_type'] ?? 'unknown') );
        
        if ( ! empty( $data['is_rooted'] ) ) { 
            $score = 0; 
            $threats[] = 'ROOTED'; 
            $status = 'compromised'; 
        }
        if ( empty( $data['encryption_active'] ) ) { 
            $score -= 40; 
            $threats[] = 'NO_ENC'; 
        }
        if ( $os === 'android' && ! empty( $data['adb_enabled'] ) ) { 
            $score -= 30; 
            $threats[] = 'ADB_ON'; 
        }
        if ( in_array( $os, array( 'windows', 'linux' ), true ) && isset( $data['secure_boot'] ) && ! $data['secure_boot'] ) { 
            $score -= 20; 
            $threats[] = 'NO_SECURE_BOOT'; 
        }
        
        if ( $score < 50 ) {
            $status = 'compromised'; 
        } elseif ( $score < 90 ) {
            $status = 'warning';
        }
        
        return array( 'score' => max( 0, $score ), 'status' => $status, 'threats' => $threats );
    }

    private function sign_response_with_session( array $data, string $session_key ) {
        $json = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( false === $json ) {
            return rest_ensure_response( array( 'success' => false, 'error' => 'Encoding failure.' ) );
        }
        
        $signature = base64_encode( hash_hmac( 'sha256', $json, $session_key, true ) );
        
        return rest_ensure_response( array( 
            'payload'    => $data, 
            'server_sig' => $signature, 
            'timestamp'  => time() 
        ));
    }

    public function recover_auth_user( $user_id ) {
        if ( $user_id > 0 ) {
            return $user_id;
        }
        $auth_header = $this->get_auth_header();
        
        if ( empty( $auth_header ) || stripos( $auth_header, 'basic' ) === false ) {
            return $user_id;
        }
        
        $token   = trim( substr( $auth_header, 6 ) );
        $decoded = base64_decode( $token, true );
        
        if ( false === $decoded || strpos( $decoded, ':' ) === false ) {
            return $user_id;
        }
        
        $credentials = explode( ':', $decoded, 2 );
        if ( count( $credentials ) === 2 ) {
            $user = wp_authenticate_application_password( null, $credentials[0], $credentials[1] );
            if ( ! is_wp_error( $user ) && $user instanceof \WP_User ) {
                return $user->ID;
            }
        }
        return $user_id;
    }

    private function get_auth_header(): string {
        return $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }

    public function aegis_coop_mode( $should_block, $request_data ) {
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], $this->namespace . '/device' ) !== false ) {
            return false;
        }
        return $should_block;
    }

    // =========================================================================================
    // 7. DASHBOARD PERSISTENCE INTERFACE
    // =========================================================================================
    
    public function get_all_devices(): array {
        global $wpdb;
        $results = $wpdb->get_results( "SELECT * FROM $this->table_name ORDER BY last_seen DESC", ARRAY_A );
        return is_array( $results ) ? $results : array();
    }

    public function get_device_details_decrypted( array $device_row ): array {
        if ( empty( $device_row['encrypted_telemetry'] ) ) {
            return array();
        }
        return $this->decrypt_at_rest( $device_row['encrypted_telemetry'] );
    }
    
    public function approve_device( string $device_id ): void { 
        global $wpdb; 
        $wpdb->update( $this->table_name, array( 'status' => 'active' ), array( 'device_id' => $device_id ) ); 
    }

    public function override_device( string $device_id ): void { 
        global $wpdb; 
        $wpdb->update( $this->table_name, array( 'override_trust' => 1, 'integrity_score' => 100 ), array( 'device_id' => $device_id ) ); 
    }

    public function delete_device( string $device_id ): void { 
        global $wpdb; 
        $wpdb->delete( $this->table_name, array( 'device_id' => $device_id ) ); 
    }

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/device/handshake', array( 
            'methods'             => 'GET', 
            'callback'            => array( $this, 'api_handshake' ), 
            'permission_callback' => array( $this, 'check_permission_open' ) 
        ) );
        register_rest_route( $this->namespace, '/device/register', array( 
            'methods'             => 'POST', 
            'callback'            => array( $this, 'api_register' ), 
            'permission_callback' => array( $this, 'check_permission_user' ) 
        ) );
        register_rest_route( $this->namespace, '/device/report', array( 
            'methods'             => 'POST', 
            'callback'            => array( $this, 'api_report' ), 
            'permission_callback' => array( $this, 'check_permission_strict' ) 
        ) );
    }

    public function api_handshake() {
        $keys = get_option( '_vis_myrmidon_server_keys' );
        if ( ! is_array( $keys ) ) {
            return new \WP_Error( 'config_error', 'Server-Keys nicht initialisiert.', array( 'status' => 500 ) );
        }
        
        return rest_ensure_response( array( 
            'public_key'  => $keys['public'], 
            'algo'        => 'X25519', 
            'fingerprint' => hash( 'sha256', base64_decode( $keys['public'], true ) ?: '' ) 
        ));
    }
}

// Global Kernel Initialization
VIS_Myrmidon::get_instance();
