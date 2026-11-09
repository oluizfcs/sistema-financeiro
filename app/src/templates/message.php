<div class="msg"  style="width: 100%;">
    <?php
    if (isset($_SESSION['message'])) {
        switch ($_SESSION['message'][1]) {
            case 'success':
                $bg = '#d4edda';
                $color = '#155724';
                $border = '#c3e6cb';
                break;
            case 'fail':
                $bg = '#f8d7da';
                $color = '#721c24';
                $border = '#f5c6cb';
                break;
            default:
                $bg = '#fff3cd';
                $color = '#856404';
                $border = '#ffeeba';
        }
        echo "<div style=\"
            background-color: $bg;
            color: $color;
            border: 1px solid $border;
            border-radius: 8px;
            padding: 12px 18px;
            margin: 15px 0;
            font-size: 15px;
            font-family: Arial, sans-serif;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
           
            text-align: center;
        \">" . $_SESSION['message'][0] . "</div>";
        unset($_SESSION['message']);
    }
    ?>
</div>
