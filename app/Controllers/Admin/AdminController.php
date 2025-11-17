<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;

	abstract class AdminController extends Controller
	{
		protected function ensureMasterAdmin(): void
		{
			if (!\Auth::check()) {
				$this->redirect('/login.php');
			}

			if (\Auth::userType() !== 'master_admin') {
				app_logger()->warning('Unauthorized admin access attempt', [
					'user_id' => \Auth::id(),
					'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
				]);
				$this->redirect('/index.php');
			}
		}
	}