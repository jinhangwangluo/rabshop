#!/usr/bin/env python3
"""
导出 ThinkPHP8 显式路由为 OpenAPI 3.0 JSON.

默认扫描项目根目录 route/*.php，排除 Route::miss 页面兜底路由，
并通过 x-apifox-folder 指定 Apifox 多级目录。
"""

import argparse
import json
import os
import re
from collections import Counter, defaultdict


ROUTE_FILES = ["admin.php", "merchant.php", "mobile.php", "route.php"]
HTTP_METHODS = {
    "get": ["get"],
    "post": ["post"],
    "put": ["put"],
    "delete": ["delete"],
    "patch": ["patch"],
    "any": ["get", "post", "put", "delete", "patch"],
}
RESOURCE_ACTIONS = [
    ("index", "get", ""),
    ("create", "get", "create"),
    ("save", "post", ""),
    ("read", "get", ":id"),
    ("edit", "get", ":id/edit"),
    ("update", "put", ":id"),
    ("delete", "delete", ":id"),
]
AUTH_KEYWORDS = (
    "AuthAdminLoginMiddleware",
    "AuthMerchantLoginMiddleware",
    "AuthMobileLoginMiddleware",
)
OPTIONAL_AUTH_KEYWORDS = ("OptionalAuthMobileMiddleware",)
REQUEST_METHODS = ("get", "post", "put", "param", "delete")
REQUEST_MORE_METHODS = ("getMore", "postMore")


def normalize_slashes(value):
    parts = [part.strip("/") for part in value.split("/") if part.strip("/")]
    return "/".join(parts)


def join_path(*parts):
    return normalize_slashes("/".join(part for part in parts if part is not None))


def openapi_path(path):
    normalized = normalize_slashes(path)
    converted = re.sub(r":([A-Za-z_][A-Za-z0-9_]*)", r"{\1}", normalized)
    return "/" + converted if converted else "/"


def extract_path_params(path, pattern_line=""):
    names = re.findall(r"{([A-Za-z_][A-Za-z0-9_]*)}", path)
    params = []
    for name in names:
        schema = {"type": "string"}
        if re.search(rf"['\"]{re.escape(name)}['\"]\s*=>\s*['\"]\\\\?d\+['\"]", pattern_line):
            schema = {"type": "integer"}
        params.append({
            "name": name,
            "in": "path",
            "required": True,
            "schema": schema,
            "description": name,
        })
    return params


def quoted_args(line):
    return re.findall(r"['\"]([^'\"]*)['\"]", line)


def parse_array_arg(line, method):
    match = re.search(rf"->{method}\s*\(\s*\[([^\]]*)\]\s*\)", line)
    if not match:
        return None
    return set(re.findall(r"['\"]([^'\"]+)['\"]", match.group(1)))


def route_file_title(filename):
    titles = {
        "admin.php": "后台管理路由",
        "merchant.php": "商户端路由",
        "mobile.php": "手机端接口",
        "route.php": "公共路由",
    }
    return titles.get(filename, filename)


def module_from_path(path):
    first = normalize_slashes(path).split("/", 1)[0]
    return first or "未分组"


def clean_comment(line):
    text = line.strip()
    if text.startswith("//"):
        return text[2:].strip(" =")
    return ""


def basename_from_controller(controller):
    controller_name = controller.split("/", 1)[0].replace(".", "/")
    basename = controller_name.rsplit("/", 1)[-1]
    words = re.sub(r"(?<!^)([A-Z])", r" \1", basename).strip()
    return words or basename or "接口"


def clean_resource_label(label, controller):
    if not label or "." in label or "/" in label:
        return controller_doc_label(controller) or basename_from_controller(controller)
    cleaned = re.sub(r"\s+", "", label.strip())
    cleaned = re.sub(r"（.*?）|\(.*?\)", "", cleaned)
    cleaned = re.sub(r"^(获取|查询)", "", cleaned)
    cleaned = re.sub(r"(列表|详情|数据)$", "", cleaned)
    cleaned = re.sub(r"(管理路由|路由|管理|控制器)$", "", cleaned)
    return cleaned or controller_doc_label(controller) or basename_from_controller(controller)


