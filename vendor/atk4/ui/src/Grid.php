<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\ui;

class Grid extends Lister
{
    use \atk4\core\HookTrait;

    // Overrides
    public $defaultTemplate = 'grid.html';
    public $ui = 'table';
    public $content = false;

    /**
     * Column objects can service multiple columns. You can use it for your advancage by re-using the object
     * when you pass it to addColumn(). If you omit the argument, then a column of a type 'Generic' will be
     * used.
     *
     * @var Column\Generic
     */
    public $default_column = null;

    /**
     * Contains list of declared columns. Value will always be a column object.
     *
     * @var array
     */
    public $columns = [];

    /**
     * Allows you to inject HTML into grid using getHTMLTags hook and column call-backs.
     * Switch this feature off to increase performance at expense of some row-specific HTML.
     *
     * @var bool
     */
    public $use_html_tags = true;

    /**
     * Determines a strategy on how totals will be calculated. Do not touch those fields
     * direcly, instead use addTotals().
     *
     * @var bool
     */
    public $totals_plan = false;

    /**
     * Setting this to false will hide header row.
     *
     * @var bool
     */
    public $header = true;

    /**
     * Contains list of totals accumulated during the render process.
     *
     * @var array
     */
    public $totals = [];

    /**
     * Contain the template for the "Head" type row.
     *
     * @var Template
     */
    protected $t_head;

    /**
     * Contain the template for the "Body" type row.
     *
     * @var Template
     */
    protected $t_row;

    /**
     * Contain the template for the "Foot" type row.
     *
     * @var Template
     */
    protected $t_totals;

    /**
     * Contains the output to show if table contains no rows.
     *
     * @var Template
     */
    protected $t_empty;

    /**
     * Defines a new column for this field. You need two objects for field to
     * work.
     *
     * First is being Model field. If your Grid is already associated with
     * the model, it will automatically pick one by looking up element
     * corresponding to the $name.
     *
     * The other object is a Column. This object know how to produce HTML for
     * cells and will handle other things like alignment. If you do not specify
     * column, then it will be selected dynamically based on field type
     *
     * And third object is a Field. You can use it in case your current data
     * model doesn't already have such field.
     *
     * @param string         $name      Data model field name
     * @param Column\Generic $columnDef
     * @param array          $fieldDef  Array of defaults for new Model field
     *
     * @return Column\Generic
     */
    public function addColumn($name, $columnDef = null, $fieldDef = [])
    {
        if (!$this->model) {
            $this->model = new \atk4\ui\misc\ProxyModel();
        }

        $field = $this->model->hasElement($name);
        if (!$field) {
            $field = $this->model->addField($name, $fieldDef);
        }

        if ($columnDef === null) {
            $columnDef = $this->_columnFactory($field);
        } elseif (is_string($columnDef) || is_array($columnDef)) {
            if (!$this->app) {
                throw new Exception(['You can only specify column type by name if Grid is in a render-tree']);
            }

            $columnDef = $this->add($columnDef, $name);
        } else {
            $this->add($columnDef, $name);
        }

        $columnDef->grid = $this;
        $this->columns[$name] = $columnDef;

        return $columnDef;
    }

    /**
     * Will come up with a column object based on the field object supplied.
     * By default will use default column.
     *
     * @param \atk4\data\Field $f Data model field
     *
     * @return Column\Generic
     */
    public function _columnFactory(\atk4\data\Field $f)
    {
        switch ($f->type) {
        //case 'boolean':
            //return $this->add(new Column\Checkbox());

        default:
            if (!$this->default_column) {
                $this->default_column = $this->add(new Column\Generic());
            }

            return $this->default_column;
        }
    }

    /**
     * Overrides work like this:.
     * [
     *   'name'=>'Totals for {$num} rows:',
     *   'price'=>'--',
     *   'total'=>['sum']
     * ].
     *
     * @param array $plan
     */
    public function addTotals($plan = [])
    {
        $this->totals_plan = $plan;
    }

    /**
     * Init method will create one column object that will be used to render
     * all columns in the grid unless you have specified a different
     * column object.
     */
    public function init()
    {
        parent::init();

        if (!$this->t_head) {
            $this->t_head = $this->template->cloneRegion('Head');
            $this->t_row_master = $this->template->cloneRegion('Row');
            $this->t_totals = $this->template->cloneRegion('Totals');
            $this->t_empty = $this->template->cloneRegion('Empty');

            $this->template->del('Head');
            $this->template->del('Body');
            $this->template->del('Foot');
        }
    }

