---
name: gpt-image-workflow
description: Use OpenAI Images API workflows for text-to-image generation, image-to-image generation, and image editing with optional masks. Trigger when the user wants to generate pictures with a configured OpenAI image model such as gpt-image-2, transform one or more reference images, modify an existing image, create local image assets, or run an API-backed image workflow from conversation while keeping API keys out of files.
---

# GPT Image Workflow

## Overview

Use this skill to turn conversational image requests into repeatable OpenAI Images API runs. Collect missing inputs, confirm external API submission for user-provided images or sensitive prompts, then run `scripts/openai-image.mjs` and return saved output paths.

## Decision Tree

- Text-to-image: use `--mode text` with only a prompt.
- Image-to-image: use `--mode image` with one or more `--image` paths and a prompt that describes the target result.
- Image editing: use `--mode edit` with `--image`, prompt, and optional `--mask` for localized changes.

Read `references/workflow.md` before running image generation if the request includes input images, masks, sensitive prompt content, or ambiguous output requirements.

## Required Safety Rules

- Never write an API key into the skill, repository, command history examples, generated metadata, or output files.
- Read the API key from `OPENAI_API_KEY`. Read the model from `OPENAI_IMAGE_MODEL`, defaulting to `gpt-image-2` only when the user has not provided another model.
- Before sending user-provided images, masks, private screenshots, personal data, or sensitive prompts to OpenAI, clearly state that the content will be sent to OpenAI's API and obtain user confirmation.
- Store outputs under a user-approved directory. Default to `./generated/openai-images` inside the current working directory when no output path is provided.
- Do not run destructive file operations. Create new output directories and files only.

## Quick Start

Text-to-image:

```bash
OPENAI_API_KEY="..." OPENAI_IMAGE_MODEL="gpt-image-2" \
node .codex/skills/gpt-image-workflow/scripts/openai-image.mjs \
  --mode text \
  --prompt "A clean product hero image of a smart notebook on a white desk" \
  --out-dir "./generated/openai-images"
```

Image-to-image:

```bash
node .codex/skills/gpt-image-workflow/scripts/openai-image.mjs \
  --mode image \
  --image "./input/reference.png" \
  --prompt "Keep the main subject, convert the scene to a premium ecommerce banner" \
  --out-dir "./generated/openai-images"
```

Localized edit:

```bash
node .codex/skills/gpt-image-workflow/scripts/openai-image.mjs \
  --mode edit \
  --image "./input/source.png" \
  --mask "./input/mask.png" \
  --prompt "Replace only the masked background with a bright modern studio" \
  --out-dir "./generated/openai-images"
```

## Script Contract

Use `scripts/openai-image.mjs` for API calls. It uses Node's built-in `fetch` and does not require npm packages.

Common options:

- `--mode text|image|edit`
- `--prompt "..."`
- `--image <path>`; repeat for multiple references when supported by the API/model.
- `--mask <path>`; use with `--mode edit`.
- `--out-dir <path>`
- `--model <model>`; overrides `OPENAI_IMAGE_MODEL`.
- `--size <size>`, `--quality <quality>`, `--background <background>`, `--output-format <png|jpeg|webp>`, `--n <count>`

The script writes image files and `metadata.json`. Metadata excludes API keys and image bytes.

## Output Response

After a successful run, report:

- Mode, model, and output directory.
- Saved image path(s) as absolute paths.
- Whether a mask or input images were sent.
- Any warnings returned by the API or script.

If the API call fails, return the sanitized error and the exact non-secret command shape needed to retry.
