<?php
declare(strict_types=1);
require_login();

render('categorias', [
    'conceptos'   => conceptos_all(),
    'eliminadas'  => conceptos_deleted(),
], 'Categorías');
