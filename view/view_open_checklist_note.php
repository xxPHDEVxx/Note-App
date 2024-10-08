<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open "<?= $note->title ?>"</title>
    <base href="<?= $web_root ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0">
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

</head>

<body>

    <?php include ("open_note.php"); ?>
    <div class="note_body_title">Items</div>

    <div class="note_body_checklist">
        <?php foreach ($note_body as $row): ?>
            <?php if ($row['checked']): ?>
                <form class="check_form" action="note/update_checked" method="post">
                    <input type="text" name="uncheck" value="<?= $row["id"] ?>" class="item" hidden>
                    <input class="material-symbols-outlined check_submit " type="submit" <?=$is_shared_as_reader == 1 ? "disabled" : "" ;?>  value='check_box'
                        id="uncheck<?= $row["id"] ?>">
                    <label class="check_label item_label" for="uncheck<?= $row["id"] ?>" id="<?= $row["id"] ?>">
                        <?= $row["content"] ?></label>
                </form>
            <?php else: ?>
                <form class="check_form" action="note/update_checked" method="post">
                    <input type="text" name="check" value="<?= $row["id"] ?>" class="item" hidden>
                    <input class="material-symbols-outlined check_submit" type="submit"  <?=$is_shared_as_reader == 1 ? "disabled" : "" ;?> value="check_box_outline_blank"
                        id="check<?= $row["id"] ?>">
                    <label class="uncheck_label item_label" for="check<?= $row["id"] ?>" id="<?= $row["id"] ?>">
                        <?= $row["content"] ?></label>
                </form>
            <?php endif; ?>

        <?php endforeach; ?>
    </div>

    <?php include ("view/view_modal_delete.php"); ?>
    <?php include ("view_modal_delete_confirmation.php"); ?>
    <?php include ("view_modal.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="JS/confirmation_delete.js"></script>
    <script src="JS/check_uncheck.js"></script>
</body>

</html>