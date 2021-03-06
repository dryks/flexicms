<?php

namespace Modules\Admin\Controller;

use Flexi;
use View;
use Modules;
use Flexi\Http\Uri;
use Modules\Admin\Service\CustomField\CustomFieldService;
use Flexi\Localization\I18n;
use Modules\Admin\Model\Resource as ResourceModel;
use Modules\Admin\Model\ResourceType as ResourceTypeModel;

/**
 * Class ResourceController
 * @package Modules\Admin\Controller
 */
class ResourceController extends AdminController
{
    /**
     * @var ResourceModel
     */
    protected $resourceModel;

    /**
     * @var ResourceTypeModel
     */
    protected $resourceTypeModel;

    /**
     * ResourceController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->resourceModel = new ResourceModel();
        $this->resourceTypeModel = new ResourceTypeModel();
    }

    /**
     * @param int $resourceId
     * @return Flexi\Template\View
     */
    public function listing(int $resourceId)
    {
        I18n::instance()->load('resources/list');

        $resources = $this->resourceModel->getResources($resourceId);
        $resourceType = $this->resourceTypeModel->getResourceType($resourceId);

        $this->setData('resources', $resources);
        $this->setData('resource_type', $resourceType);

        return View::make('resources/list', $this->data);
    }

    /**
     * @param string $name
     * @return Flexi\Template\View
     */
    public function create(string $name)
    {
        I18n::instance()->load('resources/create');

        $this->setData('resourceType', $this->resourceTypeModel->getResourceTypeByName($name));

        return View::make('resources/create', $this->data);
    }

    /**
     * @param string $name
     * @param int $id
     * @return Flexi\Template\View
     */
    public function edit(string $name, int $id)
    {
        I18n::instance()->load('resources/edit');

        $customFieldService = new CustomFieldService();
        $fileModel = new Modules\Admin\Model\File();

        $resource = $this->resourceModel->getResource($id);
        $customFields = $customFieldService->getResourceFields($resource);

        $image = false;
        if ($resource->getAttribute('thumbnail')) {
            $image = $fileModel->getFile($resource->getAttribute('thumbnail'));
        }

        $this->setData('baseUrl', Uri::base());
        $this->setData('resource', $resource);
        $this->setData('pageTypes', getTypes());
        $this->setData('layouts', getLayouts());
        $this->setData('nameResource', $name);
        $this->setData('customFields', $customFields);
        $this->setData('image', $image);

        return View::make('resources/edit', $this->data);
    }

    public function add()
    {
        $params = Flexi\Http\Input::post();

        if (isset($params['title'])) {
            $resource = new Modules\Admin\Model\Resource;
            $resource->setAttribute('resource_type_id', $params['resource_type_id']);
            $resource->setAttribute('title', $params['title']);
            $resource->setAttribute('content', $params['content']);
            $resource->setAttribute('segment', Flexi\Helper\Text::transliteration($params['title']));
            $resource->save();

            $resourceType = $this->resourceTypeModel->getResourceType($params['resource_type_id']);

            echo '/admin/resource/' . $resourceType->getAttribute('name') . '/edit/' . $resource->getAttribute('id');
            exit;
        }
    }

    public function update()
    {
        $params = Flexi\Http\Input::post();
        $files = Flexi\Http\Input::files();

        $fileId = 0;
        if (!empty($files)) {
            $fileModel = new Modules\Admin\Model\File;

            $uploadFile = $files[0];
            $uploadsDir = path_content('uploads') . '/' . date('Y-m') . '/';
            $name       = 'image-' . time();

            if (!file_exists($uploadsDir)) {
                mkdir($uploadsDir);
            }

            $file = new Flexi\Helper\ImageUploader($uploadFile);
            $file->sendTo = $uploadsDir;
            $file->imageName = $name;

            $upload = $file->uploadImage();

            if ($upload->isUploaded) {
                $params['image'] = $upload->uploadedName;

                $fileId = $fileModel->addFile([
                    'name' => $upload->uploadedName,
                    'link' => '/content/uploads/' . date('Y-m') . '/' . $upload->uploadedName,
                    'type' => $uploadFile['type']
                ]);
            }
        }

        $customFields = [];
        if (!empty($params['custom_fields'])) {
            parse_str($params['custom_fields'], $customFields);
        }

        if (isset($params['title'])) {
            $resource = new Modules\Admin\Model\Resource;
            $resource->setAttribute('id', $params['resource_id']);
            $resource->setAttribute('title', $params['title']);
            $resource->setAttribute('content', $params['content']);
            $resource->setAttribute('status', $params['status']);
            $resource->setAttribute('type', $params['type']);

            if ($fileId) {
                $resource->setAttribute('thumbnail', $fileId);
            }

            $resource->save();

            if (isset($customFields['fields'])) {
                $customFieldValueModel = new Modules\Admin\Model\CustomFieldValue();
                foreach ($customFields['fields'] as $fieldId => $value) {
                    $customFieldValueModel->addUpdateFieldValue([
                        'field_id' => $fieldId,
                        'element_id' => $resource->getAttribute('id'),
                        'value' => $value
                    ]);
                }
            }

            echo $resource->getAttribute('id');
            exit;
        }
    }
}
