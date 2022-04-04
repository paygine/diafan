<?php

if (! defined('DIAFAN'))
{
	$path = __FILE__;
	while(! file_exists($path.'/includes/404.php'))
	{
		$parent = dirname($path);
		if($parent == $path) exit;
		$path = $parent;
	}
	include $path.'/includes/404.php';
}

class Payment_paygine_admin
{
	public $config;

	public function __construct()
	{
		$this->config = array(
			"name" => 'Paygine',
			"params" => array(
				'paygine_sector' => 'Номер сектора',
				'paygine_password' => 'Пароль',
				'paygine_test' => array('name' => 'Тестовый режим', 'type' => 'checkbox'),
				'paygine_kkt' => array('name' => 'Передавать данные на свое ККТ', 'type' => 'checkbox'),
                'paygine_tax' => array(
                    'name' => 'Ставка НДС',
                    'type' => 'select',
                    'select' => array(
                        1 => 'ставка НДС 18%',
                        2 => 'ставка НДС 10%',
                        3 => 'ставка НДС расч. 18/118',
                        4 => 'ставка НДС расч. 10/110',
                        5 => 'ставка НДС 0%',
                        6 => 'НДС не облагается',
                    ),
                ),
            )
		);
	}
}