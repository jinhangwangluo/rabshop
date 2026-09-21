#!/usr/bin/env node

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { basename, extname, join, resolve } from 'node:path';

const API_URL = 'https://api.openai.com/v1/images';

function usage() {
  return `
Usage:
  node openai-image.mjs --mode text --prompt "..." --out-dir ./generated/openai-images
  node openai-image.mjs --mode image --image ./reference.png --prompt "..." --out-dir ./generated/openai-images
  node openai-image.mjs --mode edit --image ./source.png --mask ./mask.png --prompt "..." --out-dir ./generated/openai-images

Options:
  --mode text|image|edit
  --prompt <text>
  --image <path>               Repeatable for reference/source images
  --mask <path>                Optional edit mask
  --out-dir <path>             Default: ./generated/openai-images
  --model <model>              Default: OPENAI_IMAGE_MODEL or gpt-image-2
  --size <size>
  --quality <quality>
  --background <background>
  --output-format <png|jpeg|webp>
  --output-compression <0-100>
  --n <count>                  Default: 1
  --api-base <url>             Default: https://api.openai.com/v1
  --help
`;
}

function parseArgs(argv) {
  const args = {
    images: [],
    outDir: './generated/openai-images',
    n: '1',
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    const next = argv[index + 1];

    if (arg === '--help' || arg === '-h') {
      args.help = true;
      continue;
    }

    if (!arg.startsWith('--')) {
      throw new Error(`Unexpected argument: ${arg}`);
    }

    const key = arg.slice(2);
    if (next === undefined || next.startsWith('--')) {
      throw new Error(`Missing value for ${arg}`);
    }

    index += 1;

    if (key === 'image') {
      args.images.push(next);
      continue;
    }

    const normalizedKey = key.replace(/-([a-z])/g, (_, char) => char.toUpperCase());
    args[normalizedKey] = next;
  }

  return args;
}

function requireValue(value, message) {
  if (!value) {
    throw new Error(message);
  }
}

function mimeFromPath(filePath) {
  const extension = extname(filePath).toLowerCase();
  if (extension === '.png') {
    return 'image/png';
  }
  if (extension === '.jpg' || extension === '.jpeg') {
    return 'image/jpeg';
  }
  if (extension === '.webp') {
    return 'image/webp';
  }
  throw new Error(`Unsupported image extension for ${filePath}. Use PNG, JPEG, or WebP.`);
}

function extensionFromFormat(format, fallback = 'png') {
  if (!format) {
    return fallback;
  }
  if (format === 'jpeg') {
    return 'jpg';
  }
  return format;
}

function appendIfSet(target, key, value) {
  if (value !== undefined && value !== null && value !== '') {
    target[key] = value;
  }
}

function parseIntegerOption(value, optionName) {
  if (value === undefined || value === null || value === '') {
    return undefined;
  }

  const parsed = Number.parseInt(value, 10);
  if (!Number.isInteger(parsed)) {
    throw new Error(`${optionName} must be an integer.`);
  }

  return parsed;
}

async function appendImage(form, fieldName, filePath) {
  const absolutePath = resolve(filePath);
  const bytes = await readFile(absolutePath);
  const blob = new Blob([bytes], { type: mimeFromPath(absolutePath) });
  form.append(fieldName, blob, basename(absolutePath));
  return absolutePath;
}

function buildGenerationPayload(args, model) {
  const payload = {
    model,
    prompt: args.prompt,
  };

  appendIfSet(payload, 'size', args.size);
  appendIfSet(payload, 'quality', args.quality);
  appendIfSet(payload, 'background', args.background);
  appendIfSet(payload, 'output_format', args.outputFormat);
  appendIfSet(payload, 'output_compression', parseIntegerOption(args.outputCompression, '--output-compression'));
  appendIfSet(payload, 'n', parseIntegerOption(args.n, '--n'));

  return payload;
}

async function buildEditForm(args, model) {
  const form = new FormData();
  const inputImages = [];

  form.append('model', model);
  form.append('prompt', args.prompt);

  for (const imagePath of args.images) {
    inputImages.push(await appendImage(form, 'image[]', imagePath));
  }

  if (args.mask) {
    await appendImage(form, 'mask', args.mask);
  }

  const options = {
    size: args.size,
    quality: args.quality,
    background: args.background,
    output_format: args.outputFormat,
    output_compression: parseIntegerOption(args.outputCompression, '--output-compression'),
    n: parseIntegerOption(args.n, '--n'),
  };

  for (const [key, value] of Object.entries(options)) {
    if (value !== undefined && value !== null && value !== '') {
      form.append(key, String(value));
    }
  }

  return { form, inputImages };
}