def resource_summary(label, action, controller):
    entity = clean_resource_label(label, controller)
    action_titles = {
        "index": f"获取{entity}列表",
        "create": f"获取{entity}创建表单",
        "save": f"保存{entity}",
        "read": f"获取{entity}详情",
        "edit": f"获取{entity}编辑表单",
        "update": f"更新{entity}",
        "delete": f"删除{entity}",
    }
    return action_titles.get(action, f"{entity} {action}")


def make_operation_id(method, path, controller):
    raw = f"{method}_{path}_{controller}".lower()
    raw = re.sub(r"[^a-z0-9]+", "_", raw).strip("_")
    return raw[:180] or f"{method}_route"


def build_common_response():
    return {
        "description": "成功",
        "content": {
            "application/json": {
                "schema": {
                    "type": "object",
                    "properties": {
                        "status": {"type": "integer", "description": "状态码"},
                        "msg": {"type": "string", "description": "提示信息"},
                        "data": {"description": "业务数据"},
                    },
                }
            }
        },
    }


def infer_schema(field_name, default_value="", explicit_type=""):
    type_text = (explicit_type or "").lower()
    name = field_name.lower()
    if explicit_type in {"int", "integer"} or type_text == "int":
        return {"type": "integer"}
    if explicit_type in {"array", "list"} or type_text == "array":
        return {"type": "array", "items": {"type": "string"}}
    if explicit_type in {"bool", "boolean"} or type_text == "bool":
        return {"type": "boolean"}
    if explicit_type in {"float", "double", "number"} or type_text in {"float", "double"}:
        return {"type": "number"}
    if isinstance(default_value, bool):
        return {"type": "boolean"}
    if isinstance(default_value, int):
        return {"type": "integer"}
    if isinstance(default_value, float):
        return {"type": "number"}
    if isinstance(default_value, list):
        return {"type": "array", "items": {"type": "string"}}
    if name.endswith("_id") or name in {"id", "page", "limit", "page_size", "status", "sort"}:
        return {"type": "integer"}
    if name.endswith("_ids") or name in {"ids", "cart_ids", "category_ids"}:
        return {"type": "array", "items": {"type": "integer"}}
    return {"type": "string"}


def parse_default(default_text):
    text = default_text.strip()
    if text in {"[]", "array()"}:
        return []
    if text in {"true", "false"}:
        return text == "true"
    if re.fullmatch(r"-?\d+", text):
        return int(text)
    if re.fullmatch(r"-?\d+\.\d+", text):
        return float(text)
    quoted = re.fullmatch(r"['\"](.*)['\"]", text)
    if quoted:
        return quoted.group(1)
    return text


def resolve_use_alias(source, class_name):
    for match in re.finditer(r"use\s+([^;]+);", source):
        full_name = match.group(1).strip()
        alias_match = re.search(r"\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$", full_name, re.I)
        if alias_match and alias_match.group(1) == class_name:
            return full_name.split()[0]
        if full_name.rsplit("\\", 1)[-1] == class_name:
            return full_name
    return ""


def resolve_php_const(source, expression):
    expr = expression.strip()
    quoted = re.fullmatch(r"['\"]([^'\"]+)['\"]", expr)
    if quoted:
        return quoted.group(1)

    const_match = re.fullmatch(r"([A-Za-z_][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)", expr)
    if not const_match:
        return ""

    class_name, const_name = const_match.groups()
    candidates = []
    if class_name == "self":
        candidates.append(source)
    else:
        full_name = resolve_use_alias(source, class_name)
        if full_name:
            const_path = os.path.join(*full_name.split("\\")) + ".php"
            candidates.append(const_path)

    for candidate in candidates:
        if candidate == source:
            const_source = source
        elif os.path.exists(candidate):
            with open(candidate, encoding="utf-8") as file:
                const_source = file.read()
        else:
            continue
        const_pattern = rf"const\s+{re.escape(const_name)}\s*=\s*['\"]([^'\"]+)['\"]"
        match = re.search(const_pattern, const_source)
        if match:
            return match.group(1)
    return ""


def request_key_parts(raw_key):
    field = raw_key
    suffix = ""
    if "/" in field:
        field, suffix = field.split("/", 1)
    schema_type = {
        "d": "integer",
        "a": "array",
        "s": "string",
        "b": "boolean",
        "f": "number",
    }.get(suffix)
    return field, schema_type


