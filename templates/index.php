<div class="page-header">
    <h1>Payment</h1>
</div>

<form action="" method="post">
    <button class="btn btn-success" type="submit" name="success">Pay</button>
    <hr><button class="btn btn-danger" type="submit" name="fail">Decline</button>
    <?php foreach ($params as $key => $value): ?>
    <input type="hidden" name="<?= $key ?>" value="<?= $value ?>">
    <?php endforeach; ?>
</form>
