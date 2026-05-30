#!/usr/bin/env python3
"""Hybrid encryption helper for CipherShell Chat."""

from __future__ import annotations

import argparse
import base64
import json
import os
import sys
import time
from pathlib import Path

try:
    from cryptography.exceptions import InvalidSignature
    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.asymmetric import padding, rsa
    from cryptography.hazmat.primitives.ciphers.aead import AESGCM
except ModuleNotFoundError as e:
    print(f"Crypto error: Missing dependency: {e}", file=sys.stderr)
    print("Install it with:", file=sys.stderr)
    print("  Termux: pkg install python-cryptography", file=sys.stderr)
    print("  Linux:  pip install cryptography", file=sys.stderr)
    sys.exit(1)


VERSION = 2
ALG = "RSA-OAEP-SHA256+AES-256-GCM+RSA-PSS-SHA256"


def b64e(data: bytes) -> str:
    return base64.b64encode(data).decode("ascii")


def b64d(data: str) -> bytes:
    return base64.b64decode(data.encode("ascii"), validate=True)


def load_public_key(path: Path) -> rsa.RSAPublicKey:
    with path.open("rb") as fh:
        key = serialization.load_pem_public_key(fh.read())
    if not isinstance(key, rsa.RSAPublicKey):
        raise ValueError("Only RSA public keys are supported.")
    return key


def load_private_key(path: Path) -> rsa.RSAPrivateKey:
    with path.open("rb") as fh:
        key = serialization.load_pem_private_key(fh.read(), password=None)
    if not isinstance(key, rsa.RSAPrivateKey):
        raise ValueError("Only RSA private keys are supported.")
    return key


def canonical_bytes(package: dict) -> bytes:
    unsigned = {key: value for key, value in package.items() if key != "signature"}
    return json.dumps(unsigned, sort_keys=True, separators=(",", ":")).encode("utf-8")


def encrypt(args: argparse.Namespace) -> int:
    recipient_public = load_public_key(Path(args.recipient_public_key))
    sender_private = load_private_key(Path(args.sender_private_key))
    plaintext = Path(args.input).read_bytes()

    aes_key = os.urandom(32)
    nonce = os.urandom(12)
    aad = f"{args.sender}\0{args.recipient}\0{VERSION}".encode("utf-8")
    ciphertext = AESGCM(aes_key).encrypt(nonce, plaintext, aad)
    encrypted_key = recipient_public.encrypt(
        aes_key,
        padding.OAEP(
            mgf=padding.MGF1(algorithm=hashes.SHA256()),
            algorithm=hashes.SHA256(),
            label=None,
        ),
    )

    package = {
        "version": VERSION,
        "algorithm": ALG,
        "sender": args.sender,
        "recipient": args.recipient,
        "created_at": int(time.time()),
        "encrypted_key": b64e(encrypted_key),
        "nonce": b64e(nonce),
        "ciphertext": b64e(ciphertext),
    }
    signature = sender_private.sign(
        canonical_bytes(package),
        padding.PSS(mgf=padding.MGF1(hashes.SHA256()), salt_length=padding.PSS.MAX_LENGTH),
        hashes.SHA256(),
    )
    package["signature"] = b64e(signature)

    encoded = base64.b64encode(
        json.dumps(package, sort_keys=True, separators=(",", ":")).encode("utf-8")
    )
    Path(args.output).write_bytes(encoded)
    return 0


def decrypt(args: argparse.Namespace) -> int:
    recipient_private = load_private_key(Path(args.recipient_private_key))
    sender_public = load_public_key(Path(args.sender_public_key))
    encoded = Path(args.input).read_text(encoding="ascii").strip()

    package = json.loads(base64.b64decode(encoded.encode("ascii"), validate=True))
    if package.get("version") != VERSION:
        raise ValueError("Unsupported encrypted package version.")
    if package.get("algorithm") != ALG:
        raise ValueError("Unsupported encrypted package algorithm.")
    if package.get("sender") != args.expected_sender:
        raise ValueError("Encrypted package sender does not match the server envelope.")
    if package.get("recipient") != args.expected_recipient:
        raise ValueError("Encrypted package recipient does not match this listener.")

    try:
        sender_public.verify(
            b64d(package["signature"]),
            canonical_bytes(package),
            padding.PSS(mgf=padding.MGF1(hashes.SHA256()), salt_length=padding.PSS.MAX_LENGTH),
            hashes.SHA256(),
        )
    except InvalidSignature as exc:
        raise ValueError(
            "Signature verification failed. The sender's public key "
            f"({args.sender_public_key}) does not match the signing key. "
            "If using multiple devices, ensure server/users.db/ is synced: "
            "run 'bash scripts/export_keys.sh' on the sender's device and "
            "'bash scripts/import_keys.sh' on this device."
        ) from exc

    try:
        aes_key = recipient_private.decrypt(
            b64d(package["encrypted_key"]),
            padding.OAEP(
                mgf=padding.MGF1(algorithm=hashes.SHA256()),
                algorithm=hashes.SHA256(),
                label=None,
            ),
        )
    except ValueError as exc:
        raise ValueError(
            "Failed to decrypt AES key. Your private key does not match the "
            f"public key used to encrypt this message ({args.recipient_private_key}). "
            "If using multiple devices, re-register on the same device or "
            "sync server/users.db/ across all devices."
        ) from exc

    aad = f"{args.expected_sender}\0{args.expected_recipient}\0{VERSION}".encode("utf-8")
    try:
        plaintext = AESGCM(aes_key).decrypt(
            b64d(package["nonce"]),
            b64d(package["ciphertext"]),
            aad,
        )
    except Exception as exc:
        raise ValueError(
            "Message decryption failed. The encrypted payload may be corrupted "
            "or the AES key does not match. Ensure both devices have synced "
            "server/users.db/ files."
        ) from exc
    Path(args.output).write_bytes(plaintext)
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="CipherShell hybrid encryption helper")
    subparsers = parser.add_subparsers(dest="command", required=True)

    enc = subparsers.add_parser("encrypt", help="encrypt and sign a plaintext file")
    enc.add_argument("--recipient-public-key", required=True)
    enc.add_argument("--sender-private-key", required=True)
    enc.add_argument("--sender", required=True)
    enc.add_argument("--recipient", required=True)
    enc.add_argument("--input", required=True)
    enc.add_argument("--output", required=True)
    enc.set_defaults(func=encrypt)

    dec = subparsers.add_parser("decrypt", help="verify and decrypt a package")
    dec.add_argument("--recipient-private-key", required=True)
    dec.add_argument("--sender-public-key", required=True)
    dec.add_argument("--expected-sender", required=True)
    dec.add_argument("--expected-recipient", required=True)
    dec.add_argument("--input", required=True)
    dec.add_argument("--output", required=True)
    dec.set_defaults(func=decrypt)
    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    try:
        return args.func(args)
    except Exception as exc:
        print(f"Crypto error: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