def add_param(params, field_name, location, default_value="", explicit_type="", description=""):
    if not field_name or field_name.startswith("_"):
        return
    field_name, type_from_key = request_key_parts(field_name)
    schema = infer_schema(field_name, default_value, type_from_key or explicit_type)
    if default_value not in ("", None) and default_value != []:
        schema["default"] = default_value
    params[field_name] = {
        "name": field_name,
        "in": location,
        "required": False,
        "schema": schema,
        "description": description or field_name,
    }


def find_matching_brace(text, open_index):
    depth = 0
    in_string = None
    escaped = False
    for index in range(open_index, len(text)):
        char = text[index]
        if in_string:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == in_string:
                in_string = None
            continue
        if char in {"'", '"'}:
            in_string = char
            continue
        if char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return index
    return -1


def find_matching_paren(text, open_index):
    pairs = {"(": ")", "[": "]"}
    open_char = text[open_index]
    close_char = pairs[open_char]
    depth = 0
    in_string = None
    escaped = False
    for index in range(open_index, len(text)):
        char = text[index]
        if in_string:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == in_string:
                in_string = None
            continue
        if char in {"'", '"'}:
            in_string = char
            continue
        if char == open_char:
            depth += 1
        elif char == close_char:
            depth -= 1
            if depth == 0:
                return index
    return -1


def extract_method_body(source, method):
    pattern = re.compile(rf"(?:public|protected|private)\s+function\s+{re.escape(method)}\s*\([^)]*\)[^{{]*{{")
    match = pattern.search(source)
    if not match:
        return "", ""
    open_index = source.find("{", match.start())
    close_index = find_matching_brace(source, open_index)
    if close_index < 0:
        return "", ""
    before = source[:match.start()]
    docblock = ""
    doc_matches = list(re.finditer(r"/\*\*.*?\*/", before, flags=re.S))
    if doc_matches and before[doc_matches[-1].end():].strip() == "":
        docblock = doc_matches[-1].group(0)
    return source[open_index + 1:close_index], docblock


def strip_outer_array(array_text):
    text = array_text.strip()
    if not text.startswith("["):
        return text
    close_index = find_matching_paren(text, 0)
    if close_index == len(text) - 1:
        return text[1:-1]
    return text


def split_top_level_items(array_text):
    items = []
    start = 0
    square_depth = 0
    paren_depth = 0
    in_string = None
    escaped = False
    for index, char in enumerate(array_text):
        if in_string:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == in_string:
                in_string = None
            continue
        if char in {"'", '"'}:
            in_string = char
            continue
        if char == "[":
            square_depth += 1
        elif char == "]":
            square_depth -= 1
        elif char == "(":
            paren_depth += 1
        elif char == ")":
            paren_depth -= 1
        elif char == "," and square_depth == 0 and paren_depth == 0:
            item = array_text[start:index].strip()
            if item:
                items.append(item)
            start = index + 1
    tail = array_text[start:].strip()
    if tail:
        items.append(tail)
    return items


def parse_field_array(array_text, source=""):
    fields = []
    for item in split_top_level_items(strip_outer_array(array_text)):
        field_expr = item
        default_expr = ""
        if item.strip().startswith("["):
            parts = split_top_level_items(strip_outer_array(item))
            if not parts:
                continue
            field_expr = parts[0]
            default_expr = parts[1] if len(parts) > 1 else ""
        field = resolve_php_const(source, field_expr) if source else ""
        if not field:
            quoted = re.fullmatch(r"\s*['\"]([^'\"]+)['\"]\s*", field_expr)
            field = quoted.group(1) if quoted else ""
        if not field:
            continue
        default = parse_default(default_expr.rstrip(","))
        fields.append((field, default))
    used = {field for field, _ in fields}
    stripped_items = re.sub(r"\[[^\]]*\]", "", array_text)
    for field in re.findall(r"['\"]([A-Za-z_][A-Za-z0-9_/]*)['\"]", stripped_items):
        if field not in used:
            fields.append((field, ""))
            used.add(field)
    return fields


def extract_return_array(source, method):
    body, _ = extract_method_body(source, method)
    match = re.search(r"return\s*\[", body)
    if not match:
        return ""
    open_index = body.find("[", match.start())
    close_index = find_matching_paren(body, open_index)
    if close_index < 0:
        return ""
    return body[open_index:close_index + 1]


def extract_helper_fields(source, helper):
    return parse_field_array(extract_return_array(source, helper), source)


