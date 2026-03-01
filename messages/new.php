<?php
// messages/new.php
// Redirect: ?to=X → chat.php?user_id=X
// Linked from connections page as "Message" button

require_once "../includes/helpers.php";

$to = (int) ($_GET['to'] ?? 0);

if ($to > 0) {
    redirect("chat.php?user_id={$to}");
} else {
    redirect("inbox.php");
}
?>
