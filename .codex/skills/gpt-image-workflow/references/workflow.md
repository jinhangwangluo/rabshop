# GPT Image Workflow Reference

## Parameter Collection

Collect only the information needed for the requested mode:

- Text-to-image: prompt, output directory, optional size/quality/background/output format/count.
- Image-to-image: prompt, one or more input image paths, output directory, optional size/quality/background/output format/count.
- Edit: prompt, source image path, optional mask path, output directory, optional size/quality/background/output format/count.

Use defaults when the user does not care:

- `model`: `OPENAI_IMAGE_MODEL`, otherwise `gpt-image-2`.
- `out-dir`: `./generated/openai-images`.
- `size`: omit unless requested; let the model/API choose its default.
- `quality`: omit unless requested.
- `n`: `1`.

For `gpt-image-2`, do not request transparent background. Use an opaque or omitted background setting unless the user switches to a model that supports transparency.

## Confirmation Rules

Ask for explicit confirmation before the first API call in a task when any of these are true:

- The user provides a private, personal, client, production, screenshot, or unpublished input image.
- The prompt contains sensitive business, personal, medical, legal, financial, credential-like, or confidential content.
- The request will send a local file supplied by the user to OpenAI.

Confirmation wording should be short:

`我会把这些输入图片/提示词发送到 OpenAI Images API 处理，并把结果保存到 <path>。请确认是否继续？`

Do not ask again in the same task unless the user adds new sensitive inputs or changes the destination materially.

## Prompt Handling

Preserve the user's artistic intent. Improve prompts only when the user asks for help or the request is underspecified. When improving, keep the subject, style, constraints, and forbidden elements explicit.

For edits with a mask, state what should change and what must remain unchanged. Example:

`Replace only the masked background with a clean daylight studio. Keep the person, clothing, pose, face, hands, and product unchanged.`

For mask files, prefer PNG masks that match the source image dimensions. The masked area should represent the part to regenerate.

For image-to-image without a mask, describe the intended transformation and preservation constraints. Example:

`Use the input image as the composition and subject reference. Keep the product shape and logo placement, but render it as a premium ecommerce hero image.`

## Script Usage

Use the local script:

```bash
node .codex/skills/gpt-image-workflow/scripts/openai-image.mjs --mode text --prompt "..." --out-dir "./generated/openai-images"
```

Never place the API key in the command. The shell environment must contain:

```bash
export OPENAI_API_KEY="..."
export OPENAI_IMAGE_MODEL="gpt-image-2"
```

If the user's actual model ID differs, set `OPENAI_IMAGE_MODEL` accordingly or pass `--model`.

## Verification

After the script completes:

- Confirm each output file exists.
- If the Codex app can display images, include the generated image with an absolute Markdown image path.
- Review `metadata.json` only for non-secret request details and saved file paths.
- If the result needs another iteration, reuse the previous output path as the new `--image`.