def merge_params(target, params):
    for param in params:
        target[param["name"]] = param


def parse_docblock_params(docblock):
    descriptions = {}
    if not docblock:
        return descriptions
    for line in docblock.splitlines():
        cleaned = line.strip().lstrip("*").strip()
        match = re.match(r"-\s*([A-Za-z_][A-Za-z0-9_]*)\s+([A-Za-z_|]+)\s*(.*)", cleaned)
        if match:
            descriptions[match.group(1)] = {
                "type": match.group(2).split("|", 1)[0],
                "description": match.group(3).strip(" 。"),
            }
    return descriptions


def controller_to_path(controller):
    controller_name = controller.split("/", 1)[0].replace(".", "/")
    parts = [part for part in controller_name.split("/") if part]
    if not parts:
        return ""
    relative = os.path.join("app", "controller", *parts) + ".php"
    if os.path.exists(relative):
        return relative
    current = os.getcwd()
    for part in relative.split(os.sep):
        if not os.path.isdir(current):
            return relative
        candidates = {name.lower(): name for name in os.listdir(current)}
        actual = candidates.get(part.lower())
        if actual is None:
            return relative
        current = os.path.join(current, actual)
    return current


def controller_doc_label(controller):
    path = controller_to_path(controller)
    if not path or not os.path.exists(path):
        return ""
    with open(path, encoding="utf-8") as file:
        source = file.read()
    class_match = re.search(r"/\*\*(.*?)\*/\s*class\s+", source, re.S)
    if not class_match:
        return ""
    for line in class_match.group(1).splitlines():
        cleaned = line.strip().lstrip("*").strip()
        if not cleaned or cleaned.startswith("@"):
            continue
        cleaned = re.sub(r"（.*?）|\(.*?\)", "", cleaned)
        cleaned = re.sub(r"^(平台端|后台|管理端|移动端)", "", cleaned)
        cleaned = cleaned.strip(" .。")
        cleaned = re.sub(r"(管理控制器|控制器|管理)$", "", cleaned)
        return cleaned.strip(" .。") or ""
    return ""


def first_docblock_line(docblock):
    if not docblock:
        return ""
    for line in docblock.splitlines():
        cleaned = line.strip().lstrip("*").strip()
        if cleaned in {"/", "/**", "*/"} or cleaned.startswith("/"):
            continue
        if cleaned and not cleaned.startswith("@"):
            return cleaned.strip(" .。")
    return ""


def controller_method_summary(controller):
    path = controller_to_path(controller)
    action = controller_action(controller)
    if not path or not os.path.exists(path):
        return ""
    with open(path, encoding="utf-8") as file:
        source = file.read()
    _, docblock = extract_method_body(source, action)
    summary = first_docblock_line(docblock)
    return re.sub(r"（.*?）|\(.*?\)", "", summary).strip(" .。")


def action_title_from_route(route):
    controller = route["controller"]
    action = controller_action(controller)
    entity = controller_doc_label(controller) or clean_resource_label("", controller)
    raw_path = route.get("rawPath") or route["path"]
    if action == "show" and ("status" in raw_path or "show" in raw_path):
        return f"修改{entity}状态"
    action_titles = {
        "index": f"获取{entity}列表",
        "list": f"获取{entity}列表",
        "tree": f"获取{entity}树",
        "options": f"获取{entity}选项",
        "queryConfig": f"获取{entity}查询配置",
        "saveQueryConfig": f"保存{entity}查询配置",
        "save": f"保存{entity}",
        "read": f"获取{entity}详情",
        "detail": f"获取{entity}详情",
        "update": f"更新{entity}",
        "edit": f"更新{entity}",
        "delete": f"删除{entity}",
        "status": f"修改{entity}状态",
        "audit": f"审核{entity}",
    }
    if action in action_titles:
        return action_titles[action]
    words = re.sub(r"(?<!^)([A-Z])", r" \1", action).strip()
    return f"{entity} {words}" if entity else words


def is_weak_summary(summary):
    text = summary or ""
    return (
        text in {"获取", "查询", "保存", "修改", "更新", "删除", "列表", "详情"}
        or "路由" in text
        or "静态路径" in text
    )


def controller_action(controller):
    return controller.split("/", 1)[1] if "/" in controller else "index"


