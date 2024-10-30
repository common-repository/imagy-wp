<?php

namespace imagy;

class ImagyWPAddMenu{
    public function __construct()
    {
        //独自メニューの追加
        add_action('admin_menu',array($this,'add_submenu_func'));
        //独自メニュー内の要素の指定
        add_action('admin_init',array($this,'add_page_elements'));
        //独自メニューのCSS
        add_action( 'admin_enqueue_scripts', array($this,'my_admin_style'));
    }

    //メニューの追加関数
    public function add_submenu_func()
    {
        //サブメニューの追加
        add_submenu_page(
            'options-general.php', // 親ページのslug(設定)
            'imagy高速化設定', // ページタイトル
            'imagy高速化設定', // メニュータイトル
            'manage_options', // 権限設定
            'imagy.php', // このページのslug
            array($this,'show_page_elements') // ページ内容を表示する関数名
        );
    }
    //ページ内容を表示する関数
    public function show_page_elements()
    {
        ?>
        <h1>imagy高速化設定</h1>
        <form method="POST" action="options.php">
            <?php
            settings_fields('imagy.php');
            do_settings_sections('imagy.php');
            submit_button();
            ?>
        </form>
        <?php
    }

    public function add_page_elements()
    {
        add_settings_section('section_id','imagyURL設定',array($this,'add_description'),'imagy.php');
        add_settings_field('could_front_url','imagy URL',array($this,'add_input_elements'),'imagy.php','section_id');
        register_setting('imagy.php','could_front_url',['sanitize_callback' => 'esc_html']);
    }

    public function add_input_elements()
    {
        ?>
        <input type="text" name="could_front_url" class="regular-text" value="<?php form_option('could_front_url');?>" placeholder="demo.imagy.jp">
        <?php
    }

    public function add_description()
    {
        $plugin_url = parse_url(plugin_dir_url(__FILE__ ))["path"]; //プラグインまでのパス
        $connection_check_image_url = $this->modify_could_front_url(get_option('could_front_url')) . $plugin_url . "img/connection_check.jpg";

        //下記は「connection_check.jpg」のCouldFront側のレスポンスヘッダ取得する為の記述
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $connection_check_image_url,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_USERAGENT => 'User-Agent: Mozilla/5.0',
            CURLOPT_COOKIE => 'foo=bar',
            CURLOPT_HTTPHEADER => ['Accept-language: ja']
        ];
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $ret = curl_exec($ch);
        curl_close($ch);
        $header_info = array_filter(explode("\r\n", $ret)); //レスポンスヘッダをここで取得
        ?>
        <p>お申し込み後に送られてくるimagyのURLを入力してください</p>
        <div class="imagy_status">
            <p>imagy 現在の高速化状態</p>
            <?php 
            if(count($header_info) > 0 && preg_match('/200/',$header_info[0]) === 1):
            ?>
            <p class="on-off true">有効</p>
            <?php else:?>
            <p class="on-off false">無効（imagy URLを入力してください）</p>
            <?php endif;?>
        </div>
        <?php
    }

    //CouldFrontのURLの「https」や「/」の有無などを整える
    private function modify_could_front_url($could_front_url)
    {
        if (preg_match('/^https?:\/\//', $could_front_url) === 0) {
            $could_front_url = 'https://' . $could_front_url;
        }
        if (preg_match('/\/$/', $could_front_url) === 1) {
            $could_front_url = substr($could_front_url, 0, -1);
        }
        return $could_front_url;
    }

    //CSSを読み込む
    public function my_admin_style(){
        $plugin_url = plugin_dir_url( __FILE__ );
        wp_enqueue_style( 'my_admin_style', $plugin_url . '/css/style.css');
    }
}

$imagy_wp_add_menu = new ImagyWPAddMenu();