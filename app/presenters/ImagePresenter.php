<?php
namespace App\Presenters;

use Nette,
	Nette\Forms\Controls\SubmitButton,
	Model,
	App\Forms,
	Nette\Utils\Strings,
	Nette\Utils\Html,
	Nette\Image,
	Tracy\Debugger;


class ImagePresenter extends SecurePresenter
{

	/** @var Model\ImageManager @inject */
	public $imageManager;

	public function renderDefault($id)
	{
		$vp = $this['vp'];
		$paginator = $vp->paginator;
		$paginator->itemCount = $this->imageManager->getCountAll();
		$this->template->page = $paginator->page;
		$this->template->pictures = $this->imageManager->findAll()->limit($paginator->itemsPerPage,$paginator->offset);
	}

	public function actionEdit($id,$page)
	{
		if($this->getUser()->isLoggedIn() && $this->getUser()->isAllowed('Image','edit')) {
			if ($id > 0) {
				$val = $this->imageManager->getByID($id);
				$this['pictureForm']->setDefaults($val);
			} else {
				$this->flashMessage('Chyba při načítání dat.', 'danger');
				$this->redirect('Homepage:');
			}
		} else {
			$this->flashMessage('Nemáš práva pro daný modul.', 'danger');
			$this->redirect('Homepage:');
		}
	}

	public function handleUpload()
	{
		Debugger::log('test');
	}

	public function handleImport()
	{
		if($this->getUser()->isLoggedIn() && $this->getUser()->isAllowed('Image','import')) {
			$srcDir = realpath( __DIR__ . '/../../images/import/');
			$dstDir = realpath( __DIR__ . '/../../images/');
			$thumbDir = realpath( __DIR__ . '/../../images/thumbs/');
	
			if ($handle = opendir($srcDir)) {
				$i = 0;
				$values = array('file' => '', 'description' => '');
				while (false !== ($file = readdir($handle))) {
					if ($file != "." && $file != "..") {
						if (($i<=5)) {
							$values['file'] = $file;
							copy($srcDir.'/'.$file, $dstDir.'/'.$file);
							$image = Image::fromFile($srcDir.'/'.$file);
							$width = $image->getWidth();
							$height = $image->getHeight();
							if($width > $height) {
								$image->resize(NULL,400);
								$thumb_width = $image->getWidth();
								$image->crop(($thumb_width-400)/2,0,400,400);
							} elseif($width < $height) {
								$image->resize(400,NULL);
								$thumb_height = $image->getHeight();
								$image->crop(0,($thumb_height-400)/2,400,400);
							}
							$image->sharpen();
							$image->save($thumbDir.'/'.$file);
							unlink($srcDir.'/'.$file);
							$this->imageManager->save($values);
							$i++;
						}
					}
				}
				closedir($handle);
				if ($i>0)
					$this->flashMessage('Fotky ('.$i.') byly přidány.', 'success');
				else
					$this->flashMessage('Žádná fotka nebyla nachystána k importu', 'info');
				$this->redirect('Image:');
			}
		} else {
			$this->flashMessage('Nemáš práva pro daný modul.', 'danger');
			$this->redirect('Homepage:');
		}
	}

	protected function createComponentPictureForm($name)
	{
		$form = new Forms\PictureForm($this, $name);
		$form['ok']->onClick[] = callback($this, 'pictureFormSubmitted');
		return $form;
	}
	
	public function pictureFormSubmitted(SubmitButton $button)
	{
		$values = $button->getForm()->getValues();
		$result = $this->imageManager->save($values);
		if ($result == 'inserted') {
			$this->flashMessage('Nový obrázek byl vytvořen', 'success');
		} elseif($result == 'updated') {
			$this->flashMessage('Obrázek byl změněn.', 'success');
		} else {
			$this->flashMessage('Došlo k chybě při ukládání.', 'danger');
		}
		$this->redirect('Image:');
	}
	
	public function handleDelete($id)
	{
		if($this->getUser()->isLoggedIn() && $this->getUser()->isAllowed('Image','deleted')) {
			$deleted = $deleted_thumb = FALSE;
			$picture = $this->imageManager->getById($id);
			$file = realpath( __DIR__ . '/../../images/'.$picture->file);
			if (is_file($file) && unlink($file)) 
				$deleted = TRUE;
			$thumb = realpath( __DIR__ . '/../../images/thumbs/'.$picture->file);
			if (is_file($thumb) && unlink($thumb)) 
				$deleted_thumb = TRUE;
			if ($deleted === TRUE && $deleted_thumb === TRUE) {
				$picture = $this->imageManager->deleteById($id);
				$this->flashMessage('Fotka byla smazána.', 'success');
				$this->redirect('Image:');
			} else {
				$this->flashMessage('Chyba při mazání fotky.', 'danger');
				$this->redirect('Image:');
			}
		}
	}

}