def extract_invocation_argument(text, marker):
    start = text.find(marker)
    if start < 0:
        return ""
    open_index = text.find("(", start)
    if open_index < 0:
        return ""
    close_index = find_matching_paren(text, open_index)
    if close_index < 0:
        return ""
    return text[open_index + 1:close_index]


def extract_controller_params(controller, http_method):
    path = controller_to_path(controller)
    action = controller_action(controller)
    if not path or not os.path.exists(path):
        return [], [], ""
    with open(path, encoding="utf-8") as file:
        source = file.read()
    body, docblock = extract_method_body(source, action)
    if not body and action == "update":
        body, docblock = extract_method_body(source, "save")
    if not body:
        return extract_trait_params(source, action, http_method) + (path,)

    helper_skip = {
        action,
        "getIndexFields",
        "getRequestFields",
        "getFormFields",
        "getStatusField",
        "traitIndex",
        "traitCreate",
        "traitSave",
        "traitRead",
        "traitUpdate",
        "traitDelete",
        "traitShow",
    }
    helper_bodies = []
    for helper in re.findall(r"\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(", body):
        if helper in helper_skip:
            continue
        helper_body, _ = extract_method_body(source, helper)
        if helper_body and "$this->request->" in helper_body:
            helper_bodies.append(helper_body)
    if helper_bodies:
        body = body + "\n" + "\n".join(helper_bodies)

    doc_params = parse_docblock_params(docblock)
    query_params = {}
    body_params = {}

    for method in REQUEST_MORE_METHODS:
        for match in re.finditer(rf"\$this->request->{method}\s*\(", body):
            args = extract_invocation_argument(body, match.group(0))
            fields = []
            if args.strip().startswith("["):
                close_index = find_matching_paren(args, 0)
                fields = parse_field_array(args[:close_index + 1] if close_index >= 0 else args, source)
            else:
                helper = re.search(r"\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(\s*\)", args)
                if helper:
                    fields = extract_helper_fields(source, helper.group(1))
            location = "query" if method == "getMore" else "body"
            target = query_params if location == "query" else body_params
            for field, default in fields:
                doc = doc_params.get(request_key_parts(field)[0], {})
                add_param(target, field, location, default, doc.get("type", ""), doc.get("description", ""))

    for method in REQUEST_METHODS:
        pattern = re.compile(rf"\$this->request->{method}\s*\(\s*['\"]([^'\"]+)['\"]\s*(?:,\s*([^)]*?))?\)")
        for match in pattern.finditer(body):
            field = match.group(1)
            default = parse_default((match.group(2) or "").rstrip(","))
            location = "query" if method == "get" else "body"
            if method == "param":
                location = "query" if http_method == "get" else "body"
            target = query_params if location == "query" else body_params
            doc = doc_params.get(request_key_parts(field)[0], {})
            add_param(target, field, location, default, doc.get("type", ""), doc.get("description", ""))

    for match in re.finditer(r"\$this->request->file\s*\(\s*['\"]([^'\"]+)['\"]\s*\)", body):
        field = match.group(1)
        body_params[field] = {
            "name": field,
            "in": "body",
            "required": False,
            "schema": {"type": "string", "format": "binary"},
            "description": field,
        }
    if (
        re.search(r"(?:->move|\$this->request->file)\s*\(\s*\$file\s*\)", body)
        and "fileName" in body_params
        and "file" not in body_params
    ):
        body_params["file"] = {
            "name": "file",
            "in": "body",
            "required": False,
            "schema": {"type": "string", "format": "binary"},
            "description": "上传文件，默认表单字段名为 file；可通过 fileName 指定其他字段名",
        }

    trait_forwarding = {
        "traitIndex": ("index", "get"),
        "traitCreate": ("create", "get"),
        "traitSave": ("save", "post"),
        "traitRead": ("read", "get"),
        "traitUpdate": ("update", "put"),
        "traitDelete": ("delete", "delete"),
        "traitShow": ("show", "get"),
    }
    for trait_method, (trait_action, trait_http_method) in trait_forwarding.items():
        if re.search(rf"\$this->{trait_method}\s*\(", body):
            trait_query, trait_body = extract_trait_params(source, trait_action, trait_http_method)
            merge_params(query_params, trait_query)
            merge_params(body_params, trait_body)

    for match in re.finditer(r"\$this->request->only\s*\(\s*\[([^\]]*)\]\s*\)", body):
        location = "query" if http_method == "get" else "body"
        target = query_params if location == "query" else body_params
        for field in re.findall(r"['\"]([^'\"]+)['\"]", match.group(1)):
            doc = doc_params.get(field, {})
            add_param(target, field, location, "", doc.get("type", ""), doc.get("description", ""))

    if re.search(r"\$this->request->param\s*\(\s*\)", body):
        allowed_match = re.search(r"\$allowed\s*=\s*\[", body)
        if allowed_match:
            open_index = body.find("[", allowed_match.start())
            close_index = find_matching_paren(body, open_index)
            if close_index >= 0:
                location = "query" if http_method == "get" else "body"
                target = query_params if location == "query" else body_params
                for field, default in parse_field_array(body[open_index:close_index + 1], source):
                    doc = doc_params.get(request_key_parts(field)[0], {})
                    add_param(target, field, location, default, doc.get("type", ""), doc.get("description", ""))

    for match in re.finditer(r"\$this->getBatchIds\s*\(\s*['\"]([^'\"]+)['\"]\s*\)", body):
        add_param(body_params, match.group(1), "body", [], "array")
        add_param(body_params, "ids", "body", [], "array")

    return list(query_params.values()), list(body_params.values()), path


