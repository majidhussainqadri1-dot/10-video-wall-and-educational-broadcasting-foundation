#!/usr/bin/env python3
"""Verify File10's 64 exact requirements against the user's original V12.3 DOCX.

Read-only; does not upload/expose the Word document. Stdlib ZIP/XML parser
follows Word's top-level body paragraph order, matching python-docx .paragraphs.
This is source-text fidelity, NOT code, Staging, Live or production acceptance.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import pathlib
import re
import sys
import unicodedata
import xml.etree.ElementTree as ET
import zipfile

PLAN_SHA256 = "c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf"
WORD = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
IDENTITY = re.compile(r"^(F10-(?:FR|NFR|FUT)-\d{3}|F10-CEN-01|V12\.1-STO-\d{2})(?=\s)")
FIELDS = ("requirement_id", "paragraph_index_zero_based", "codepoints_nfc", "sha256_nfc_utf8")


def reject(message: str) -> None:
    print("File10 V12.3 DOCX fidelity FAIL: " + message, file=sys.stderr)
    raise SystemExit(2)


def original_text(paragraph: ET.Element) -> str:
    # Word's paragraph.text includes text, inline hyperlinks, tabs and breaks.
    chars = []
    for node in paragraph.iter():
        if node.tag == WORD + "t":
            chars.append(node.text or "")
        elif node.tag == WORD + "tab":
            chars.append("\t")
        elif node.tag in (WORD + "br", WORD + "cr"):
            chars.append("\n")
    return unicodedata.normalize("NFC", "".join(chars).strip())


def read_manifest(path: pathlib.Path) -> dict[str, tuple[int, int, str]]:
    try:
        with path.open(encoding="utf-8", newline="") as stream:
            reader = csv.DictReader(stream, delimiter="\t")
            if tuple(reader.fieldnames or ()) != FIELDS:
                reject("manifest fields differ from frozen source schema")
            rows = list(reader)
    except OSError:
        reject("manifest not readable")
    if len(rows) != 64:
        reject("exact 64 Word source paragraphs must be listed")
    found = {}
    for item in rows:
        ident = item["requirement_id"]
        if ident in found or not IDENTITY.match(ident + " ") or not re.fullmatch(r"[a-f0-9]{64}", item["sha256_nfc_utf8"]):
            reject("repeated, malformed or unverifiable requirement entry")
        try:
            number = int(item["paragraph_index_zero_based"])
            size = int(item["codepoints_nfc"])
        except ValueError:
            reject("invalid paragraph index or Unicode length")
        if number < 0 or size < 30:
            reject("invalid source locator or suspiciously short paragraph")
        found[ident] = (number, size, item["sha256_nfc_utf8"])
    if len({x[0] for x in found.values()}) != 64:
        reject("duplicate Word paragraph locator")
    expected = {f"F10-FR-{n:03d}" for n in range(1, 20)}
    expected |= {f"F10-NFR-{n:03d}" for n in range(1, 11)}
    expected |= {f"F10-FUT-{n:03d}" for n in range(1, 25)}
    expected |= {"F10-CEN-01"}
    expected |= {f"V12.1-STO-{n:02d}" for n in range(1, 11)}
    if set(found) != expected:
        reject("one or more original File10/CF04 IDs were lost or invented")
    return found


def extract_docx(path: pathlib.Path) -> tuple[str, dict[str, tuple[int, int, str]]]:
    try:
        data = path.read_bytes()
        digest = hashlib.sha256(data).hexdigest()
        with zipfile.ZipFile(path) as doc:
            root = ET.fromstring(doc.read("word/document.xml"))
    except (OSError, KeyError, zipfile.BadZipFile, ET.ParseError) as e:
        reject("original Word DOCX cannot be read as a valid source: " + type(e).__name__)
    body = root.find(WORD + "body")
    if body is None:
        reject("source Word document missing main body")
    records = {}
    for index, paragraph in enumerate(body.findall(WORD + "p")):
        text = original_text(paragraph)
        match = IDENTITY.match(text)
        if not match:
            continue
        ident = match.group(1)
        if ident in records:
            reject("duplicate requirement in original Word paragraphs: " + ident)
        records[ident] = (index, len(text), hashlib.sha256(text.encode("utf-8")).hexdigest())
    return digest, records


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("docx", nargs="?", help="path to the unmodified master-plan V12.3 DOCX")
    parser.add_argument("--manifest", type=pathlib.Path,
        default=pathlib.Path(__file__).resolve().parent.parent /
                "docs/V12.3-FILE10-ORIGINAL-DOCX-CONTENT-DIGESTS.tsv")
    parser.add_argument("--manifest-only", action="store_true", help="check frozen identifiers/digests without private DOCX")
    parser.add_argument("--fixture-plan-sha256", default=None,
        help="for isolated test fixture only; never an authorized release check")
    args = parser.parse_args()
    frozen = read_manifest(args.manifest)
    if args.manifest_only:
        if args.docx is not None:
            reject("--manifest-only cannot pretend to verify a supplied Word file")
        print("File10 V12.3 digest manifest PASS: exact 64 IDs; original Word content NOT rechecked; NO RELEASE CLAIM")
        return
    if not args.docx:
        reject("supply the actual original V12.3 DOCX; no source validity inferred")
    document_sha, extracted = extract_docx(pathlib.Path(args.docx))
    correct_sha = args.fixture_plan_sha256 or PLAN_SHA256
    if document_sha != correct_sha:
        reject("original DOCX binary SHA-256 differs from approved V12.3")
    if extracted != frozen:
        lost = sorted(set(frozen) - set(extracted))
        extra = sorted(set(extracted) - set(frozen))
        mutated = sorted(x for x in set(extracted) & set(frozen) if extracted[x] != frozen[x])
        reject(f"source paragraph drift: missing={lost}, extra={extra}, altered={mutated}")
    if args.fixture_plan_sha256:
        print("File10 V12.3 synthetic DOCX fixture PASS (NOT original/source/release acceptance)")
    else:
        print("File10 V12.3 original DOCX 64/64 exact paragraph digests PASS; Staging/Live UNVERIFIED")


if __name__ == "__main__":
    main()
