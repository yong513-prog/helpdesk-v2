<?php


if(!function_exists('normalize_pic_display_value'))
{
    function normalize_pic_display_value($pic)
    {
        $pic = trim((string)$pic);
        $legacyPicLabels = ['负责人','負責人','Pegawai Bertanggungjawab','Person In Charge','person in charge','PIC'];
        return in_array($pic, $legacyPicLabels, true) ? 'PIC' : $pic;
    }
}

if(!function_exists('csv_to_array_safe'))
{
    function csv_to_array_safe($value)
    {
        $items = [];
        foreach(explode(',', (string)$value) as $item)
        {
            $item = trim($item);
            if($item !== '') $items[] = $item;
        }
        return array_values(array_unique($items));
    }
}

if(!function_exists('get_active_pic_options'))
{
    function get_active_pic_options($pdo, $extraValues = [])
    {
        $fallback = ['KIAT','ANDY','LAS','KRISHNAN','HQ','ADMIN'];
        $pics = [];

        try
        {
            $stmt = $pdo->query("SELECT pic_name FROM pic_master WHERE status = 1 ORDER BY pic_name ASC");
            $pics = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        catch(Exception $e)
        {
            $pics = $fallback;
        }

        foreach((array)$extraValues as $value)
        {
            foreach(csv_to_array_safe($value) as $v)
            {
                $pics[] = $v;
            }
        }

        $clean = [];
        $uiOnlyPicLabels = [
            '负责人', '負責人', 'Person In Charge', 'Select Person In Charge',
            '选择负责人', '選擇負責人', 'Pilih Pegawai Bertanggungjawab'
        ];
        foreach($pics as $pic)
        {
            $pic = trim((string)$pic);
            if($pic !== '' && !in_array($pic, $uiOnlyPicLabels, true)) $clean[] = $pic;
        }

        return array_values(array_unique($clean));
    }
}

if(!function_exists('normalize_selected_pics'))
{
    function normalize_selected_pics($rawValue, $allowedPics)
    {
        if(is_array($rawValue))
        {
            $selected = [];
            foreach($rawValue as $v)
            {
                $selected = array_merge($selected, csv_to_array_safe($v));
            }
        }
        else
        {
            $selected = csv_to_array_safe($rawValue);
        }

        $allowedMap = array_flip($allowedPics);
        $final = [];
        foreach($selected as $pic)
        {
            if(isset($allowedMap[$pic])) $final[] = $pic;
        }
        return array_values(array_unique($final));
    }
}

if(!function_exists('render_pic_checkbox_grid'))
{
    function render_pic_checkbox_grid($fieldName, $picList, $selectedPics)
    {
        echo '<div class="quick-actions mb-2">';
        echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleBoxes(\'' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '[]\',true)">Select All PIC</button> ';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleBoxes(\'' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '[]\',false)">Clear PIC</button>';
        echo '</div>';
        echo '<div class="choice-grid">';
        foreach($picList as $pic)
        {
            $pic = normalize_pic_display_value($pic);
            $checked = in_array($pic, $selectedPics, true) ? 'checked' : '';
            echo '<label class="choice-card">';
            echo '<input type="checkbox" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '[]" value="' . htmlspecialchars($pic, ENT_QUOTES, 'UTF-8') . '" ' . $checked . '>';
            echo '<span><strong>' . htmlspecialchars($pic, ENT_QUOTES, 'UTF-8') . '</strong><small>Person In Charge</small></span>';
            echo '</label>';
        }
        echo '</div>';
    }
}