def extract_trait_params(source, action, http_method):
    query_params = {}
    body_params = {}
    if "ResourceControllerTrait" not in source:
        return [], []

    helper_by_action = {
        "index": ("getIndexFields", "query"),
        "create": ("getFormFields", "query"),
        "save": ("getRequestFields", "body"),
        "store": ("getRequestFields", "body"),
        "update": ("getRequestFields", "body"),
    }
    helper_info = helper_by_action.get(action)
    if helper_info:
        helper, location = helper_info
        target = query_params if location == "query" else body_params
        for field, default in extract_helper_fields(source, helper):
            add_param(target, field, location, default)

    if action == "show":
        status_field = "status"
        status_match = re.search(r"protected\s+function\s+getStatusField\s*\([^)]*\)[^{]*{.*?return\s+['\"]([^'\"]+)['\"]", source, re.S)
        if status_match:
            status_field = status_match.group(1)
        add_param(query_params, status_field, "query", "")
    elif action in {"read", "delete", "destroy", "edit"}:
        pass
    elif not helper_info and http_method in {"post", "put", "patch", "delete"}:
        for field, default in extract_helper_fields(source, "getRequestFields"):
            add_param(body_params, field, "body", default)

    return list(query_params.values()), list(body_params.values())


def append_route(routes, route, active_groups):
    route["middlewares"] = []
    route["optionalAuth"] = False
    route["authRequired"] = False
    route["_groupIndexes"] = [group["index"] for group in active_groups]
    for group in active_groups:
        route["middlewares"].extend(group["middlewares"])
    apply_auth(route)
    routes.append(route)
    for group in active_groups:
        group["routeIndexes"].append(len(routes) - 1)


def apply_auth(route):
    joined = " ".join(route.get("middlewares", []))
    route["authRequired"] = any(keyword in joined for keyword in AUTH_KEYWORDS)
    route["optionalAuth"] = any(keyword in joined for keyword in OPTIONAL_AUTH_KEYWORDS)