async function callOpenAI({ apiKey, apiBase, endpoint, body, isMultipart }) {
  const url = `${apiBase.replace(/\/$/, '')}/images/${endpoint}`;
  const headers = {
    Authorization: `Bearer ${apiKey}`,
  };

  if (!isMultipart) {
    headers['Content-Type'] = 'application/json';
  }

  const response = await fetch(url, {
    method: 'POST',
    headers,
    body: isMultipart ? body : JSON.stringify(body),
  });

  const text = await response.text();
  let json;
  try {
    json = text ? JSON.parse(text) : {};
  } catch (error) {
    throw new Error(`Image API returned non-JSON response (${response.status}).`);
  }

  if (!response.ok) {
    throw new Error(`Image API request failed (${response.status}).`);
  }

  return json;
}

async function saveImageFromUrl(url, outputPath) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Failed to download generated image (${response.status}).`);
  }
  const bytes = Buffer.from(await response.arrayBuffer());
  await writeFile(outputPath, bytes);
}

async function saveOutputs({ result, outDir, outputFormat }) {
  await mkdir(outDir, { recursive: true });

  const saved = [];
  const data = Array.isArray(result.data) ? result.data : [];

  for (let index = 0; index < data.length; index += 1) {
    const item = data[index];
    const extension = extensionFromFormat(outputFormat, 'png');
    const outputPath = join(outDir, `image-${String(index + 1).padStart(2, '0')}.${extension}`);

    if (item.b64_json) {
      await writeFile(outputPath, Buffer.from(item.b64_json, 'base64'));
      saved.push(resolve(outputPath));
      continue;
    }

    if (item.url) {
      await saveImageFromUrl(item.url, outputPath);
      saved.push(resolve(outputPath));
      continue;
    }

    throw new Error('Image API returned an unsupported image payload.');
  }

  return saved;
}

function sanitizeRequest({ args, mode, model, endpoint, inputImages }) {
  return {
    mode,
    model,
    endpoint,
    prompt: args.prompt,
    images: inputImages || args.images.map((imagePath) => resolve(imagePath)),
    mask: args.mask ? resolve(args.mask) : null,
    outDir: resolve(args.outDir),
    size: args.size || null,
    quality: args.quality || null,
    background: args.background || null,
    outputFormat: args.outputFormat || null,
    outputCompression: parseIntegerOption(args.outputCompression, '--output-compression') || null,
    n: parseIntegerOption(args.n, '--n'),
  };
}

async function main() {
  const args = parseArgs(process.argv.slice(2));

  if (args.help) {
    process.stdout.write(usage());
    return;
  }

  const apiKey = process.env.OPENAI_API_KEY;
  const model = args.model || process.env.OPENAI_IMAGE_MODEL || 'gpt-image-2';
  const apiBase = args.apiBase || process.env.OPENAI_API_BASE || 'https://api.openai.com/v1';
  const mode = args.mode || 'text';

  requireValue(apiKey, 'OPENAI_API_KEY is required in the environment.');
  requireValue(args.prompt, '--prompt is required.');

  if (!['text', 'image', 'edit'].includes(mode)) {
    throw new Error('--mode must be one of: text, image, edit.');
  }

  if ((mode === 'image' || mode === 'edit') && args.images.length === 0) {
    throw new Error(`--image is required for --mode ${mode}.`);
  }

  if (model === 'gpt-image-2' && args.background === 'transparent') {
    throw new Error('gpt-image-2 does not support transparent background. Omit --background or use another supported value/model.');
  }

  let result;
  let endpoint;
  let inputImages = [];

  if (mode === 'text') {
    endpoint = 'generations';
    result = await callOpenAI({
      apiKey,
      apiBase,
      endpoint,
      body: buildGenerationPayload(args, model),
      isMultipart: false,
    });
  } else {
    endpoint = 'edits';
    const { form, inputImages: resolvedImages } = await buildEditForm(args, model);
    inputImages = resolvedImages;
    result = await callOpenAI({
      apiKey,
      apiBase,
      endpoint,
      body: form,
      isMultipart: true,
    });
  }

  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const outDir = resolve(args.outDir, `${mode}-${timestamp}`);
  const files = await saveOutputs({
    result,
    outDir,
    outputFormat: args.outputFormat,
  });

  const metadata = {
    ok: true,
    createdAt: new Date().toISOString(),
    request: sanitizeRequest({ args, mode, model, endpoint, inputImages }),
    files,
    usage: result.usage && typeof result.usage === 'object'
      ? Object.fromEntries(Object.entries(result.usage).filter(([key, value]) =>
        ['input_tokens', 'output_tokens', 'total_tokens'].includes(key) && Number.isFinite(value)))
      : null,
  };
  const metadataPath = join(outDir, 'metadata.json');
  await writeFile(metadataPath, JSON.stringify(metadata, null, 2));

  process.stdout.write(`${JSON.stringify({ ...metadata, metadataPath: resolve(metadataPath) }, null, 2)}\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
});
