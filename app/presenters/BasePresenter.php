<?php

namespace App\Presenters;

use Model,
	Nette,
	Nette\DateTime,
	Nette\Utils\Json,
	App\Components,
	Nette\Forms\Controls;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{

	/** @var Model\ImageManager @inject */
	public $imageManager;

	/** @var Model\PageManager @inject */
	public $pageManager;

	/** @var Model\LogManager @inject */
	public $logManager;

	/** @var Nette\Http\Request @inject */
	public $httpRequest;

	/** @var Model\SetupManager */
	public $config;


	public function __construct(Model\SetupManager $setup)
	{
		$this->config = $setup;
	}
	
	public function beforeRender()
	{
		$this->template->copy = $this->config->web['copy'];
	}

	protected function createComponentSignInForm($name)
	{
		$form = new SignInForm($this, $name);
		$form->onSuccess[] = [$this, 'signInFormSucceeded'];
		return $form;
	}

	public function signInFormSucceeded(SignInForm $form)
	{
		$values = $form->getValues();

		$log = array(
			'ts' => new \DateTime,
			'values' => '',
		);
		$logValues = array(
			'user' => $values->username,
			'ip' => $this->httpRequest->getRemoteAddress(),
			'message' => '',
		);
		try {
			$this->getUser()->login($values->username, $values->password);
			$logValues['message'] = 'SignIn success.';
			$log['values'] = Json::encode($logValues);
			$this->logManager->save($log);
			$this->flashMessage('Přihlášení proběhlo v pořádku.', 'success');

		} catch (Nette\Security\AuthenticationException $e) {
			$logValues['message'] = $e->getMessage();
			$log['values'] = Json::encode($logValues);
			$this->logManager->save($log);
			$this->flashMessage('Při přihlášení došlo k chybě.', 'danger');
		}
		if($this->isAjax())
			$this->redrawControl();
		else
			$this->redirect('this');
	}

    /**
     * Create main menu
     *
     * @return Components\Menu
     */
	public function createComponentMenu()
	{
		$menu = new Components\Menu($this->pageManager);
		return $menu;
	}

    /**
     * Create image gallery component
     *
     * @return Components\Images
     */
	public function createComponentImages()
	{
		$images = new Components\Images($this->imageManager);
		return $images;
	}

	public function handleSignOut()
	{
		$this->getUser()->logout();
		$this->flashMessage('Odhlášení proběhlo v pořádku.', 'success');
		if($this->isAjax())
			$this->redrawControl();
		else
			$this->redirect('Homepage:Default');
	}

}