def parse_route_file(path, filename):
    routes = []
    groups = []
    group_counter = 0
    pending_comments = []

    with open(path, encoding="utf-8") as file:
        lines = file.readlines()

    for line_no, line in enumerate(lines, start=1):
        stripped = line.strip()
        if stripped.startswith("//"):
            comment = clean_comment(stripped)
            if comment:
                pending_comments.append(comment)
            continue
        if not stripped:
            continue

        close_match = re.match(r"^\}\)\s*(.*)", stripped)
        if close_match and groups:
            closed = groups.pop()
            chain = close_match.group(1)
            middlewares = re.findall(r"->middleware\(([^)]*)\)", chain)
            if middlewares:
                closed["middlewares"].extend(middlewares)
                for route_index in closed["routeIndexes"]:
                    routes[route_index]["middlewares"].extend(middlewares)
                    apply_auth(routes[route_index])
            pending_comments = []
            continue

        group_match = re.search(r"Route::group\s*\((.*)", stripped)
        if group_match and "Route::group(" in stripped:
            args = quoted_args(stripped)
            prefix = args[0] if args and "function" not in stripped.split(args[0], 1)[0] else ""
            parent_prefix = groups[-1]["fullPrefix"] if groups else ""
            full_prefix = join_path(parent_prefix, prefix)
            root_prefixes = {"api/admin", "api/merchant", "api/mobile"}
            module = None
            if prefix and full_prefix not in root_prefixes:
                module = prefix.strip("/").replace("/", "_")
            group_counter += 1
            groups.append({
                "index": group_counter,
                "prefix": prefix,
                "fullPrefix": full_prefix,
                "module": module,
                "middlewares": re.findall(r"->middleware\(([^)]*)\)", stripped),
                "routeIndexes": [],
            })
            pending_comments = []
            continue

        method_match = re.search(
            r"Route::(get|post|put|delete|patch|any)\s*\(\s*['\"]([^'\"]*)['\"]\s*,\s*['\"]([^'\"]+)['\"]",
            stripped,
        )
        if method_match:
            route_type, route_path, controller = method_match.groups()
            parent_prefix = groups[-1]["fullPrefix"] if groups else ""
            full_path = openapi_path(join_path(parent_prefix, route_path))
            module = next((group["module"] for group in reversed(groups) if group.get("module")), None)
            if not module:
                module = module_from_path(route_path)
            summary = pending_comments[-1] if pending_comments else controller
            for method in HTTP_METHODS[route_type]:
                append_route(routes, {
                    "file": filename,
                    "line": line_no,
                    "method": method,
                    "path": full_path,
                    "rawPath": route_path,
                    "controller": controller,
                    "module": module,
                    "summary": summary,
                    "patternLine": stripped,
                }, groups)
            pending_comments = []
            continue

        resource_match = re.search(
            r"Route::resource\s*\(\s*['\"]([^'\"]*)['\"]\s*,\s*['\"]([^'\"]+)['\"]",
            stripped,
        )
        if resource_match:
            resource_path, controller = resource_match.groups()
            parent_prefix = groups[-1]["fullPrefix"] if groups else ""
            module = next((group["module"] for group in reversed(groups) if group.get("module")), None)
            if not module:
                module = module_from_path(resource_path)
            except_actions = parse_array_arg(stripped, "except") or set()
            only_actions = parse_array_arg(stripped, "only")
            summary_prefix = pending_comments[-1] if pending_comments else controller
            for action, method, suffix in RESOURCE_ACTIONS:
                if only_actions is not None and action not in only_actions:
                    continue
                if action in except_actions:
                    continue
                action_path = join_path(resource_path, suffix)
                full_path = openapi_path(join_path(parent_prefix, action_path))
                append_route(routes, {
                    "file": filename,
                    "line": line_no,
                    "method": method,
                    "path": full_path,
                    "rawPath": action_path,
                    "controller": f"{controller}/{action}",
                    "module": module,
                    "summary": resource_summary(summary_prefix, action, controller),
                    "patternLine": stripped,
                }, groups)
            pending_comments = []
            continue

        pending_comments = []

    return routes


def route_to_operation(route):
    folder = f"{route['file']}/{route['module'] or '未分组'}"
    query_params, body_params, controller_path = extract_controller_params(route["controller"], route["method"])
    summary = route["summary"]
    if summary == route["controller"] or is_weak_summary(summary):
        method_summary = controller_method_summary(route["controller"])
        summary = method_summary if method_summary and not is_weak_summary(method_summary) else action_title_from_route(route)
    description = [
        f"控制器：{route['controller']}",
        f"来源：route/{route['file']}:{route['line']}",
    ]
    if controller_path:
        description.append(f"控制器文件：{controller_path}")
    if route.get("optionalAuth"):
        description.append("权限：可选登录")
    elif route.get("authRequired"):
        description.append("权限：必须登录")
    operation = {
        "tags": [folder],
        "summary": summary,
        "description": "\n".join(description),
        "operationId": make_operation_id(route["method"], route["path"], route["controller"]),
        "x-apifox-folder": folder,
        "parameters": extract_path_params(route["path"], route.get("patternLine", "")) + query_params,
        "responses": {"200": build_common_response()},
    }
    if route.get("authRequired"):
        operation["security"] = [{"bearerAuth": []}]
    elif route.get("optionalAuth"):
        operation["security"] = []
        operation["x-auth-mode"] = "optional"
    if body_params:
        properties = {param["name"]: param["schema"] for param in body_params}
        operation["requestBody"] = {
            "required": False,
            "content": {
                "application/json": {
                    "schema": {
                        "type": "object",
                        "properties": properties,
                        "x-apifox-orders": list(properties.keys()),
                    }
                }
            },
        }
    elif route["method"] in {"post", "put", "patch", "delete"}:
        operation["requestBody"] = {
            "required": False,
            "content": {
                "application/json": {
                    "schema": {
                        "type": "object",
                        "description": "未从 Controller 自动识别到请求字段，需结合 Validate/Service 进一步补充",
                    }
                }
            },
        }
    return operation


