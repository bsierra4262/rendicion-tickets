<?php
declare(strict_types=1);
redirect(auth_user() ? 'dashboard' : 'login');
