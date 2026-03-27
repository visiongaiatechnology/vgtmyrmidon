# 🛡️ VGT Myrmidon — Zero Trust Network Access for WordPress

[![License](https://img.shields.io/badge/License-AGPLv3-green?style=for-the-badge)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.0.7-brightgreen?style=for-the-badge)](#)
[![Platform](https://img.shields.io/badge/Platform-WordPress-21759B?style=for-the-badge&logo=wordpress)](#)
[![Crypto](https://img.shields.io/badge/Crypto-X25519_%2B_Ed25519_%2B_AES--256--GCM-purple?style=for-the-badge)](#)
[![Status](https://img.shields.io/badge/Status-STABLE-brightgreen?style=for-the-badge)](#)
[![Architecture](https://img.shields.io/badge/Architecture-ZTNA-red?style=for-the-badge)](#)
[![VGT](https://img.shields.io/badge/VGT-VisionGaia_Technology-red?style=for-the-badge)](https://visiongaiatechnology.de)

> *"Trust no device. Verify everything. Encrypt at rest."*
> *AGPLv3 — For Humans, not for SaaS Corporations.*

---

## 🔍 What is VGT Myrmidon?

VGT Myrmidon is a **Zero Trust Network Access (ZTNA) endpoint plugin for WordPress**. It was originally a core module of the VGT Sentinel security suite and has been extracted and open-sourced as a standalone plugin.

Myrmidon turns your WordPress installation into a **cryptographic device registry and integrity verification system**. Every device that wants to communicate with your server must perform a cryptographic handshake, register with a unique keypair, and continuously report its integrity status — encrypted end-to-end.

<img width="1473" height="466" alt="myrmidon" src="https://github.com/user-attachments/assets/7d3eeca2-e296-492b-9c2f-2992a40d2aac" />


```
Traditional WordPress Security:
→ Username + Password = Access
→ No device awareness
→ No integrity checks
→ No encryption at rest

VGT Myrmidon ZTNA:
→ Cryptographic Handshake (X25519 ECDH)
→ Device Identity (Ed25519 Keypair)
→ Integrity Scoring (Root/ADB/Encryption/SecureBoot)
→ Telemetry encrypted AES-256-GCM at rest
→ Replay Attack Prevention
→ Admin approval required before any device is trusted
```

---

## 🏛️ Architecture

```
Client Device (Your App)
         ↓
GET /visiongaia/v1/device/handshake
→ Receives Server Public Key (X25519)
→ Verifies SHA-256 Fingerprint (Anti-MITM)
         ↓
POST /visiongaia/v1/device/register
→ Authenticated via WordPress Application Password
→ Sends Device Public Key (X25519)
→ Device registered as "pending" in Ledger
         ↓
Admin Dashboard
→ Reviews device details
→ Approves / Denies / Overrides trust
         ↓
POST /visiongaia/v1/device/report  (approved devices only)
→ Client encrypts telemetry with shared session key (ECDH)
→ Server decrypts, evaluates Integrity Score
→ Server signs response with HMAC-SHA256
→ Encrypted telemetry stored in Vault (AES-256-GCM)
         ↓
Myrmidon Ledger (WordPress DB)
→ All sensitive data encrypted at rest
→ Master Key stored outside web context
→ Sodium Memory Zeroing after each operation
```

---

## 💎 Feature Set

| Feature | Description |
|---|---|
| **X25519 ECDH Key Exchange** | Ephemeral session keys via Curve25519 — perfect forward secrecy per session |
| **Ed25519 Signatures** | Server signs all responses — clients can verify authenticity |
| **AES-256-GCM at Rest** | All telemetry encrypted before storage — including protocol version prefix |
| **Sodium Memory Zeroing** | Master keys wiped from memory immediately after use |
| **Replay Attack Prevention** | IV-based nonce cache with 30-second window |
| **Proxy-Aware Rate Limiting** | 20 requests/minute per IP, Cloudflare/proxy-aware |
| **Integrity Scoring** | 100-point score: Root, Encryption, ADB, Secure Boot, Firewall |
| **Device Ledger** | Full device registry with status tracking and last-seen timestamps |
| **Admin Approval Flow** | Devices start as "pending" — admin must explicitly authorize |
| **Security Override** | Manual trust override with audit logging |
| **AEGIS Co-op Mode** | Integrates with VGT Sentinel AEGIS — whitelists own API endpoints |
| **WordPress App Passwords** | Uses native WP Application Passwords for authentication |
| **SHA-256 Fingerprint** | Server identity verification — display and compare to prevent MITM |

---

## 🔐 Cryptographic Specifications

```
Key Exchange:        X25519 (ECDH via Libsodium sodium_crypto_box_keypair)
Signatures:          Ed25519 (via Libsodium) + HMAC-SHA256 response signing
Encryption:          AES-256-GCM (OpenSSL, OPENSSL_RAW_DATA)
IV:                  12-byte random (random_bytes) per encryption operation
Protocol Version:    0x01 prefix on all encrypted blobs
Memory Hygiene:      sodium_memzero() on all key material after use
Rate Limit Hashing:  SHA-256 of client IP (privacy-preserving)
Replay Prevention:   SHA-256(device_id + IV) → 30s transient cache
Master Key:          32-byte random, base64-encoded, stored outside web-root
Key Generation:      PHP random_bytes(32) with atomic race condition protection
```

---

## 📊 Integrity Scoring

Myrmidon evaluates each device report and calculates an **Integrity Score (0–100)**:

| Check | Penalty | Severity |
|---|---|---|
| **Root / Jailbreak detected** | -100 (score = 0) | 🔴 CRITICAL |
| **Disk encryption inactive** | -40 | 🟠 HIGH |
| **ADB Debugging enabled** (Android) | -30 | 🟡 MEDIUM |
| **Secure Boot disabled** (Windows/Linux) | -20 | 🟡 MEDIUM |

**Status thresholds:**

```
Score 90–100:  SECURE   ✅
Score 50–89:   WARNING  ⚠️
Score 0–49:    COMPROMISED ❌
```

---

## 🖥️ Admin Dashboard

The Myrmidon Dashboard is a full-featured **device management interface** inside WordPress Admin:

```
┌─────────────────────────────────────────────────────────────┐
│  MYRMIDON LEDGER          │  SERVER IDENTITY               │
│  Total Devices: 12        │  SHA-256 Fingerprint:          │
│  Crypto Engine: AES-256   │  A3F2 9B11 CC4E ...            │
│  Action Req.: 2 (blinking)│  Sodium: ACTIVE                │
└─────────────────────────────────────────────────────────────┘

Device Table:
┌──────────┬────────────┬────────┬───────┬─────────┬─────────┐
│ DEVICE   │ USER       │ OS     │ SCORE │ STATUS  │ ACTIONS │
├──────────┼────────────┼────────┼───────┼─────────┼─────────┤
│ iPhone15 │ rene       │ iOS    │  95   │ SECURE  │ [✓][✗]  │
│ Win-PC   │ admin      │ Win    │  72   │ WARNING │ [✓][✗]  │
│ Unknown  │ pending    │ ?      │   0   │ PENDING │ [✓][✗]  │
└──────────┴────────────┴────────┴───────┴─────────┴─────────┘

Expandable Detail Row:
→ Device Metadata (OS, Last Seen, Device ID)
→ Integrity Audit (Root, Encryption, SecureBoot, Firewall, ADB)
→ Detected Threats list
→ Approve / Override / Delete actions
```

---

## 🔌 REST API Endpoints

All endpoints are under `/wp-json/visiongaia/v1/`

### `GET /device/handshake`
**Public endpoint** — No authentication required.

Returns the server's X25519 public key and SHA-256 fingerprint for MITM verification.

```json
{
  "public_key": "base64encodedX25519PublicKey==",
  "algo": "X25519",
  "fingerprint": "a3f29b11cc4e..."
}
```

### `POST /device/register`
**Authenticated** — Requires WordPress Application Password.

Registers a new device or updates an existing one. Device starts in `pending` status until admin approval.

```json
{
  "device_id": "unique-device-uuid",
  "device_name": "My iPhone 15",
  "os_type": "ios",
  "public_key": "base64encodedClientX25519Key=="
}
```

### `POST /device/report`
**Strictly authenticated + device must be approved.**

Submits encrypted integrity telemetry. Server decrypts, scores, stores encrypted, and returns HMAC-signed response.

```json
{
  "device_id": "unique-device-uuid",
  "iv": "base64encodedIV==",
  "payload": "base64encryptedTelemetry=="
}
```

**Telemetry payload (before encryption):**
```json
{
  "is_rooted": false,
  "encryption_active": true,
  "secure_boot": true,
  "firewall_active": true,
  "adb_enabled": false,
  "os_type": "android"
}
```

---

## 📱 Client App — Build It Yourself

> **Myrmidon is a server-side plugin only.** It provides the cryptographic backend, the device ledger, and the admin interface. **There is no official client app.**

To use Myrmidon, you need to build your own client application for mobile (Android/iOS) or desktop (Windows/Linux/macOS) that implements the protocol.

### What your client app needs to implement:

```
1. HANDSHAKE
   GET /wp-json/visiongaia/v1/device/handshake
   → Store server public key
   → Verify SHA-256 fingerprint (show to user for manual verification)

2. KEY GENERATION
   → Generate X25519 keypair on the client
   → Store private key securely (Keychain / Android Keystore)

3. REGISTRATION
   POST /wp-json/visiongaia/v1/device/register
   → Authenticate with WP Application Password
   → Send your X25519 public key + device metadata

4. ECDH SESSION KEY DERIVATION
   → Compute shared secret: ECDH(client_private, server_public)
   → Use as AES-256 session key for telemetry encryption

5. TELEMETRY COLLECTION
   → Collect device integrity data:
     - is_rooted (Root/Jailbreak detection)
     - encryption_active (BitLocker/FileVault/dm-crypt)
     - secure_boot (UEFI Secure Boot status)
     - firewall_active (OS Firewall status)
     - adb_enabled (Android Debug Bridge)
     - os_type (android/ios/windows/linux/macos)

6. ENCRYPTED REPORT
   POST /wp-json/visiongaia/v1/device/report
   → Encrypt telemetry with shared session key (AES-256-GCM)
   → Send IV + encrypted payload
   → Verify server HMAC-SHA256 signature on response
```

### Recommended libraries per platform:

| Platform | Crypto Library | Notes |
|---|---|---|
| **Android (Kotlin)** | `libsodium-jni` or `Tink` | Android Keystore for key storage |
| **iOS (Swift)** | `swift-sodium` or `CryptoKit` | Secure Enclave / Keychain for storage |
| **Windows (.NET)** | `libsodium-net` | DPAPI for key storage |
| **Linux (Python)** | `PyNaCl` | Use system keyring |
| **Cross-platform** | `libsodium` bindings | Available for most languages |

> The server uses `sodium_crypto_box_keypair()` (X25519/Curve25519). Your client must use the same curve for the ECDH key exchange to work.

---

## ⚙️ Requirements

| Requirement | Minimum |
|---|---|
| **WordPress** | 5.8+ |
| **PHP** | 7.4+ (Strict Types) |
| **PHP Extension** | `sodium` (Libsodium) |
| **PHP Extension** | `openssl` |
| **MySQL / MariaDB** | 5.7+ / 10.3+ |
| **WordPress Feature** | Application Passwords enabled |

### Check requirements:

```bash
# Check PHP Sodium
php -m | grep sodium

# Check OpenSSL
php -m | grep openssl

# Both must return the extension name
```

---

## 🚀 Installation

```bash
# 1. Download or clone into WordPress plugins directory
cd /var/www/html/wp-content/plugins/
git clone https://github.com/visiongaiatechnology/vgtmyrmidon

# 2. Activate in WordPress Admin
# Plugins → VGT Myrmidon Core → Activate

# 3. Navigate to dashboard
# WordPress Admin → Myrmidon ZTNA

# 4. Note your Server Fingerprint
# Compare with clients during initial setup to prevent MITM
```

On first activation, Myrmidon automatically:

```
→ Generates AES-256 Master Key (stored securely)
→ Generates X25519 Server Keypair
→ Creates the Myrmidon Ledger database table
→ Registers all REST API endpoints
→ Integrates with VGT Sentinel AEGIS (if present)
```

---

## 🔒 Security Notes

**On the `CF-Connecting-IP` header:**
Myrmidon reads `CF-Connecting-IP` for rate limiting. This is only trustworthy if your server is configured to accept traffic exclusively from Cloudflare IP ranges. Without this configuration, the header can be spoofed.

**On Application Passwords:**
Device registration requires a WordPress Application Password. Generate one per device in `Users → Profile → Application Passwords`. Revoke it if a device is lost or compromised.

**On the Master Key:**
The AES-256 Master Key is stored as a WordPress option with `autoload = no`. It is never logged, never transmitted, and wiped from memory immediately after use via `sodium_memzero()`.

**On Pending Devices:**
New devices are always registered as `pending`. No telemetry is accepted from pending devices. Always verify device identity before approving.

---

## 💰 Support the Project

[![Donate via PayPal](https://img.shields.io/badge/Donate-PayPal-00457C?style=for-the-badge&logo=paypal)](https://www.paypal.com/paypalme/dergoldenelotus)

| Method | Address |
|---|---|
| **PayPal** | [paypal.me/dergoldenelotus](https://www.paypal.com/paypalme/dergoldenelotus) |
| **Bitcoin** | `bc1q3ue5gq822tddmkdrek79adlkm36fatat3lz0dm` |
| **ETH** | `0xD37DEfb09e07bD775EaaE9ccDaFE3a5b2348Fe85` |
| **USDT (ERC-20)** | `0xD37DEfb09e07bD775EaaE9ccDaFE3a5b2348Fe85` |

---

## 🔗 VGT Ecosystem

| Tool | Type | Purpose |
|---|---|---|
| 🛡️ **VGT Myrmidon** | **ZTNA** | Zero Trust device registry and integrity verification for WordPress |
| ⚔️ **[VGT Auto-Punisher](https://github.com/visiongaiatechnology/vgt-auto-punisher)** | **IDS** | L4+L7 Hybrid IDS — attackers terminated before they even knock |
| 📊 **[VGT Dattrack](https://github.com/visiongaiatechnology/dattrack)** | **Analytics** | Sovereign analytics engine — your data, your server, no third parties |
| 🌐 **[VGT Global Threat Sync](https://github.com/visiongaiatechnology/vgt-global-threat-sync)** | **Preventive** | Daily threat feed — block known attackers before they arrive |
| 🔥 **[VGT Windows Firewall Burner](https://github.com/visiongaiatechnology/vgt-windows-burner)** | **Windows** | 280,000+ APT IPs in native Windows Firewall |

---

## 🤝 Contributing

Pull requests are welcome. For major changes, open an issue first.

Licensed under **AGPLv3** — *"For Humans, not for SaaS Corporations."*

---

## 🏢 Built by VisionGaia Technology

[![VGT](https://img.shields.io/badge/VGT-VisionGaia_Technology-red?style=for-the-badge)](https://visiongaiatechnology.de)

VisionGaia Technology builds enterprise-grade security infrastructure — engineered to the DIAMANT VGT SUPREME standard.

> *"Myrmidon was born inside VGT Sentinel as a classified module. Today it's open source — because Zero Trust shouldn't be a privilege."*

---

*Version 1.0.7 — VGT Myrmidon // Zero Trust Network Access // X25519 + Ed25519 + AES-256-GCM // AGPLv3*
