<?php
/**
 * VISIONGAIATECHNOLOGY SENTINEL
 * COMPONENT: VISUAL INTERFACE (MYRMIDON VAULT)
 * ARCHITECTURE: MVC VIEW LAYER / SECURE ADMIN CONTEXT
 * SECURITY LEVEL: DIAMANT VGT SUPREME (Admin-Only)
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // SILENCE IS GOLDEN
}

// 1. SYSTEM RETRIEVAL & CAPABILITY GUARD
$myrmidon = VIS_Myrmidon::get_instance();
$msg      = '';
$msg_type = 'success';

try {
    // 2. ACTION CONTROLLER (SECURE POST PROCESSOR)
    if ( isset( $_POST['vis_action'] ) ) {
        
        // Strikte CSRF-Sicherheitsbarriere
        if ( ! check_admin_referer( 'vis_action_nonce' ) ) {
            throw new SecurityException( 'CSRF token verification failed on administrative action dispatch.' );
        }

        // Berechtigungsprüfung (Least Privilege Enforcement)
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new SecurityException( 'Unauthorized administrative access attempt.' );
        }

        $action    = sanitize_key( $_POST['vis_action'] );
        $device_id = isset( $_POST['device_id'] ) ? sanitize_text_field( $_POST['device_id'] ) : '';

        // Strikte Eingabe-Validierung per Regex (Verhindert SQLi- & Path-Traversal-Rauschen)
        if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $device_id ) ) {
            throw new ValidationException( 'Formatverletzung: Ungültige Zeichensequenz im Geräte-Identifikator.' );
        }

        switch ( $action ) {
            case 'approve':
                $myrmidon->approve_device( $device_id );
                $msg = 'GERÄT ERFOLGREICH AUTORISIERT. Kryptographischer Handshake freigegeben.';
                break;
                
            case 'override':
                $myrmidon->override_device( $device_id );
                $msg      = 'SECURITY OVERRIDE AKTIV: Vertrauensstatus manuell erzwungen (Audit Logged).';
                $msg_type = 'warning';
                break;
                
            case 'delete':
            case 'deny':
                $myrmidon->delete_device( $device_id );
                $msg = 'Gerät permanent aus dem Ledger entfernt. Lokale Schlüssel vernichtet.';
                break;
                
            default:
                throw new ValidationException( 'Unbekannte Operation oder ungültiger Aktions-Pfad.' );
        }
    }
} catch ( ValidationException $e ) {
    $msg      = $e->getMessage();
    $msg_type = 'error';
} catch ( SecurityException $e ) {
    error_log( '[SEC] Myrmidon Dashboard Security Exception: ' . $e->getMessage() );
    $msg      = 'Sicherheitsverletzung: Die Operation wurde blockiert und protokolliert.';
    $msg_type = 'error';
} catch ( \Throwable $e ) {
    error_log( '[FATAL] Myrmidon Dashboard Global Fault: ' . $e->getMessage() );
    $msg      = 'Kritischer Systemfehler bei der Verarbeitung.';
    $msg_type = 'error';
}

// 3. LEDGER STATE AGGREGATION
$devices_all = $myrmidon->get_all_devices();

$stats = array(
    'total'       => count( $devices_all ),
    'pending'     => 0,
    'compromised' => 0,
    'secure'      => 0
);

foreach ( $devices_all as $d ) {
    if ( ( $d['status'] ?? '' ) === 'pending' ) {
        $stats['pending']++;
    } else {
        $score = (int) ( $d['integrity_score'] ?? 0 );
        if ( $score >= 90 || ! empty( $d['override_trust'] ) ) {
            $stats['secure']++;
        } elseif ( $score < 50 ) {
            $stats['compromised']++;
        }
    }
}

// Server Identitäts-Zertifikat extrahieren
$keys            = get_option( '_vis_myrmidon_server_keys' );
$fingerprint     = is_array( $keys ) ? hash( 'sha256', base64_decode( $keys['public'], true ) ?: '' ) : 'SYSTEM_NOT_INITIALIZED';
$fingerprint_fmt = wordwrap( strtoupper( $fingerprint ), 4, ' ', true );
$sodium_state    = extension_loaded( 'sodium' );

// 4. VIEW RENDERING UTILITIES
if ( ! function_exists( 'vis_render_audit_item' ) ) {
    function vis_render_audit_item( string $label, bool $status, bool $critical = false ): string {
        $icon        = $status ? 'dashicons-yes' : 'dashicons-no';
        $color       = $status ? '#00e676' : ( $critical ? '#ff1744' : '#ff9100' );
        $text_class  = $status ? 'vis-audit-ok' : ( $critical ? 'vis-audit-fail' : 'vis-audit-warn' );
        $status_text = $status ? 'SICHER' : 'FEHLER';
        
        return sprintf(
            '<div class="vis-audit-item"><span class="dashicons %s" style="color:%s"></span><span class="vis-audit-label">%s</span><span class="%s">%s</span></div>',
            esc_attr( $icon ),
            esc_attr( $color ),
            esc_html( $label ),
            esc_attr( $text_class ),
            esc_html( $status_text )
        );
    }
}

if ( ! function_exists( 'vis_get_os_icon' ) ) {
    function vis_get_os_icon( string $os ): string {
        $os = strtolower( $os );
        if ( str_contains( $os, 'win' ) ) {
            return 'dashicons-desktop';
        }
        if ( str_contains( $os, 'android' ) ) {
            return 'dashicons-smartphone';
        }
        if ( str_contains( $os, 'linux' ) ) {
            return 'dashicons-rest-api';
        }
        if ( str_contains( $os, 'mac' ) || str_contains( $os, 'ios' ) ) {
            return 'dashicons-apple';
        }
        return 'dashicons-laptop';
    }
}
?>

<!-- 5. RENDER UI (VISIONGAIATECHNOLOGY OMEGA DARK THEME) -->
<div class="wrap vis-dashboard-wrap">
    
    <!-- SYSTEM ALERTS / STATUS BENACHRICHTIGUNGEN -->
    <?php if ( ! empty( $msg ) ) : ?>
        <div class="vis-alert <?php echo 'error' === $msg_type ? 'vis-alert-danger' : ( 'warning' === $msg_type ? 'vis-alert-warning' : 'vis-alert-success' ); ?>">
            <span class="dashicons <?php echo 'error' === $msg_type ? 'dashicons-dismiss' : 'dashicons-info'; ?>"></span> 
            <p><?php echo esc_html( $msg ); ?></p>
        </div>
    <?php endif; ?>

    <!-- OVERVIEW CONTROL BOARD -->
    <div class="vis-header-grid">
        <div class="vis-card vis-card-glow">
            <h3><span class="dashicons dashicons-database"></span> MYRMIDON LEDGER</h3>
            <div class="vis-stat-grid">
                <div class="vis-stat">
                    <span class="vis-stat-val"><?php echo esc_html( (string) $stats['total'] ); ?></span>
                    <span class="vis-stat-label">VAULT ENTRIES</span>
                </div>
                <div class="vis-stat">
                    <span class="vis-stat-val" style="color: <?php echo $sodium_state ? '#00f2ff' : '#ff1744'; ?>">
                        <?php echo $sodium_state ? 'AES-256' : 'MISSING'; ?>
                    </span>
                    <span class="vis-stat-label">CRYPTO ENGINE</span>
                </div>
                <?php if ( $stats['pending'] > 0 ) : ?>
                    <div class="vis-stat vis-stat-alert">
                        <span class="vis-stat-val blink" style="color: #ff9100;"><?php echo esc_html( (string) $stats['pending'] ); ?></span>
                        <span class="vis-stat-label">ACTION REQ.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="vis-card">
            <h3><span class="dashicons dashicons-fingerprint"></span> SERVER IDENTITÄT</h3>
            <p class="vis-desc">SHA-256 Fingerprint (Anti-MITM Verification):</p>
            <div class="vis-fingerprint-box">
                <?php echo esc_html( $fingerprint_fmt ); ?>
            </div>
        </div>
    </div>

    <!-- PENDING APPROVAL QUEUE (ONLY RENDER IF STATE ACTIVE) -->
    <?php if ( $stats['pending'] > 0 ) : ?>
        <div class="vis-card vis-queue-container">
            <div class="vis-card-header vis-header-warn">
                <h3><span class="dashicons dashicons-flag"></span> SYSTEM-FREIGABE ERFORDERLICH (<?php echo esc_html( (string) $stats['pending'] ); ?>)</h3>
            </div>
            <table class="vis-table">
                <thead>
                    <tr>
                        <th>GERÄT / FINGERPRINT</th>
                        <th>BENUTZER KONTEXT</th>
                        <th>ZEITSTEMPEL</th>
                        <th style="text-align:right;">COMMANDS</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $devices_all as $d ) : 
                    if ( ( $d['status'] ?? '' ) !== 'pending' ) {
                        continue; 
                    }
                    ?>
                    <tr>
                        <td>
                            <strong class="vis-text-highlight"><?php echo esc_html( $d['device_name'] ); ?></strong><br>
                            <span class="vis-meta mono"><?php echo esc_html( $d['os_type'] ); ?> :: <?php echo esc_html( substr( $d['device_id'], 0, 12 ) ); ?>...</span>
                        </td>
                        <td class="vis-text-light">
                            <?php 
                            $user_info = get_userdata( (int) $d['user_id'] );
                            echo $user_info ? esc_html( $user_info->user_login ) : 'Unknown Operator';
                            ?> 
                            <span class="vis-meta">(ID: <?php echo esc_html( (string) $d['user_id'] ); ?>)</span>
                        </td>
                        <td class="vis-meta">
                            <?php 
                            $timestamp = strtotime( $d['created_at'] ?? 'now' );
                            echo esc_html( human_time_diff( $timestamp === false ? time() : $timestamp ) ); 
                            ?> ago
                        </td>
                        <td style="text-align:right;">
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'vis_action_nonce' ); ?>
                                <input type="hidden" name="vis_action" value="approve">
                                <input type="hidden" name="device_id" value="<?php echo esc_attr( $d['device_id'] ); ?>">
                                <button type="submit" class="vis-btn vis-btn-success">ZULASSEN</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'vis_action_nonce' ); ?>
                                <input type="hidden" name="vis_action" value="deny">
                                <input type="hidden" name="device_id" value="<?php echo esc_attr( $d['device_id'] ); ?>">
                                <button type="submit" class="vis-btn vis-btn-danger">ABLEHNEN</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- MAIN SECURE LEDGER -->
    <div class="vis-card vis-table-container">
        <div class="vis-card-header">
            <h3><span class="dashicons dashicons-lock"></span> SECURE DEVICE LEDGER</h3>
        </div>
        
        <table class="vis-table">
            <thead>
                <tr>
                    <th width="40"></th>
                    <th width="60" style="text-align:center;">STATUS</th>
                    <th>GERÄT / OS</th>
                    <th>INTEGRITÄT</th>
                    <th>TRUST SCORE</th>
                    <th style="text-align:right;">PROTOKOLLE</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $devices_all ) || ( count( $devices_all ) === $stats['pending'] ) ) : ?>
                    <tr><td colspan="6" class="vis-empty-row">Ledger ist leer. Keine aktiven kryptographischen Tunnel.</td></tr>
                <?php else : ?>
                    <?php foreach ( $devices_all as $idx => $device ) : 
                        if ( ( $device['status'] ?? '' ) === 'pending' ) {
                            continue;
                        }

                        $score = (int) ( $device['integrity_score'] ?? 0 );
                        $integrity_label = ( $score >= 90 ) ? 'secure' : ( ( $score < 50 ) ? 'compromised' : 'warning' );
                        if ( ! empty( $device['override_trust'] ) ) {
                            $integrity_label = 'secure';
                        }

                        $integrity_class = 'vis-' . $integrity_label;
                        $icon            = ( 'secure' === $integrity_label ) ? 'dashicons-yes' : 'dashicons-warning';
                        $os_icon         = vis_get_os_icon( $device['os_type'] ?? 'unknown' );
                        
                        // ON-THE-FLY DECRYPTION (SECURE TRANSIT RESTORATION)
                        $details = $myrmidon->get_device_details_decrypted( $device );
                        $threats = $details['threats'] ?? array();
                        
                        $detail_id = 'vis_detail_' . sanitize_key( (string) $idx );
                    ?>
                    <tr class="vis-main-row" onclick="document.getElementById('<?php echo esc_attr( $detail_id ); ?>').classList.toggle('vis-hidden');">
                        <td style="text-align:center; cursor:pointer;"><span class="dashicons dashicons-arrow-down-alt2 vis-toggle-icon"></span></td>
                        <td style="text-align:center;"><span class="dashicons <?php echo esc_attr( $icon ); ?> vis-icon-<?php echo esc_attr( $integrity_label ); ?>"></span></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span class="dashicons <?php echo esc_attr( $os_icon ); ?>" style="color:#607d8b;"></span>
                                <div>
                                    <strong class="vis-text-highlight"><?php echo esc_html( $device['device_name'] ); ?></strong>
                                    <?php if ( ! empty( $device['override_trust'] ) ) : ?><span class="vis-badge-override">OVERRIDE</span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                           <span class="vis-badge vis-bg-<?php echo esc_attr( $integrity_label ); ?>"><?php echo strtoupper( esc_html( $integrity_label ) ); ?></span>
                        </td>
                        <td>
                            <div class="vis-score vis-text-<?php echo esc_attr( $integrity_label ); ?>">
                                <?php echo esc_html( (string) $score ); ?>/100
                            </div>
                        </td>
                        <td style="text-align:right;">
                            <?php if ( 'secure' !== $integrity_label && empty( $device['override_trust'] ) ) : ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Sicherheits-Bypass ausführen? Das Gerät wird manuell als vertrauenswürdig eingestuft.');">
                                <?php wp_nonce_field( 'vis_action_nonce' ); ?>
                                <input type="hidden" name="vis_action" value="override">
                                <input type="hidden" name="device_id" value="<?php echo esc_attr( $device['device_id'] ); ?>">
                                <button type="submit" class="vis-action-btn vis-btn-override" title="Trust Override" onclick="event.stopPropagation();">OVERRIDE</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Kryptographischen Kontext vernichten? Das Gerät muss sich neu autorisieren.');">
                                <?php wp_nonce_field( 'vis_action_nonce' ); ?>
                                <input type="hidden" name="vis_action" value="delete">
                                <input type="hidden" name="device_id" value="<?php echo esc_attr( $device['device_id'] ); ?>">
                                <button type="submit" class="vis-action-btn vis-btn-delete" title="Löschen" onclick="event.stopPropagation();"><span class="dashicons dashicons-trash"></span></button>
                            </form>
                        </td>
                    </tr>

                    <!-- EXPANDABLE TELEMETRY DETAILS -->
                    <tr id="<?php echo esc_attr( $detail_id ); ?>" class="vis-detail-row vis-hidden">
                        <td colspan="6">
                            <div class="vis-detail-container">
                                <div class="vis-detail-col">
                                    <h4><span class="dashicons dashicons-admin-settings"></span> METADATA (LEDGER TRANSIT)</h4>
                                    <ul class="vis-tech-list">
                                        <li><strong>DEVICE IDENTIFIER:</strong> <span class="mono"><?php echo esc_html( $device['device_id'] ); ?></span></li>
                                        <li><strong>LAST TELEMETRY UPLINK:</strong> <span class="mono"><?php echo esc_html( $device['last_seen'] ?? 'NEVER' ); ?></span></li>
                                        <li><strong>DATABASE ENVELOPE:</strong> <span class="mono">INDEX #<?php echo esc_html( $device['id'] ); ?></span></li>
                                        <li><strong>HYGIENE LAYER:</strong> <span class="mono"><?php echo ! empty( $device['encrypted_telemetry'] ) ? 'AES-256-GCM SecureBox' : 'DANGER: EXPOSED'; ?></span></li>
                                    </ul>
                                </div>
                                <div class="vis-detail-col">
                                    <h4><span class="dashicons dashicons-shield-alt"></span> INTEGRITY AUDIT (DECRYPTED MEMORY)</h4>
                                    <div class="vis-audit-grid">
                                        <?php if ( empty( $details ) ) : ?>
                                            <div class="vis-audit-item"><span class="vis-audit-fail">SYSTEM ERROR: NO TELEMETRY PAYLOAD CAPTURED AT REST</span></div>
                                        <?php else : ?>
                                            <?php 
                                            $root_safe = empty( $details['is_rooted'] );
                                            echo vis_render_audit_item( 'System Integrity (Root / Jailbreak Detection)', $root_safe, true );

                                            $enc_safe = ! empty( $details['encryption_active'] );
                                            echo vis_render_audit_item( 'Data-At-Rest Encryption (BitLocker / FileVault / dm-crypt)', $enc_safe, false );

                                            if ( isset( $details['secure_boot'] ) ) {
                                                echo vis_render_audit_item( 'Platform Security Verification (UEFI Secure Boot)', (bool) $details['secure_boot'], false );
                                            }
                                            if ( isset( $details['firewall_active'] ) ) {
                                                echo vis_render_audit_item( 'Active Host Protection (OS Firewall Layer)', (bool) $details['firewall_active'], false );
                                            }
                                            if ( isset( $details['adb_enabled'] ) ) {
                                                $adb_safe = empty( $details['adb_enabled'] );
                                                echo vis_render_audit_item( 'Debug Interface Hygiene (ADB Subsystem Active)', $adb_safe, false );
                                            }
                                            
                                            if ( ! empty( $threats ) ) {
                                                echo '<div class="vis-threat-box"><strong>CRITICAL THREAT TRIGGERS EXPOSED:</strong> ' . esc_html( implode( ' | ', $threats ) ) . '</div>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- INLINE STYLE BLOCK WITH DYNAMIC CSP NONCE INTEGRATION -->
<style nonce="<?php echo function_exists( 'vgt_get_csp_nonce' ) ? esc_attr( vgt_get_csp_nonce() ) : ''; ?>">
:root {
    --vis-bg: #090d16;
    --vis-panel: #0f172a;
    --vis-border: #1e293b;
    --vis-accent: #00e676; 
    --vis-warn: #ff9100;    
    --vis-crit: #ff1744;    
    --vis-text: #eceff1;
    --vis-meta: #64748b;
}

.vis-dashboard-wrap { max-width: 1400px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: var(--vis-text); }
.vis-dashboard-wrap * { box-sizing: border-box; }
.mono { font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; letter-spacing: -0.5px; }

/* PANEL MATRIX */
.vis-header-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 25px; }
.vis-card { background: var(--vis-panel); border: 1px solid var(--vis-border); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); padding: 24px; position: relative; overflow: hidden; }
.vis-card-glow { border-top: 3px solid var(--vis-accent); }