def build_openapi(routes, server_url, route_files):
    spec = {
        "openapi": "3.0.3",
        "info": {
            "title": "Knowledge Payment API",
            "version": "1.0.0",
            "description": "由 route/*.php 显式路由导出的 OpenAPI 文档。",
        },
        "servers": [{"url": server_url, "description": "local"}],
        "paths": {},
        "components": {
            "securitySchemes": {
                "bearerAuth": {
                    "type": "http",
                    "scheme": "bearer",
                    "bearerFormat": "JWT",
                }
            },
            "schemas": {},
        },
        "x-route-files": [route_file_title(name) for name in route_files],
    }
    duplicate_count = 0
    for route in routes:
        path_item = spec["paths"].setdefault(route["path"], {})
        if route["method"] in path_item:
            duplicate_count += 1
            path_item[route["method"]].setdefault("x-duplicate-route-definitions", []).append({
                "source": f"route/{route['file']}:{route['line']}",
                "controller": route["controller"],
                "module": route["module"],
            })
            continue
        path_item[route["method"]] = route_to_operation(route)
    spec["x-duplicate-route-count"] = duplicate_count
    return spec


def print_preview(routes, route_files):
    by_folder = Counter(f"{route['file']}/{route['module'] or '未分组'}" for route in routes)
    by_file = defaultdict(int)
    for route in routes:
        by_file[route["file"]] += 1

    print("路由文件统计:")
    for filename in route_files:
        print(f"  - {filename}: {by_file.get(filename, 0)} 个接口方法")
    print()
    print("Apifox 目录预览:")
    for folder, count in sorted(by_folder.items()):
        print(f"  - {folder}: {count}")


def main():
    parser = argparse.ArgumentParser(description="导出 ThinkPHP8 route/*.php 为 OpenAPI JSON")
    parser.add_argument("--route-dir", default="route", help="路由目录")
    parser.add_argument("--files", nargs="+", default=ROUTE_FILES, help="要扫描的路由文件名，如 admin.php")
    parser.add_argument("--output", default="runtime/openapi/thinkphp8-routes.openapi.json", help="OpenAPI 输出路径")
    parser.add_argument("--server-url", default="http://127.0.0.1:25780", help="OpenAPI servers[0].url")
    parser.add_argument("--dry-run", action="store_true", help="只打印目录预览，不写入文件")
    args = parser.parse_args()

    all_routes = []
    route_files = args.files
    for filename in route_files:
        path = os.path.join(args.route_dir, filename)
        if not os.path.exists(path):
            print(f"跳过不存在的路由文件: {path}")
            continue
        all_routes.extend(parse_route_file(path, filename))

    print_preview(all_routes, route_files)

    if args.dry_run:
        print()
        print("dry-run 模式，不写入 OpenAPI 文件")
        return

    spec = build_openapi(all_routes, args.server_url, route_files)
    output_dir = os.path.dirname(args.output)
    if output_dir:
        os.makedirs(output_dir, exist_ok=True)
    with open(args.output, "w", encoding="utf-8") as file:
        json.dump(spec, file, ensure_ascii=False, indent=2)
        file.write("\n")
    operation_count = sum(len(item) for item in spec["paths"].values())
    print()
    print(f"已写入: {args.output}")
    print(f"paths: {len(spec['paths'])}, operations: {operation_count}, parsed routes: {len(all_routes)}")
    if spec["x-duplicate-route-count"]:
        print(f"重复 method + path 定义: {spec['x-duplicate-route-count']} 个，已记录到 x-duplicate-route-definitions")


if __name__ == "__main__":
    main()
