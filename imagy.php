<?php
/*
Plugin Name: imagy WP（イメージー）
Description: サイト高速化サービス imagy（イメージー）のWordPressプラグイン。全自動で画像を圧縮＆キャッシュ＆高速配信を行うサービスです。ご利用にはimagyのお申し込みが必要です。
Version: 1.0.0
Author: 株式会社ナインステクノロジーズ
Author URI: https://imagy.jp/
Plugin URI: /wp-admin/options-general.php?page=imagy.php
*/

namespace imagy;

if (! defined('ABSPATH')) {
    exit;
}

class ImagyWP
{
    public function __construct()
    {
        // 管理画面にてcould_front_urlが保存された時にupload_url_pathの書き換えとhtaccessへの追記を行う
        add_action('update_option_could_front_url', function () {
            if (get_option('could_front_url') != false && !empty(get_option('could_front_url'))) {
                $could_front_url = $this->modify_could_front_url(get_option('could_front_url')); //Could Front側のURLを整形して取得
                $uploads_path = rtrim(parse_url(wp_upload_dir()['baseurl'])["path"], '/'); //uploadsディレクトリまでのパスを取得
                update_option('upload_url_path', $could_front_url . $uploads_path . '/');
                if (get_option('imagy_is_working') == 0) {
                    $this->write_rule_on_htaccess($could_front_url);
                }
                update_option('imagy_is_working', 1);
            } else {
                $this->delete_rule_on_htaccess();
                update_option('upload_url_path', '');
                update_option('imagy_is_working', 0);
            }
        });
        //プラグインを無効化した時、sop_imagy_pluginを呼び出す
        register_deactivation_hook(__FILE__,array( $this, 'stop_imagy_plugin' ));
    }
    //htaceessを編集してimagyの為の記述を追加
    private function write_rule_on_htaccess($could_front_url)
    {
        $file_data=fopen(dirname(__DIR__, 3).'/.htaccess', 'a');
        if ($file_data===false) { //ファイルが開けなかったエラー
            return false;
        }

        fwrite($file_data, "#Imagy-Plugin START");
        fwrite($file_data, "\n<IfModule mod_rewrite.c>");
        fwrite($file_data, "\n  RewriteEngine on");
        fwrite($file_data, "\n  RewriteCond %{HTTP_AUTHORIZATION} !Basic [NC]");
        fwrite($file_data, "\n  RewriteCond %{REQUEST_FILENAME} ^(.*)\.(png|jpe?g) [NC]");
        fwrite($file_data, "\n  RewriteCond %{HTTP_REFERER} !_imagy_debug [NC]");
        fwrite($file_data, "\n  RewriteCond %{HTTP_REFERER} " . addcslashes(urlencode($_SERVER['HTTP_HOST']), '.') . " [NC]");
        fwrite($file_data, "\n  RewriteRule ^(.*)$ " . $could_front_url . "%{REQUEST_URI} [R=301,QSA,L]");
        fwrite($file_data, "\n</IfModule>");
        fwrite($file_data, "\n#END Imagy-Plugin");

        fclose($file_data);

        return;
    }

    //htaceessを編集してimagyの為の記述を削除
    private function delete_rule_on_htaccess()
    {
        $file_array=file(dirname(__DIR__, 3).'/.htaccess');
        if ($file_array===false) { //ファイルが開けなかったエラー
            return false;
        }

        $begin_point=0; //.htaccessの記述開始地点
        $end_point=0;   //.htaccessの記述終了地点
        $count=0;
        foreach ($file_array as $record) {
            if (strpos($record, '#Imagy-Plugin START')!==false) { //記述開始地点の取得
                $begin_point=$count;
            }
            if (strpos($record, '#END Imagy-Plugin')!==false) {   //記述終了地点の取得
                $end_point=$count;
                break;
            }
            ++$count;
        }
        if ($begin_point>=$end_point) { //.htaccessの記述が見つからなかったエラー
            return false;
        }

        for ($cnt=0;$cnt<=($end_point-$begin_point);++$cnt) { //記述開始地点と記述終了地点の間を削除
            unset($file_array[$begin_point+$cnt]);
        }

        file_put_contents(dirname(__DIR__, 3).'/.htaccess', $file_array);

        return;
    }
    //Imagyのプラグインを無効にした時の処理
    //htaccessからimagy高速化の為の記述を削除、また画像のpathに関するデータの書き換え
    public function stop_imagy_plugin()
    {
        $this->delete_rule_on_htaccess();
        update_option('could_front_url', '');
        update_option('upload_url_path', '');
        update_option('imagy_is_working', 0);
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
}

$imagy_wp = new ImagyWP();

//imagyの管理画面追加処理を行うadd_menu.phpを読み込む
include('add_menu.php');
