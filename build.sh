#!/bin/bash
# =========================================================
# 前端自动打包脚本
# 覆盖范围：总后台 admin / 商户端 merchant / UniApp H5
# UniApp H5 优先使用项目 package.json 中的 build:h5 脚本
# =========================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE_DIR="$SCRIPT_DIR/template"
PUBLIC_DIR="$SCRIPT_DIR/public"

ADMIN_DIR="$TEMPLATE_DIR/admin"
MERCHANT_DIR="$TEMPLATE_DIR/merchant"
UNIAPP_DIR="$TEMPLATE_DIR/uniapp"

ADMIN_PUBLIC_DIR="${ADMIN_PUBLIC_DIR:-$PUBLIC_DIR/admin}"
MERCHANT_PUBLIC_DIR="${MERCHANT_PUBLIC_DIR:-$PUBLIC_DIR/merchant}"
H5_PUBLIC_DIR="${H5_PUBLIC_DIR:-$PUBLIC_DIR}"
NPM_INSTALL_FLAGS="${NPM_INSTALL_FLAGS:---legacy-peer-deps}"
SKIP_NPM_INSTALL="${SKIP_NPM_INSTALL:-0}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${CYAN}[INFO]${NC}  $*"; }
log_success() { echo -e "${GREEN}[OK]${NC}    $*"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC}  $*"; }
log_error() { echo -e "${RED}[ERROR]${NC} $*"; }

START_TIME="$(date +%s)"

usage() {
    cat <<USAGE
用法：
  bash build.sh                  打包全部：admin merchant h5
  bash build.sh admin            只打包总后台
  bash build.sh merchant         只打包商户端
  bash build.sh mer              merchant 的别名
  bash build.sh h5               只打包 UniApp H5
  bash build.sh admin h5         指定多个目标

环境变量：
  ADMIN_PUBLIC_DIR=/path         指定总后台同步目录，默认 public/admin
  MERCHANT_PUBLIC_DIR=/path      指定商户端同步目录，默认 public/merchant
  H5_PUBLIC_DIR=/path            指定 H5 同步目录，默认 public
  SKIP_NPM_INSTALL=1             node_modules 不存在时不自动 npm install
  NPM_INSTALL_FLAGS="..."        npm install 附加参数，默认 --legacy-peer-deps
USAGE
}

ensure_dir() {
    mkdir -p "$1"
}

remove_path() {
    local path="$1"

    if [ -e "$path" ]; then
        rm -rf "$path"
    fi
}

copy_dir_contents() {
    local name="$1"
    local source_dir="$2"
    local target_dir="$3"

    if [ ! -d "$source_dir" ]; then
        log_error "[$name] 未找到构建目录：$source_dir"
        exit 1
    fi

    remove_path "$target_dir"
    ensure_dir "$target_dir"
    cp -R "$source_dir"/. "$target_dir"/
}

copy_entry() {
    local name="$1"
    local source_path="$2"
    local target_path="$3"

    if [ ! -e "$source_path" ]; then
        log_error "[$name] 未找到构建产物：$source_path"
        exit 1
    fi

    ensure_dir "$(dirname "$target_path")"
    remove_path "$target_path"
    cp -R "$source_path" "$target_path"
}

ensure_node_modules() {
    local name="$1"
    local dir="$2"

    if [ -d "$dir/node_modules" ]; then
        return
    fi

    if [ "$SKIP_NPM_INSTALL" = "1" ]; then
        log_error "[$name] node_modules 不存在，且 SKIP_NPM_INSTALL=1"
        exit 1
    fi

    log_warn "[$name] node_modules 不存在，开始执行 npm install $NPM_INSTALL_FLAGS ..."
    cd "$dir"
    npm install $NPM_INSTALL_FLAGS
    cd "$SCRIPT_DIR"
}

build_npm() {
    local name="$1"
    local dir="$2"
    local command="$3"

    if [ ! -d "$dir" ]; then
        log_error "[$name] 项目目录不存在：$dir"
        exit 1
    fi

    ensure_node_modules "$name" "$dir"

    log_info "开始打包 [$name] ..."
    cd "$dir"
    npm run "$command"
    cd "$SCRIPT_DIR"
    log_success "[$name] 打包完成"
}

