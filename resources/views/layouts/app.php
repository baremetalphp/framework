<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($component ?? 'App') ?></title>
    <?= $assetManager->viteClient() ?>
    <?= $assetManager->styles(config('frontend.entry', 'resources/js/app.jsx')) ?>
</head>
<body>
    <div id="app" 
         data-component="<?= htmlspecialchars(json_encode($component ?? 'App', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>"
         data-props='<?= json_encode($props ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
    </div>
    <?= $assetManager->script(config('frontend.entry', 'resources/js/app.jsx')) ?>
</body>
</html>