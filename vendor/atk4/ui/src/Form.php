<?php

namespace atk4\ui;

/**
 * Implements a form.
 */
class Form extends View //implements \ArrayAccess - temporarily so that our build script dont' complain
{
    use \atk4\core\HookTrait;

    // @inheritdoc
    public $ui = 'form';

    // @inheritdoc
    public $defaultTemplate = 'form.html';

    /**
     * When form is submitted successfully, this template is used by method
     * success() to replace form contents.
     *
     * @var string
     */
    public $successTemplate = 'form-success.html';

    /**
     * A current layout of a form, needed if you call $form->addField().
     *
     * @var \atk4\ui\FormLayout\Generic
     */
    public $layout = null;

    /**
     * Disables form contents.
     *
     * {@inheritdoc}
     */
    public $content = false;

    /**
     * Will point to the Save button. If you don't want to have save, destroy
     * it. Initialized by setLayout().
     *
     * @var Button
     */
    public $buttonSave;

    /**
     * Add field into current layout. If no layout, create one. If no model, create blank one.
     *
     * @param mixed ...$args
     *
     * @return FormField\Generic
     */
    public function addField(...$args)
    {
        if (!$this->model) {
            $this->model = new \atk4\ui\misc\ProxyModel();
        }

        if (!$this->layout) {
            $this->setLayout();
        }

        return $this->layout->addField(...$args); //$this->fieldFactory($modelField));
    }

    /**
     * Add header into the form, which appears as a separator.
     *
     * @param string $title
     *
     * @return \atk4\ui\FormLayout\Generic
     */
    public function addHeader($title = null)
    {
        if (!$this->layout) {
            $this->setLayout();
        }

        return $this->layout->addHeader($title);
    }

    /**
     * Creates a group of fields and returns layout.
     *
     * @param string|array $title
     *
     * @return \atk4\ui\FormLayout\Generic
     */
    public function addGroup($title = null)
    {
        if (!$this->layout) {
            $this->setLayout();
        }

        return $this->layout->addGroup($title);
    }

    /**
     * Sets form layout.
     *
     * @param string|\atk4\ui\FormLayout\Generic $layout
     */
    public function setLayout($layout = null)
    {
        if (!$layout) {
            $layout = new \atk4\ui\FormLayout\Generic(['form' => $this]);
        }

        $this->layout = $this->add($layout);
        $this->layout->addButton($this->buttonSave = new Button(['Save', 'primary']));
        $this->buttonSave->on('click', $this->js()->form('submit'));
    }

    /**
     * Adds callback in submit hook.
     *
     * @param callable $callback
     */
    public function onSubmit($callback)
    {
        $this->addHook('submit', $callback);
    }

    /**
     * Provided with a Agile Data Model Field, this method have to decide
     * and create instance of a View that will act as a form-field.
     *
     * @param mixed ...$args
     *
     * @return Form\Field\Generic
     */
    public function fieldFactory(...$args)
    {
        if (is_string($args[0]) && ($modelField = $this->model->hasElement($args[0]))) {
            // $modelField is set above
        } elseif ($args[0] instanceof \atk4\data\Field) {
            $modelField = $args[0];
        } else {
            $modelField = $this->model->addField(...$args);
        }

        return $this->_fieldFactory($modelField);
    }

    /**
     * Will come up with a column object based on the field object supplied.
     *
     * @param \atk4\data\Field $f
     *
     * @return FormField\Generic
     */
    public function _fieldFactory(\atk4\data\Field $f)
    {
        switch ($f->type) {
        case 'boolean':
            return new FormField\Checkbox(['form'=>$this, 'field'=>$f, 'short_name'=>$f->short_name]);

        default:
            return new FormField\Line(['form'=>$this, 'field'=>$f, 'short_name'=>$f->short_name]);

        }
    }

    /**
     * Associates form with the model but also specifies which of Model
     * fields should be added automatically.
     *
     * If $actualFields are not specified, then all "editable" fields
     * will be added.
     *
     * @param \atk4\data\Model $model
     * @param array            $fields
     *
     * @return \atk4\data\Model
     */
    public function setModel(\atk4\data\Model $model, $fields = null)
    {
        $model = parent::setModel($model);

        // Will not try to populate any fields
        if ($fields === false) {
            return $model;
        }

        if (!$this->layout) {
            $this->setLayout(new \atk4\ui\FormLayout\Generic(['form'=>$this]));
        }

        if ($fields === null) {
            $fields = [];
            foreach ($model->elements as $f) {
                if (!$f instanceof \atk4\data\Field) {
                    continue;
                }

                if (!$f->isEditable()) {
                    continue;
                }
                $fields[] = $f->short_name;
            }
        }

        if (is_array($fields)) {
            foreach ($fields as $field) {
                $modelField = $model->getElement($field);

                $formField = $this->layout->addField($this->fieldFactory($modelField));
            }
        } else {
            throw new Exception(['Incorrect value for $fields', 'fields'=>$fields]);
        }

        return $model;
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        $this->addHook('submit', [$this, 'loadPOST']);
    }

    /**
     * Looks inside the POST of the request and loads it into a current model.
     */
    public function loadPOST()
    {
        $data = array_intersect_key($_POST, $this->model->elements);
        $this->model->set($this->app->ui_persistence->typecastLoadRow($this->model, $data));
    }

    /**
     * Causes form to generate error.
     *
     * @param string $field Field name
     * @param string $str   Error message
     *
     * @return jsChain
     */
    public function error($field, $str)
    {
        return $this->js()->form('add prompt', $field, $str);
    }

    /**
     * Causes form to generate success message.
     *
     * @param string $str        Success message
     * @param string $sub_header Sub-header
     *
     * @return jsChain
     */
    public function success($str = 'Success', $sub_header = null)
    {
        $success = $this->app->loadTemplate($this->successTemplate);
        $success['header'] = $str;

        if ($sub_header) {
            $success['message'] = $sub_header;
        } else {
            $success->del('p');
        }

        $js = $this->js()
            ->html($success->render());

        return $js;
    }

    /**
     * {@inheritdoc}
     */
    public function renderView()
    {
        $this->ajaxSubmit();

        return parent::renderView();
    }

    /**
     * Returns JS Chain that targets INPUT element of a specified field. This method is handy
     * if you wish to set a value to a certain field.
     *
     * @param string $name Name of element
     *
     * @return jsChain
     */
    public function jsInput($name)
    {
        return $this->layout->getElement($name)->js()->find('input');
    }

    /**
     * Returns JS Chain that targets INPUT element of a specified field. This method is handy
     * if you wish to set a value to a certain field.
     *
     * @param string $name Name of element
     *
     * @return jsChain
     */
    public function jsField($name)
    {
        return $this->layout->getElement($name)->js();
    }

    /**
     * Does ajax submit.
     */
    public function ajaxSubmit()
    {
        $this->_add($cb = new jsCallback(), ['desired_name'=>'submit', 'POST_trigger'=>true]);

        $this->add(new View(['element'=>'input']))
            ->setAttr('name', $cb->name)
            ->setAttr('value', 'submit')
            ->setAttr('type', 'hidden');

        $cb->set(function () {
            $response = $this->hook('submit');
            if (!$response) {
                return new jsExpression('console.log([])', ['Form submission is not handled']);
            }

            return $response;
        });

        $this->js(true)
            ->api(['url'=>$cb->getURL(),  'method'=>'POST', 'serializeForm'=>true])
            ->form(['inline'=>true, 'on'=>'blur']);

        $this->on('change', 'input', $this->js()->form('remove prompt', new jsExpression('$(this).attr("name")')));
    }
}