build_admin() {
    build_npm "admin" "$ADMIN_DIR" "build"
}

deploy_admin() {
    copy_dir_contents "admin" "$ADMIN_DIR/dist" "$ADMIN_PUBLIC_DIR"
    log_success "[admin] 已同步到 $ADMIN_PUBLIC_DIR"
}

build_merchant() {
    build_npm "merchant" "$MERCHANT_DIR" "merbuild"
}

deploy_merchant() {
    copy_dir_contents "merchant" "$MERCHANT_DIR/dist" "$MERCHANT_PUBLIC_DIR"
    log_success "[merchant] 已同步到 $MERCHANT_PUBLIC_DIR"
}

get_uniapp_h5_dist_dir() {
    local dist_h5_dir="$UNIAPP_DIR/dist/build/h5"
    local dist_web_dir="$UNIAPP_DIR/dist/build/web"

    if [ -d "$dist_h5_dir" ]; then
        echo "$dist_h5_dir"
        return
    fi

    if [ -d "$dist_web_dir" ]; then
        echo "$dist_web_dir"
        return
    fi

    echo "$dist_h5_dir"
}

build_h5() {
    local dist_h5_dir="$UNIAPP_DIR/dist/build/h5"
    local dist_web_dir="$UNIAPP_DIR/dist/build/web"

    if [ ! -d "$UNIAPP_DIR" ]; then
        log_error "[h5] UniApp 项目目录不存在：$UNIAPP_DIR"
        exit 1
    fi

    log_info "开始打包 [UniApp H5] ..."

    remove_path "$dist_h5_dir"
    remove_path "$dist_web_dir"
    build_npm "h5" "$UNIAPP_DIR" "build:h5"
}

deploy_h5() {
    local source_dir
    source_dir="$(get_uniapp_h5_dist_dir)"

    if [ ! -d "$source_dir" ]; then
        log_error "[h5] 未找到构建目录：$source_dir"
        exit 1
    fi

    ensure_dir "$H5_PUBLIC_DIR"
    for entry in "$source_dir"/*; do
        [ -e "$entry" ] || continue
        copy_entry "h5" "$entry" "$H5_PUBLIC_DIR/$(basename "$entry")"
    done

    log_success "[UniApp H5] 已同步到 $H5_PUBLIC_DIR"
}

run_target() {
    local target="$1"

    case "$target" in
        admin)
            build_admin
            deploy_admin
            ;;
        merchant|mer)
            build_merchant
            deploy_merchant
            ;;
        h5|uniapp|web)
            build_h5
            deploy_h5
            ;;
        all)
            run_target "admin"
            run_target "merchant"
            run_target "h5"
            ;;
        -h|--help|help)
            usage
            exit 0
            ;;
        *)
            log_error "未知目标：$target（可选：admin / merchant / mer / h5 / uniapp / web / all）"
            usage
            exit 1
            ;;
    esac
}

TARGETS=("$@")
if [ ${#TARGETS[@]} -eq 0 ]; then
    TARGETS=("admin" "merchant" "h5")
fi

if [ ${#TARGETS[@]} -eq 1 ]; then
    case "${TARGETS[0]}" in
        -h|--help|help)
            usage
            exit 0
            ;;
    esac
fi

echo ""
log_info "========================================"
log_info "  前端打包任务开始"
log_info "  目标：${TARGETS[*]}"
log_info "========================================"
echo ""

for target in "${TARGETS[@]}"; do
    run_target "$target"
done

END_TIME="$(date +%s)"
ELAPSED=$((END_TIME - START_TIME))
MINUTES=$((ELAPSED / 60))
SECONDS=$((ELAPSED % 60))

echo ""
log_info "========================================"
log_success "  所有任务完成！耗时 ${MINUTES}m${SECONDS}s"
log_info "========================================"
echo ""
