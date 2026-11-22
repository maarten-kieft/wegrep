<?php
declare(strict_types=1);

// The HTML shell shared by all mails, in the look of the app (dark navy card, blue buttons).
// Mail clients only trust tables and inline styles, so that is what this is. Variable: $c, the
// content array of a template (heading, paragraphs, code, button, footnotes) and $e() to escape.
/** @var array $c */
/** @var callable(string): string $e */

$font = "'Geist', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
$mono = "'Geist Mono', ui-monospace, 'SF Mono', Menlo, Consolas, monospace";
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="supported-color-schemes" content="dark">
<title><?= $e($c['subject']) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#070c15;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#070c15;"><?= $e($c['preheader'] ?? '') ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#070c15;">
<tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;">
<tr><td style="padding:0 4px 16px 4px;font-family:<?= $font ?>;font-size:20px;font-weight:700;color:#f5f5f5;letter-spacing:-0.01em;">
<span style="color:#0a6fdc;">&#9679;</span>&nbsp;Hockey Sheet
</td></tr>
<tr><td style="background-color:#111a28;border:1px solid #263246;border-radius:12px;padding:28px 24px;font-family:<?= $font ?>;color:#f5f5f5;">
<h1 style="margin:0 0 16px 0;font-size:22px;line-height:1.3;font-weight:700;color:#f5f5f5;"><?= $e($c['heading']) ?></h1>
<?php foreach ($c['paragraphs'] as $p) : ?>
<p style="margin:0 0 14px 0;font-size:16px;line-height:1.5;color:#f5f5f5;"><?= $e($p) ?></p>
<?php endforeach; ?>
<?php if (!empty($c['code'])) : ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px 0;">
<tr><td align="center" style="background-color:#1c2738;border:1px solid #263246;border-radius:8px;padding:16px;font-family:<?= $mono ?>;font-size:34px;font-weight:700;letter-spacing:0.35em;color:#f5f5f5;">
<?= $e($c['code']['value']) ?>
</td></tr>
</table>
<?php endif; ?>
<?php if (!empty($c['button'])) : ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 20px 0;">
<tr><td align="center" bgcolor="#0a6fdc" style="background-color:#0a6fdc;border-radius:8px;">
<a href="<?= $e($c['button']['url']) ?>" style="display:inline-block;padding:13px 26px;font-family:<?= $font ?>;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;"><?= $e($c['button']['label']) ?></a>
</td></tr>
</table>
<?php endif; ?>
<?php foreach ($c['footnotes'] as $note) : ?>
<p style="margin:0 0 8px 0;font-size:13px;line-height:1.5;color:#9a9a9a;"><?= $e($note) ?></p>
<?php endforeach; ?>
</td></tr>
<tr><td align="center" style="padding:16px 4px 0 4px;font-family:<?= $font ?>;font-size:12px;color:#6b7280;">Wegrep, het wedstrijdverslag voor ijshockey</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