    /**
     * Sets data Model of Grid.
     *
     * If $columns is not defined, then automatically will add columns for all
     * visible model fields. If $columns is set to false, then will not add
     * columns at all.
     *
     * @param \atk4\data\Model $m       Data model
     * @param array|bool       $columns
     *
     * @return \atk4\data\Model
     */
    public function setModel(\atk4\data\Model $m, $columns = null)
    {
        parent::setModel($m);

        if ($columns === null) {
            $columns = [];
            foreach ($m->elements as $name => $element) {
                if (!$element instanceof \atk4\data\Field) {
                    continue;
                }

                if ($element->isVisible()) {
                    $columns[] = $name;
                }
            }
        } elseif ($columns === false) {
            return;
        }

        foreach ($columns as $column) {
            $this->addColumn($column);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function renderView()
    {
        if (!$this->columns) {
            throw new Exception(['Grid does not have any columns defined', 'columns'=>$this->columns]);
        }

        // Generate Header Row
        if ($this->header) {
            $this->t_head->setHTML('cells', $this->renderHeaderCells());
            $this->template->setHTML('Head', $this->t_head->render());
        }

        // Generate template for data row
        $this->t_row_master->setHTML('cells', $this->getRowTemplate());
        $this->t_row_master['_id'] = '{$_id}';
        $this->t_row = new Template($this->t_row_master->render());
        $this->t_row->app = $this->app;

        // Iterate data rows
        $rows = 0;
        foreach ($this->model as $this->current_id => $tmp) {
            $this->current_row = $this->model->get();

            if ($this->totals_plan) {
                $this->updateTotals();
            }

            $this->t_row->set($this->model);

            if ($this->use_html_tags) {
                // Prepare row-specific HTML tags.
                $html_tags = [];

                foreach ($this->hook('getHTMLTags', [$this->model]) as $ret) {
                    if (is_array($ret)) {
                        $html_tags = array_merge($html_tags, $ret);
                    }
                }

                foreach ($this->columns as $name => $column) {
                    if (!method_exists($column, 'getHTMLTags')) {
                        continue;
                    }
                    $field = $this->model->hasElement($name);
                    $html_tags = array_merge($column->getHTMLTags($this->model, $field), $html_tags);
                }

                // Render row and add to body
                $this->t_row->setHTML($html_tags);
                $this->t_row->set('_id', $this->model->id);
                $this->template->appendHTML('Body', $this->t_row->render());
                $this->t_row->del(array_keys($html_tags));
            } else {
                $this->template->appendHTML('Body', $this->t_row->render());
            }

            $rows++;
        }

        // Add totals rows or empty message.
        if (!$rows) {
            $this->template->appendHTML('Body', $this->t_empty->render());
        } elseif ($this->totals_plan) {
            $this->t_totals->setHTML('cells', $this->renderTotalsCells());
            $this->template->appendHTML('Foot', $this->t_totals->render());
        } else {
        }

        return View::renderView();
    }

    /**
     * Executed for each row if "totals" are enabled to add up values.
     */
    public function updateTotals()
    {
        foreach ($this->totals_plan as $key=>$val) {
            if (is_array($val)) {
                switch ($val[0]) {
                case 'sum':
                    if (!isset($this->totals[$key])) {
                        $this->totals[$key] = 0;
                    }
                    $this->totals[$key] += $this->model[$key];
                }
            }
        }
    }

    /**
     * Responds with the HTML to be inserted in the header row that would
     * contain captions of all columns.
     *
     * @return string
     */
    public function renderHeaderCells()
    {
        $output = [];
        foreach ($this->columns as $name => $column) {
            $field = $this->model->hasElement($name);

            $output[] = $column->getHeaderCell($field);
        }

        return implode('', $output);
    }

    /**
     * Responsd with HTML to be inserted in the footer row that would
     * contain totals fro all columns.
     *
     * @return string
     */
    public function renderTotalsCells()
    {
        $output = [];
        foreach ($this->columns as $name => $column) {
            if (!isset($this->totals_plan[$name])) {
                $output[] = $this->app->getTag('th', '-');
                continue;
            }

            if (is_array($this->totals_plan[$name])) {
                // todo - format
                $field = $this->model->getElement($name);
                $output[] = $column->getTotalsCell($field, $this->totals[$name]);
                continue;
            }

            $output[] = $this->app->getTag('th', [], $this->totals_plan[$name]);
        }

        return implode('', $output);
    }

    /**
     * Collects cell templates from all the columns and combine them into row template.
     *
     * @return string
     */
    public function getRowTemplate()
    {
        $output = [];
        foreach ($this->columns as $name => $column) {
            $field = $this->model->hasElement($name);

            $output[] = $column->getCellTemplate($field);
        }

        return implode('', $output);
    }
}
