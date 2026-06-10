<?php
/*
Plugin Name: Material Cross Reference
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit;
}

class Material_Cross_Reference {

    public function __construct() {
        add_shortcode('material_cross_reference', array($this, 'render_shortcode'));

        add_action('wp_ajax_mcr_get_materials', array($this, 'ajax_get_materials'));
        add_action('wp_ajax_nopriv_mcr_get_materials', array($this, 'ajax_get_materials'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {

        wp_register_script(
            'mcr-script',
            '',
            array('jquery'),
            '1.0',
            true
        );

        wp_enqueue_script('mcr-script');

        wp_localize_script(
            'mcr-script',
            'mcr_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php')
            )
        );

        $js = <<<JS
jQuery(document).ready(function($){

    $('#mcr_manufacturer').on('change', function(){

        var manufacturer = $(this).val();

        $('#mcr_material').html('<option value="">Loading...</option>');

        $.post(
            mcr_ajax.ajax_url,
            {
                action: 'mcr_get_materials',
                manufacturer: manufacturer
            },
            function(response){
                $('#mcr_material').html(response);
            }
        );
        $('#mcr_filter_form').submit();
    });

    $('#mcr_material').on('change', function(){
        $('#mcr_filter_form').submit();
    });

});
JS;

        wp_add_inline_script('mcr-script', $js);

        $css = <<<CSS
.mcr-container {
    margin:20px 0;
}

.mcr-filters {
    display:flex;
    gap:15px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.mcr-filters select {
    min-width:250px;
    padding:8px;
}

.mcr-grid {
    width:100%;
    border-collapse:collapse;
}

.mcr-grid th,
.mcr-grid td {
    border:1px solid #ddd;
    padding:10px;
}

.mcr-grid th {
    background:#f5f5f5;
    text-align:left;
}
CSS;

        wp_register_style('mcr-style', false);
        wp_enqueue_style('mcr-style');
        wp_add_inline_style('mcr-style', $css);
    }

    public function ajax_get_materials() {

        global $wpdb;

        $table = $wpdb->prefix . 'gateway_material_crossreference';

        $manufacturer = sanitize_text_field($_POST['manufacturer']);

        $materials = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT DISTINCT Material
                FROM {$table}
                WHERE Manufacturer = %s
                ORDER BY Material
                ",
                $manufacturer
            )
        );

        echo '<option value="">Select Material</option>';

        foreach ($materials as $material) {
            echo '<option value="' . esc_attr($material->Material) . '">'
                . esc_html($material->Material)
                . '</option>';
        }

        wp_die();
    }

    public function render_shortcode() {

        global $wpdb;

        $table = $wpdb->prefix . 'gateway_material_crossreference';

        $selected_manufacturer = isset($_GET['manufacturer'])
            ? sanitize_text_field($_GET['manufacturer'])
            : '';

        $selected_material = isset($_GET['material'])
            ? sanitize_text_field($_GET['material'])
            : '';

        $manufacturers = $wpdb->get_results(
            "
            SELECT DISTINCT Manufacturer
            FROM {$table}
            ORDER BY Manufacturer
            "
        );

        $materials = array();

        if (!empty($selected_manufacturer)) {

            $materials = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT DISTINCT Material
                    FROM {$table}
                    WHERE Manufacturer = %s
                    ORDER BY Material
                    ",
                    $selected_manufacturer
                )
            );
        }

        $where = array();
        $params = array();

        if (!empty($selected_manufacturer)) {
            $where[] = 'Manufacturer = %s';
            $params[] = $selected_manufacturer;
        }
	else
	{
            $where[] = 'Manufacturer = %s';
            $params[] = 'abc123';
	}

        if (!empty($selected_material)) {
            $where[] = 'Material = %s';
            $params[] = $selected_material;
        }
	else
	{
            $where[] = 'Material = %s';
            $params[] = 'abc123';
	}

        $params_manufacturer = 'TDK';

	/*
        $sql = "
            SELECT
                ID,
                MaterialID,
                Manufacturer,
                Material,
                SortID,
                Link,
            FROM {$table}
            WHERE
                Manufacturer = 'TDK' AND Material = 'PC30/PC32'
        ";
	*/

        $sql = "
            SELECT
                b.Manufacturer,
                b.Material,
                min(b.SortID) as SortID,
                max(b.Link) as LinkURL
            FROM
                (
                    SELECT
                        ID,
                        MaterialID,
                        Manufacturer,
                        Material,
                        SortID,
                        Link
                    FROM {$table}
	";

	if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
                ) as a
            JOIN {$table} b
            ON  a.MaterialID = b.MaterialID
        ";

        $sql .= ' GROUP BY Manufacturer, Material';

        $sql .= ' ORDER BY SortID, Manufacturer';

        if (!empty($params)) {
            $results = $wpdb->get_results(
                $wpdb->prepare($sql, $params)
            );
        } else {
            $results = $wpdb->get_results($sql);
        }

        ob_start();
        ?>

        <div class="mcr-container">

            <form id="mcr_filter_form" method="get">

                <?php
                foreach ($_GET as $key => $value) {

                    if (!in_array($key, array('manufacturer', 'material'))) {
                        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                    }
                }
                ?>

                <div class="mcr-filters">

                    <select name="manufacturer" id="mcr_manufacturer">
                        <option value="">Select Manufacturer</option>

                        <?php foreach ($manufacturers as $manufacturer) : ?>
                            <option
                                value="<?php echo esc_attr($manufacturer->Manufacturer); ?>"
                                <?php selected($selected_manufacturer, $manufacturer->Manufacturer); ?>
                            >
                                <?php echo esc_html($manufacturer->Manufacturer); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="material" id="mcr_material">
                        <option value="">Select Material</option>

                        <?php foreach ($materials as $material) : ?>
                            <option
                                value="<?php echo esc_attr($material->Material); ?>"
                                <?php selected($selected_material, $material->Material); ?>
                            >
                                <?php echo esc_html($material->Material); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" style="padding: 4px 16px 4px 16px;">
                        Search
                    </button>

                </div>

            </form>

            <table class="mcr-grid">

                <thead>
                    <tr>
                        <th>Manufacturerf</th>
                        <th>Material</th>
                        <th>Data Sheet</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($results)) : ?>

                    <?php foreach ($results as $row) : ?>

                        <tr>
                            <td>
                                <?php if (esc_html($row->SortID) < 99) : ?>
                                    <b>
                                <?php endif; ?>
                                <?php echo esc_html($row->Manufacturer); ?>
                                <?php if (esc_html($row->SortID) < 99) : ?>
                                    </b>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (esc_html($row->SortID) < 99) : ?>
                                    <b>
                                <?php endif; ?>
                                <?php echo esc_html($row->Material); ?>
                                <?php if (esc_html($row->SortID) < 99) : ?>
                                    </b>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (esc_html($row->SortID) < 99) : ?>
                                    <b>
                                <?php endif; ?>
                                <?php if (esc_html($row->LinkURL) > "") : ?>
                                    <a href="<?php echo esc_html($row->LinkURL); ?>" target="_blank">Data Sheet</a>
                                <?php endif; ?>
                                <?php if (esc_html($row->SortID) < 99) : ?>
                                    </b>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else : ?>

                    <tr>
                        <td colspan="5">
                            No records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <?php
        return ob_get_clean();
    }
}

new Material_Cross_Reference();