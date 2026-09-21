#!/usr/bin/env python3
"""Build and verify a safe, deterministic project distribution archive."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import stat
import sys
import zipfile
from io import BytesIO
from pathlib import Path, PurePosixPath
from typing import Iterable, Mapping


EXCLUDED_DIRS = {
    ".agents",
    ".claude",
    ".codex",
    ".codexbuddy",
    ".codebuddy",
    ".git",
    ".hyperframes",
    ".idea",
    ".qoder",
    ".trae",
    ".vscode",
    ".zcode",
    "__pycache__",
    "assets",
    "build",
    "docs",
    "generated",
    "material",
    "node_modules",
    "runtime",
    "tests",
    "tools",
}

RELOCATIONS = {
    "template": "public/source/template",
    "specs": "public/source/spec/specs",
    "rules": "public/source/spec/rules",
    "AGENTS.md": "public/source/spec/AGENTS.md",
}

EXCLUDED_FILE_NAMES = {
    "api_docs.php",
    "upgrade.local.failures.json",
}

EXCLUDED_RELATIVE_FILES = {
    ".gitignore",
    ".phpstorm.meta.php",
    ".phpunit.result.cache",
    "ICONFONT_MISSING.md",
    "README.md",
    "phpunit.xml",
    "database/migrations/v1.2/upgrade.sql",
    "template/uniapp/src/styles/iconfont/demo.css",
    "template/uniapp/src/styles/iconfont/demo_index.html",
    "public/source/template/uniapp/src/styles/iconfont/demo.css",
    "public/source/template/uniapp/src/styles/iconfont/demo_index.html",
}

EXCLUDED_RULES = sorted(EXCLUDED_DIRS) + [
    ".DS_Store",
    ".env*",
    "*.log",
    "*.pem",
    "*.key",
    "*.p12",
    "*.pfx",
    "*.jks",
    "*.pyc",
    "*.zip",
    "*.tar",
    "*.tgz",
    "*.gz",
    "api_docs.php",
    "upgrade.local.failures.json",
    "database/migrations/v1.2/upgrade.sql",
    ".gitignore",
    ".phpstorm.meta.php",
    ".phpunit.result.cache",
    "ICONFONT_MISSING.md",
    "README.md (root)",
    "phpunit.xml",
    "template/uniapp/src/styles/iconfont/demo.css",
    "template/uniapp/src/styles/iconfont/demo_index.html",
]

TEXT_SUFFIXES = {
    ".css",
    ".csv",
    ".htm",
    ".html",
    ".ini",
    ".js",
    ".json",
    ".md",
    ".php",
    ".scss",
    ".sh",
    ".sql",
    ".svg",
    ".toml",
    ".ts",
    ".txt",
    ".vue",
    ".xml",
    ".yaml",
    ".yml",
    ".py",
}

SENSITIVE_NAME_PATTERNS = (
    re.compile(r"^\.env(?:\..*)?$", re.IGNORECASE),
    re.compile(r"(?:^|[._-])(id_rsa|private[_-]?key|credentials?|secrets?)(?:[._-]|$)", re.IGNORECASE),
    re.compile(r"\.(?:pem|key|p12|pfx|jks|keystore)$", re.IGNORECASE),
    re.compile(r"\.(?:log|pyc|zip|tar|tgz|gz)$", re.IGNORECASE),
)

PRIVATE_KEY_RE = re.compile(r"-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----\s+[A-Za-z0-9+/]{32,}")
AWS_KEY_RE = re.compile(r"\bAKIA[0-9A-Z]{16}\b")
SECRET_TOKEN_RE = re.compile(r"\b(?:sk|rk)-(?:live|test)-[A-Za-z0-9_-]{16,}\b")
GENERIC_SECRET_RE = re.compile(
    r"(?i)\b(?:api[_-]?key|secret|access[_-]?token|client[_-]?secret)\b\s*[:=]\s*['\"][A-Za-z0-9_\-]{16,}['\"]"
)
REMOTE_IMAGE_RE = re.compile(
    r"https?://[^\s'\"<>\\)\]]+?\.(?:png|jpe?g|gif|webp|svg|ico|avif)(?:\?[^\s'\"<>\\)\]]*)?",
    re.IGNORECASE,
)
LOCAL_SQL_IMAGE_RE = re.compile(
    r"/storage/[A-Za-z0-9_./-]+?\.(?:png|jpe?g|gif|webp|svg|ico|avif)(?![A-Za-z0-9])",
    re.IGNORECASE,
)
TABLE_RE = re.compile(
    r"\b(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|ALTER\s+TABLE|DELETE\s+FROM|UPDATE)\s+"
    r"((?:`[^`]+`|[A-Za-z0-9_$-]+)(?:\s*\.\s*(?:`[^`]+`|[A-Za-z0-9_$-]+))?)",
    re.IGNORECASE,
)
DATA_STATEMENT_RE = re.compile(r"\b(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\b", re.IGNORECASE)
VENDOR_TEST_CREDENTIAL_RE = re.compile(
    rb"(\$id\s*=\s*'\s*';\s*\$secret\s*=\s*')[^']+(';)", re.IGNORECASE
)
VENDOR_EXAMPLE_PRIVATE_KEY_RE = re.compile(
    rb"-----BEGIN ([A-Z0-9 ]*PRIVATE KEY)-----.*?-----END \1-----", re.DOTALL
)


class PackageError(RuntimeError):
    """Raised for a package input or verification failure."""


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Build or verify a complete distribution ZIP.")
    subparsers = parser.add_subparsers(dest="command", required=True)

    build = subparsers.add_parser("build", help="build a deterministic distribution ZIP")
    build.add_argument("--root", default=".", help="repository root")
    build.add_argument("--install-sql", required=True, help="default-data SQL path relative to root")
    build.add_argument("--output", required=True, help="output ZIP path")
    build.add_argument("--version", default="dev", help="distribution version label")
    build.add_argument("--public-source", action="append", default=[], metavar="SRC=DST")
    build.add_argument("--dry-run", action="store_true", help="print the plan without writing a ZIP")

    verify = subparsers.add_parser("verify", help="verify an existing distribution ZIP")
    verify.add_argument("--package", required=True, help="ZIP file to verify")
    return parser.parse_args()


def normalized_relative(path: str) -> str:
    if path.startswith(("/", "\\")) or re.match(r"^[A-Za-z]:[\\/]", path):
        raise PackageError(f"不允许绝对路径：{path}")
    normalized = path.replace("\\", "/").strip("/")
    if not normalized or normalized == "." or normalized.startswith("../") or "/../" in normalized:
        raise PackageError(f"非法相对路径：{path}")
    if normalized.startswith("./"):
        normalized = normalized[2:]
    return normalized


def relative_to_root(root: Path, path: Path) -> str:
    try:
        return path.resolve().relative_to(root.resolve()).as_posix()
    except ValueError as exc:
        raise PackageError(f"路径必须位于仓库根目录内：{path}") from exc


def is_excluded_file(relative: str) -> bool:
    path = PurePosixPath(relative)
    if relative in EXCLUDED_RELATIVE_FILES or path.name in EXCLUDED_FILE_NAMES:
        return True
    if any(part == ".DS_Store" for part in path.parts):
        return True
    name = path.name
    if name == ".DS_Store" or name.endswith(".log"):
        return True
    # Vendor Credential.php / PrivateKey.php are runtime classes, not credential files.
    is_php_class = path.suffix.lower() == ".php" and name[:1].isupper()
    if not is_php_class and any(pattern.search(name) for pattern in SENSITIVE_NAME_PATTERNS):
        return True
    if relative.startswith("vendor/"):
        return False
    is_skill_source = relative == ".codex/skills" or relative.startswith(".codex/skills/")
    if any(part in EXCLUDED_DIRS for part in path.parts) and not (".codex" in path.parts and is_skill_source):
        return True
    return False


def assert_no_symlink(path: Path) -> None:
    if path.is_symlink():
        raise PackageError(f"不允许打包软链接：{path}")


def parse_relocations(items: Iterable[str]) -> dict[str, str]:
    result = dict(RELOCATIONS)
    for item in items:
        if "=" not in item:
            raise PackageError(f"--public-source 必须使用 SRC=DST：{item}")
        source, destination = item.split("=", 1)
        source = normalized_relative(source)
        destination = normalized_relative(destination)
        result[source] = destination
    return result


def strip_sql_comments(sql: str) -> str:
    sql = re.sub(r"/\*.*?\*/", " ", sql, flags=re.DOTALL)
    sql = re.sub(r"--[^\n]*", " ", sql)
    sql = re.sub(r"#[^\n]*", " ", sql)
    return sql


def table_name(value: str) -> str:
    parts = [part.strip().strip("`") for part in value.split(".")]
    if not parts or any(not re.fullmatch(r"[A-Za-z0-9_$-]+", part) for part in parts):
        raise PackageError(f"SQL 表名不安全：{value}")
    return parts[-1]


def sql_tables(sql: str) -> list[str]:
    cleaned = strip_sql_comments(sql)
    names = {table_name(match.group(1)) for match in TABLE_RE.finditer(cleaned)}
    return sorted(names, key=lambda value: value.lower())


def validate_install_sql(sql_path: Path) -> tuple[str, list[str], str]:
    if not sql_path.is_file():
        raise PackageError(f"找不到安装 SQL：{sql_path}")
    sql = sql_path.read_text(encoding="utf-8")
    assert_default_data(sql)
    tables = sql_tables(sql)
    if not tables:
        raise PackageError("安装 SQL 未能提取出任何表名")
    lines = [
        "-- Generated by distribution-package; review before execution.",
        "SET FOREIGN_KEY_CHECKS=0;",
    ]
    lines.extend(f"TRUNCATE TABLE `{name.replace('`', '``')}`;" for name in tables)
    lines.extend(["SET FOREIGN_KEY_CHECKS=1;", ""])
    clean_sql = "\n".join(lines)
    return sql, tables, clean_sql


def assert_default_data(sql: str) -> None:
    if not DATA_STATEMENT_RE.search(strip_sql_comments(sql)):
        raise PackageError("安装 SQL 必须包含 INSERT INTO 或 REPLACE INTO 默认数据")


def sensitive_hits(text: str) -> list[str]:
    hits: list[str] = []
    for label, pattern in (
        ("private-key", PRIVATE_KEY_RE),
        ("aws-access-key", AWS_KEY_RE),
        ("secret-token", SECRET_TOKEN_RE),
        ("generic-secret", GENERIC_SECRET_RE),
    ):
        if pattern.search(text):
            hits.append(label)
    return hits


def scan_text(path: str, data: bytes) -> None:
    if Path(path).suffix.lower() not in TEXT_SUFFIXES:
        return
    try:
        text = data.decode("utf-8")
    except UnicodeDecodeError:
        return
    hits = sensitive_hits(text)
    if hits:
        raise PackageError(f"文件疑似包含敏感信息（{','.join(hits)}）：{path}")
    image_hits = [] if path.startswith("vendor/") else REMOTE_IMAGE_RE.findall(text)
    if image_hits:
        sample = image_hits[0][:160]
        raise PackageError(f"文件仍引用远程图片，需先本地化：{path} -> {sample}")


def add_entry(entries: dict[str, bytes], name: str, data: bytes) -> None:
    name = normalized_relative(name)
    if name in entries:
        raise PackageError(f"包内路径重复：{name}")
    scan_text(name, data)
    entries[name] = data


def sanitize_vendor_example(relative: str, data: bytes) -> bytes:
    if relative == "vendor/aliyuncs/oss-sdk-php/tests/OSS/Tests/OssClientTest.php":
        data, count = VENDOR_TEST_CREDENTIAL_RE.subn(rb"\1redacted\2", data)
    elif relative == "vendor/firebase/php-jwt/README.md":
        data, count = VENDOR_EXAMPLE_PRIVATE_KEY_RE.subn(b"[REDACTED EXAMPLE PRIVATE KEY]", data)
    else:
        return data
    if count != 1:
        raise PackageError(f"第三方示例脱敏匹配数量异常：{relative}")
    return data


def map_destination(source_relative: str, relocations: Mapping[str, str]) -> str | None:
    for source, destination in sorted(relocations.items(), key=lambda item: len(item[0]), reverse=True):
        if source_relative == source:
            return destination
        prefix = source.rstrip("/") + "/"
        if source_relative.startswith(prefix):
            suffix = source_relative[len(prefix) :]
            return f"{destination.rstrip('/')}/{suffix}"
    return None


def iter_files(root: Path, output: Path, relocations: Mapping[str, str]) -> Iterable[tuple[Path, str]]:
    for current, dirs, files in os.walk(root, topdown=True, followlinks=False):
        current_path = Path(current)
        dirs[:] = sorted(dirs)
        files = sorted(files)
        for directory in list(dirs):
            if directory == ".codex" and current_path == root:
                continue
            if current_path == root / ".codex" and directory != "skills":
                dirs.remove(directory)
                continue
            if current_path != root / "vendor" and root / "vendor" not in current_path.parents and (
                directory in EXCLUDED_DIRS or directory == ".DS_Store"
            ):
                dirs.remove(directory)
        for file_name in files:
            source = current_path / file_name
            assert_no_symlink(source)
            if source.resolve() == output.resolve():
                continue
            relative = relative_to_root(root, source)
            if is_excluded_file(relative):
                continue
            destination = map_destination(relative, relocations) or relative
            # Frontend source is delivered under public/source; compiled output stays in public.
            if relative.startswith("template/") and ("/dist/" in relative or relative.endswith("/dist")):
                continue
            yield source, normalized_relative(destination)


def build_entries(
    root: Path, output: Path, install_sql: Path, relocations: Mapping[str, str]
) -> tuple[dict[str, bytes], list[str], list[str]]:
    entries: dict[str, bytes] = {}
    excluded: list[str] = list(EXCLUDED_RULES)
    for source, destination in iter_files(root, output, relocations):
        if destination in entries:
            raise PackageError(f"重定位后包内路径重复：{destination}")
        relative = relative_to_root(root, source)
        data = sanitize_vendor_example(relative, source.read_bytes())
        add_entry(entries, destination, data)

    install_data, tables, clean_sql = validate_install_sql(install_sql)
    original_install = relative_to_root(root, install_sql)
    if original_install in entries:
        del entries[original_install]
    add_entry(entries, "database/install.sql", install_data.encode("utf-8"))
    add_entry(entries, "database/clean.sql", clean_sql.encode("utf-8"))
    excluded.append(original_install)
    return entries, excluded, tables


def manifest_content(version: str, entries: Mapping[str, bytes], tables: list[str], excluded: list[str]) -> bytes:
    checksums = {name: hashlib.sha256(data).hexdigest() for name, data in sorted(entries.items())}
    manifest = {
        "schema_version": 1,
        "version": version,
        "sql_tables": tables,
        "excluded": sorted(set(excluded)),
        "files": checksums,
    }
    return (json.dumps(manifest, ensure_ascii=False, indent=2, sort_keys=True) + "\n").encode("utf-8")


def zip_bytes(entries: Mapping[str, bytes]) -> bytes:
    buffer = BytesIO()
    with zipfile.ZipFile(buffer, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in sorted(entries):
            info = zipfile.ZipInfo(name, date_time=(2020, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            archive.writestr(info, entries[name])
    return buffer.getvalue()


def verify_entries(entries: Mapping[str, bytes]) -> dict[str, object]:
    required = {"database/install.sql", "database/clean.sql", "PACKAGE-MANIFEST.json"}
    missing = sorted(required - set(entries))
    if missing:
        raise PackageError(f"包内缺少固定入口：{', '.join(missing)}")
    for name, data in entries.items():
        parts = PurePosixPath(name).parts
        if name.startswith("/") or ".." in parts:
            raise PackageError(f"ZIP 条目路径穿越：{name}")
        if is_excluded_file(name):
            raise PackageError(f"ZIP 包含禁止条目：{name}")
        scan_text(name, data)
    manifest = json.loads(entries["PACKAGE-MANIFEST.json"].decode("utf-8"))
    if not isinstance(manifest, dict) or manifest.get("schema_version") != 1:
        raise PackageError("PACKAGE-MANIFEST.json 格式不正确")
    install_sql = entries["database/install.sql"].decode("utf-8")
    clean_sql = entries["database/clean.sql"].decode("utf-8")
    assert_default_data(install_sql)
    missing_images = sorted({
        image for image in LOCAL_SQL_IMAGE_RE.findall(install_sql)
        if f"public{image}" not in entries
    })
    if missing_images:
        raise PackageError(
            f"安装 SQL 引用 {len(missing_images)} 个包内缺失的本地图片，示例：{', '.join(missing_images[:3])}"
        )
    tables = sql_tables(install_sql)
    for table in tables:
        if f"TRUNCATE TABLE `{table}`;" not in clean_sql:
            raise PackageError(f"clean.sql 缺少表：{table}")
    if manifest.get("sql_tables") != tables:
        raise PackageError("清单中的 SQL 表名与 install.sql 不一致")
    recorded = manifest.get("files")
    if not isinstance(recorded, dict):
        raise PackageError("PACKAGE-MANIFEST.json 缺少 files 哈希清单")
    expected_files = set(entries) - {"PACKAGE-MANIFEST.json"}
    if set(recorded) != expected_files:
        raise PackageError("PACKAGE-MANIFEST.json 的文件范围与 ZIP 不一致")
    for name, data in entries.items():
        if name == "PACKAGE-MANIFEST.json":
            continue
        if recorded.get(name) != hashlib.sha256(data).hexdigest():
            raise PackageError(f"文件哈希与清单不一致：{name}")
    return {
        "file_count": len(entries),
        "sql_tables": tables,
        "sha256": hashlib.sha256(zip_bytes(entries)).hexdigest(),
    }


def load_zip(path: Path) -> dict[str, bytes]:
    if not path.is_file():
        raise PackageError(f"找不到 ZIP：{path}")
    entries: dict[str, bytes] = {}
    with zipfile.ZipFile(path, "r") as archive:
        for info in archive.infolist():
            if info.is_dir():
                continue
            name = normalized_relative(info.filename)
            if name in entries:
                raise PackageError(f"ZIP 条目重复：{name}")
            entries[name] = archive.read(info)
    return entries


def build(args: argparse.Namespace) -> dict[str, object]:
    root = Path(args.root).resolve()
    if not root.is_dir():
        raise PackageError(f"仓库根目录不存在：{root}")
    output = Path(args.output).expanduser().resolve()
    if output.exists():
        raise PackageError(f"拒绝覆盖已有输出：{output}")
    install_sql = (root / normalized_relative(args.install_sql)).resolve()
    relative_to_root(root, install_sql)
    relocations = parse_relocations(args.public_source)
    entries, excluded, tables = build_entries(root, output, install_sql, relocations)
    entries["PACKAGE-MANIFEST.json"] = manifest_content(args.version, entries, tables, excluded)
    summary = {
        "ok": True,
        "command": "build",
        "dry_run": bool(args.dry_run),
        "version": args.version,
        "file_count": len(entries),
        "total_bytes": sum(len(data) for data in entries.values()),
        "sql_tables": tables,
        "excluded": sorted(set(excluded)),
        "included_sample": sorted(entries)[:20],
    }
    if args.dry_run:
        verify_entries(entries)
        return summary
    output.parent.mkdir(parents=True, exist_ok=True)
    payload = zip_bytes(entries)
    output.write_bytes(payload)
    verified = verify_entries(load_zip(output))
    summary.update(
        {
            "output": str(output),
            "package_bytes": output.stat().st_size,
            "package_sha256": hashlib.sha256(payload).hexdigest(),
            "verified": verified,
        }
    )
    return summary


def main() -> int:
    args = parse_args()
    try:
        if args.command == "build":
            result = build(args)
        else:
            package = Path(args.package).expanduser().resolve()
            result = {
                "ok": True,
                "command": "verify",
                "package": str(package),
                "verified": verify_entries(load_zip(package)),
            }
        print(json.dumps(result, ensure_ascii=False, indent=2, sort_keys=True))
        return 0
    except (PackageError, OSError, ValueError, json.JSONDecodeError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
