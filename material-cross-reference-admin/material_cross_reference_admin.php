<?php
/*
Plugin Name: Material Cross Reference Admin
Description: Maintain Material Cross Reference records.
Version: 1.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

add_action('admin_menu', 'gmc_register_admin_menu');

function gmc_register_admin_menu()
{
    add_menu_page(
        'Material Cross Reference',
        'Material XRef',
        'manage_options',
        'material-xref',
        'gmc_render_page',
        'dashicons-database',
        30
    );
}

function gmc_render_page()
{

    global $wpdb;

    // Render list page
    $table = new GMC_Material_CrossRef_Table();

    $action = $_GET['action'] ?? '';

    if ($action === 'add') {
        gmc_material_form();
        return;
    }

    if ($action === 'edit' && !empty($_GET['id'])) {
        gmc_material_form((int)$_GET['id']);
        return;
    }

    // Delete Record
    if (
        isset($_GET['action']) &&
        $_GET['action'] == 'delete' &&
        !empty($_GET['id'])
    ) {

        $wpdb->delete(
            $wpdb->prefix . 'gateway_material_crossreference',
            ['ID' => intval($_GET['id'])]
        );

        echo '<div class="notice notice-success"><p>Record deleted.</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Material Cross Reference</h1>';

    echo '<a href="' .
        admin_url('admin.php?page=material-xref&action=add') .
        '" class="page-title-action">Add New</a>';

    echo '<hr class="wp-header-end">';

    $table->prepare_items();

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="material-xref" />';

    $table->search_box('Search', 'material_search');
    $table->display();

    echo '</form>';
    echo '</div>';
}

class GMC_Material_CrossRef_Table extends WP_List_Table
{

    public function get_columns()
    {
        $columns = array(
            'cb'           => '<input type="checkbox" />',
            'ID'           => 'ID',
            'MaterialID'   => 'Material ID',
            'Manufacturer' => 'Manufacturer',
            'Material'     => 'Material',
            'SortID'       => 'Sort Order',
            'Link'         => 'Data Sheet'
        );
        return $columns;
    }

    public function get_sortable_columns()
    {
        return [
            'ID'           => ['ID', true],
            'MaterialID'   => ['MaterialID', false],
            'Manufacturer' => ['Manufacturer', false],
            'Material'     => ['Material', false],
            'SortID'       => ['SortID', false],
            'Link'         => ['Link', false]
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['ID']
        );
    }

    public function column_ID($item)
    {
        $edit_url = admin_url(
            'admin.php?page=material-xref&action=edit&id=' . $item['ID']
        );

        $actions = [
            'edit' => '<a href="' . $edit_url . '">Edit</a>',
            'delete' => sprintf(
                '<a href="?page=%s&action=delete&id=%s">Delete</a>',
                $_REQUEST['page'],
                $item['ID']
            )
        ];

        return $item['ID'] . $this->row_actions($actions);
    }

    public function column_MaterialID($item)
    {
        return $item['MaterialID'];
    }

    public function column_Manufacturer($item)
    {
        return $item['Manufacturer'];
    }

    public function column_Material($item)
    {
        return $item['Material'];
    }

    public function column_SortID($item)
    {
        return $item['SortID'];
    }

    public function column_Link($item)
    {
        return $item['Link'];
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => 'Delete'
        ];
    }

    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gateway_material_crossreference';

        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (is_array($ids)) $ids = implode(',', $ids);

            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
            }
        }
    }

    public function prepare_items()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gateway_material_crossreference';

        $per_page = 20;
        $current_page = $this->get_pagenum();

        $search = isset($_REQUEST['s'])
            ? sanitize_text_field($_REQUEST['s'])
            : '';

        $orderby = !empty($_REQUEST['orderby'])
            ? sanitize_sql_orderby($_REQUEST['orderby'])
            : 'ID';

        $order = !empty($_REQUEST['order'])
            ? sanitize_text_field($_REQUEST['order'])
            : 'ASC';

        $where = '';

        if (!empty($search)) {

            $where = $wpdb->prepare(
                " WHERE MaterialID LIKE %s
                   OR Manufacturer LIKE %s
                   OR Material LIKE %s",
                "%{$search}%",
                "%{$search}%",
                "%{$search}%"
            );
        }

        $this->process_bulk_action();

        $total_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} {$where}"
        );

        $offset = ($current_page - 1) * $per_page;

        $sql = "
            SELECT *
            FROM {$table_name}
            {$where}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d
        ";

        $this->_column_headers = [
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        ];


        $this->items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset),
            ARRAY_A
        );

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

    }
}

function gmc_material_form($id = 0)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'gateway_material_crossreference';

    $record = [
        'MaterialID'   => '',
        'Manufacturer' => '',
        'Material'     => '',
        'SortID'       => 0,
        'Link'         => ''
    ];

    if (isset($_POST['save_material'])) {

        $data = [
            'MaterialID'   => intval($_POST['MaterialID']),
            'Manufacturer' => sanitize_text_field($_POST['Manufacturer']),
            'Material'     => sanitize_text_field($_POST['Material']),
            'SortID'       => intval($_POST['SortID']),
            'Link'         => sanitize_text_field($_POST['Link'])
        ];

        if ($id > 0) {
            $wpdb->update($table_name, $data, ['ID' => $id]);
        } else {
            $wpdb->insert($table_name, $data);
        }

        echo '<div class="notice notice-success"><p>Saved.</p></div>';
    }

    if ($id > 0) {
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE ID=%d",
                $id
            ),
            ARRAY_A
        );
    }

    ?>

    <div class="wrap">
        <h1><?php echo $id ? 'Edit' : 'Add'; ?> Material</h1>

        <form method="post">

            <table class="form-table">

                <tr>
                    <th>Material ID</th>
                    <td>
                        <input type="number"
                               name="MaterialID"
                               value="<?php echo esc_attr($record['MaterialID']); ?>"
                               class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th>Manufacturer</th>
                    <td>
                        <input type="text"
                               name="Manufacturer"
                               value="<?php echo esc_attr($record['Manufacturer']); ?>"
                               class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th>Material</th>
                    <td>
                        <input type="text"
                               name="Material"
                               value="<?php echo esc_attr($record['Material']); ?>"
                               class="regular-text">
                    </td>
                </tr>

                <tr>
                    <th>Sort Order</th>
                    <td>
                        <input type="number"
                               name="SortID"
                               value="<?php echo esc_attr($record['SortID']); ?>">
                    </td>
                </tr>

                <tr>
                    <th>Data Sheet</th>
                    <td>
                        <input type="text"
                               name="Link"
                               value="<?php echo esc_attr($record['Link']); ?>"
                               class="regular-text">
                    </td>
                </tr>

            </table>

            <table>
                <tr>
                    <td>
                        <?php submit_button('Save', 'primary', 'save_material'); ?>
                    </td>
                    <td>
                        <a class="button" style="margin-left:3px;margin-top:5px;" href="admin.php?page=material-xref">Back</a>
                    </td>
                </tr>
            </table>

        </form>
    </div>

    <?php
}
