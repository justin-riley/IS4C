<?php

include(__DIR__ . '/../../../config.php');
if (!class_exists('\\FannieAPI')) {
    include(__DIR__ . '/../../../classlib2.0/FannieAPI.php');
}
if (!class_exists('CategoriesModel')) {
    include(__DIR__ . '/models/CategoriesModel.php');
}

class CommentCategories extends FannieRESTfulPage
{
    protected $header = 'Comment Categories';
    protected $title = 'Comment Categories';

    public function preprocess()
    {
        $this->addRoute('post<id><user>');

        return parent::preprocess();
    }

    protected function post_id_handler()
    {
        $ids = FormLib::get('id');
        $names = FormLib::get('name');
        $nMethods = FormLib::get('notify');
        $nAddrs = FormLib::get('address');
        $ccAddrs = FormLib::get('cc');
        $new = FormLib::get('new');

        $settings = $this->config->get('PLUGIN_SETTINGS');
        $this->connection->selectDB($settings['CommentDB']);
        $model = new CategoriesModel($this->connection);

        if (trim($new) !== '') {
            $model->name($new);
            $model->save();     
        }

        for ($i=0; $i<count($ids); $i++) {
            $model->categoryID($ids[$i]);
            $model->name($names[$i]);
            $model->notifyMethod($nMethods[$i]);
            $model->notifyAddress($nAddrs[$i]);
            $model->ccAddress($ccAddrs[$i]);
            $model->save();
        }

        return 'CommentCategories.php';
    }

    protected function delete_id_handler()
    {
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $this->connection->selectDB($settings['CommentDB']);
        $model = new CategoriesModel($this->connection);
        $model->categoryID($this->id);
        $model->delete();

        return 'CommentCategories.php';
    }

    protected function post_id_user_handler()
    {
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $this->connection->selectDB($settings['CommentDB']);
        $this->connection->startTransaction();
        $delP = $this->connection->prepare('DELETE FROM CategoryUserMap WHERE categoryID=?');
        $this->connection->execute($delP, array($this->id));
        $model = new CategoryUserMapModel($this->connection);
        foreach ($this->user as $uid) {
            if ($uid) {
                $model->categoryID($this->id);
                $model->userID($uid);
                $model->save();
            }
        }
        $this->connection->commitTransaction();

        return 'CommentCategories.php?id=' . $this->id;
    }

    protected function get_id_view()
    {
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $this->connection->selectDB($settings['CommentDB']);
        $model = new CategoriesModel($this->connection);
        $model->categoryID($this->id);
        $model->load();
        $name = $model->name();

        $userP = $this->connection->prepare("SELECT userID FROM CategoryUserMap WHERE categoryID=?");
        $users = $this->connection->getAllValues($userP, $this->id);

        $userR = $this->connection->query("SELECT name, uid FROM " . FannieDB::fqn('Users', 'op') . " ORDER BY name");
        $table = "";
        while ($row = $this->connection->fetchRow($userR)) {
            $selected = in_array($row['uid'], $users);
            $table .= sprintf('<tr class="%s"><td>%s</td>
                <td><input type="checkbox" name="user[]" onchange="rowClasser(this);" value="%d" %s /></td></tr>',
                $selected ? 'info' : '',
                $row['name'],
                $row['uid'],
                $selected ? 'checked' : ''
            ); 
        }

        return <<<HTML
<h4>{$name} Visibility</h4>
<form method="post" action="CommentCategories.php">
<p>
    <button type="submit" class="btn btn-default">Save</button>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href="CommentCategories.php" class="btn btn-default">Back to All Categories</a>
</p>
<input type="hidden" name="id" value="{$this->id}" />
<input type="hidden" name="user[]" value="" />
<table class="table table-bordered">
    {$table}
</table>
<p>
    <button type="submit" class="btn btn-default">Save</button>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href="CommentCategories.php" class="btn btn-default">Back to All Categories</a>
</p>
<script type="text/javascript">
function rowClasser(elem) {
    if (elem.checked) {
        $(elem).closest('tr').addClass('info');
    } else {
        $(elem).closest('tr').removeClass('info');
    }
}
</script>
HTML;
    }

    protected function get_view()
    {
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $this->connection->selectDB($settings['CommentDB']);
        $model = new CategoriesModel($this->connection);

        $rows = '';
        foreach ($model->find('name') as $obj) {
            $rows .= sprintf('<tr><td><input type="hidden" name="id[]" value="%d" />
                <input type="text" class="form-control" name="name[]" value="%s" /></td>
                <td><input type="text" class="form-control" name="notify[]" value="%s" /></td>
                <td><input type="text" class="form-control" name="address[]" value="%s" /></td>
                <td><input type="text" class="form-control" name="cc[]" value="%s" /></td>
                <td><a href="?id=%d">User Access</a></td>
                <td><a href="?_method=delete&id=%d">%s</a>
                </tr>',
                $obj->categoryID(),
                $obj->name(),
                $obj->notifyMethod(),
                $obj->notifyAddress(),
                $obj->ccAddress(),
                $obj->categoryID(),
                $obj->categoryID(),
                COREPOS\Fannie\API\lib\FannieUI::deleteIcon()
            );
        }

        return <<<HTML
<form method="post">
    <table class="table table-bordered">
    <thead>
        <tr><th>Name</th><th>Notification Method</th><th>Notification Address(es)</th><th>CC Address(es)</tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>
<p>
    <div class="form-group">
        <label>Create new category</label>
        <input type="text" name="new" class="form-control" />
    </div>
</p>
<p>
    <button class="btn btn-default btn-core">Save</button>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <a href="ManageComments.php" class="btn btn-default">Back to Comments</a>
</p>
</form>
HTML;
    }
}

FannieDispatch::conditionalExec();