/* SYSTEM DESIGN & HEADER */
.vis-card h3 { margin-top: 0; color: #fff; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; border-bottom: 1px solid var(--vis-border); padding-bottom: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.vis-desc { font-size: 12px; color: var(--vis-meta); margin-bottom: 12px; }

/* STATISTICS GRID */
.vis-stat-grid { display: flex; gap: 48px; }
.vis-stat { text-align: left; }
.vis-stat-val { display: block; font-size: 36px; font-weight: 800; color: #fff; line-height: 1; text-shadow: 0 2px 8px rgba(0,0,0,0.5); }
.vis-stat-label { font-size: 10px; text-transform: uppercase; color: var(--vis-meta); font-weight: 700; letter-spacing: 1px; margin-top: 6px; display: block; }

.blink { animation: vis-glowing-heartbeat 2s infinite; }
@keyframes vis-glowing-heartbeat { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }

/* MITM DEFENSE STATUS DISPLAY */
.vis-fingerprint-box { background: #020617; color: #00f2ff; font-family: "SFMono-Regular", Consolas, monospace; padding: 16px; border-radius: 8px; border: 1px solid rgba(0, 242, 255, 0.25); word-break: break-all; font-size: 13px; text-align: center; letter-spacing: 1px; box-shadow: inset 0 0 20px rgba(0, 242, 255, 0.05); }

/* TABLES WITH GLASSMORPHISM SKEW */
table.vis-table { width: 100%; border-collapse: separate; border-spacing: 0; background: transparent; }
table.vis-table thead th { text-align: left; padding: 16px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--vis-meta); border-bottom: 2px solid var(--vis-border); background: rgba(15, 23, 42, 0.6); }
table.vis-table tbody td { padding: 16px; border-bottom: 1px solid #1e293b; vertical-align: middle; color: #cbd5e1; background: transparent; }

.vis-text-highlight { color: #fff; font-weight: 700; font-size: 14px; }
.vis-text-light { color: #94a3b8; font-size: 13px; }
.vis-meta { font-size: 11px; color: #475569; }

/* INTEGRITY LEVEL INDICATORS */
.vis-icon-secure { color: var(--vis-accent); }
.vis-icon-warning { color: var(--vis-warn); }
.vis-icon-compromised { color: var(--vis-crit); }

/* PILL BADGES */
.vis-badge { padding: 4px 10px; border-radius: 4px; font-size: 9px; font-weight: 900; letter-spacing: 0.5px; display: inline-block; min-width: 80px; text-align: center; }
.vis-bg-secure { background: rgba(0, 230, 118, 0.08); color: var(--vis-accent); border: 1px solid var(--vis-accent); }
.vis-bg-compromised { background: rgba(255, 23, 68, 0.08); color: var(--vis-crit); border: 1px solid var(--vis-crit); }
.vis-bg-warning { background: rgba(255, 145, 0, 0.08); color: var(--vis-warn); border: 1px solid var(--vis-warn); }
.vis-badge-override { background: var(--vis-warn); color: #020617; font-size: 9px; padding: 2px 6px; border-radius: 3px; margin-left: 8px; font-weight: 900; }

/* COMMAND BUTTON MATRIX */
.vis-btn { border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.15s ease-in-out; }
.vis-btn-success { background: var(--vis-accent); color: #020617; box-shadow: 0 4px 12px rgba(0, 230, 118, 0.2); }
.vis-btn-success:hover { background: #00c853; transform: translateY(-1px); }
.vis-btn-danger { background: transparent; border: 1px solid var(--vis-crit); color: var(--vis-crit); }
.vis-btn-danger:hover { background: var(--vis-crit); color: #fff; transform: translateY(-1px); }

.vis-action-btn { background: none; border: none; cursor: pointer; color: var(--vis-meta); font-size: 16px; transition: color 0.15s ease-in-out; padding: 6px; }
.vis-btn-delete:hover { color: var(--vis-crit); }
.vis-btn-override { color: var(--vis-warn); font-size: 10px; font-weight: 800; border: 1px solid var(--vis-warn); border-radius: 4px; padding: 4px 10px; margin-right: 12px; transition: all 0.15s ease-in-out; }
.vis-btn-override:hover { background: var(--vis-warn); color: #020617; }

/* INTERACTION PATTERNS */
.vis-main-row { cursor: pointer; transition: background 0.15s ease-in-out; }
.vis-main-row:hover td { background: #1e293b !important; }
.vis-toggle-icon { transition: transform 0.2s ease-in-out; color: var(--vis-meta); }
.vis-main-row:hover .vis-toggle-icon { color: #fff; transform: translateY(1px); }

/* DETAILS PANE INTERPOLATION */
.vis-hidden { display: none; }
.vis-detail-row td { background: #0b0f19 !important; border-bottom: 2px solid var(--vis-border) !important; padding: 0 !important; }
.vis-detail-container { padding: 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 48px; border-left: 4px solid var(--vis-border); background: #020617; }
.vis-detail-col h4 { margin: 0 0 20px 0; font-size: 11px; color: var(--vis-meta); text-transform: uppercase; letter-spacing: 1.5px; border-bottom: 1px solid #1e293b; padding-bottom: 10px; }

.vis-tech-list { list-style: none; margin: 0; padding: 0; }
.vis-tech-list li { font-size: 13px; color: #94a3b8; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #1e293b; display: flex; justify-content: space-between; }
.vis-tech-list strong { color: var(--vis-text); font-weight: 700; }

/* TELEMETRY ITEM REPRESENTATION */
.vis-audit-grid { display: flex; flex-direction: column; gap: 12px; }
.vis-audit-item { display: flex; align-items: center; background: #0f172a; padding: 12px 20px; border-radius: 6px; border: 1px solid #1e293b; }
.vis-audit-item .dashicons { margin-right: 16px; font-size: 20px; }
.vis-audit-label { flex-grow: 1; font-size: 13px; font-weight: 600; color: #f1f5f9; }
.vis-audit-ok { color: var(--vis-accent); font-size: 10px; font-weight: 900; letter-spacing: 1px; }
.vis-audit-fail { color: var(--vis-crit); font-size: 10px; font-weight: 900; letter-spacing: 1px; }

.vis-threat-box { margin-top: 16px; padding: 12px; background: rgba(255, 23, 68, 0.08); border: 1px solid var(--vis-crit); color: var(--vis-crit); font-size: 12px; border-radius: 6px; font-family: "SFMono-Regular", Consolas, monospace; }

/* ERROR PANE & ALERTS */
.vis-alert { padding: 16px; margin-bottom: 24px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 12px; border-left: 4px solid; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.vis-alert p { margin: 0; }
.vis-alert-success { background: rgba(0, 230, 118, 0.08); color: #fff; border-color: var(--vis-accent); }
.vis-alert-warning { background: rgba(255, 145, 0, 0.08); color: #fff; border-color: var(--vis-warn); }
.vis-alert-danger { background: rgba(255, 23, 68, 0.08); color: #fff; border-color: var(--vis-crit); }
.vis-queue-container { margin-top: 32px; border-color: var(--vis-warn); border-top-width: 3px; }
.vis-header-warn h3 { color: var(--vis-warn); border-bottom-color: rgba(255, 145, 0, 0.2); }
</style>
