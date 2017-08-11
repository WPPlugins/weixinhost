<?php
/*
Plugin Name: 微信侯斯特伴侣
Plugin URI: http://www.weixinhost.com/
Description: 微信侯斯特伴侣的主要功能就是能够将你的公众账号和你的 WordPress 博客连接起来，搜索和用户发送信息匹配的日志，并自动通过微信回复用户。同时，您还可以因此享用到微信侯斯特为您提供的其他服务。
Version: 1.0.3
Author: Weixinhost
Author URI: http://www.weixinhost.com/
 */

define( 'WEIXINHOST__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WEIXINHOST__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEIXINHOST__OPTION_NAME', 'weixinhost_token' );

define('DEFAULT_COVER', 'http://dn-weixinhost-admin-data.qbox.me/b5dc8070e3c5a0f91e162d1da53a59de.png?imageView/1/w/900/h/500');

add_action('init', 'weixinhost_init', 11);

function weixinhost_init($wp) {

    if (isset($_GET['weixinhost']) || isset($_POST['weixinhost'])) {

        $token = get_option(WEIXINHOST__OPTION_NAME);

        if (empty($token)) {
            exit();
        }

        $keywords = $_POST['keywords'];
        $keywords = trim($keywords);

        $results = false;

        if (!empty($keywords)) {
            $results = weixinhost_get_article($keywords);
        }

        $ret = array();

        if ($results) {
            $ret['err_code'] = 0;
            $ret['err_msg'] = 'success';
            $ret['data'] = $results;
        } else {
            $ret['err_code'] = 10001;
            $ret['err_msg'] = 'No Results';
        }

        echo json_encode($ret);
        exit();
    }
}

function weixinhost_get_article($keywords, $limit = 10) {

    $keywords = explode('|', $keywords);

    if (empty($keywords) || !is_array($keywords)) {
        return false;
    }

    $results = array();

    foreach ($keywords as $keyword) {

        query_posts(
            array('s' => $keyword)
        );
        $i = 0;

        $list = array();

        while( have_posts() && ($i < 10) ) {

            the_post();

            $article = array();

            $article['title'] = get_the_title();
            $article['content'] = mb_strimwidth(strip_tags(get_the_content()) , 0 , 200 , '...' , 'UTF-8' );
            $article['url'] = get_site_url().'/?p='.get_the_id();
            $article['pic'] = weixinhost_thumbnail_url(get_the_post_thumbnail());
            if (empty($article['pic'])) {
                $article['pic'] = weixinhost_get_content_first_image(get_the_content());
            }

            if( !$article['pic'] ) $article['pic'] = DEFAULT_COVER;

            $list[] = $article;
            $i++;
        }
        $results[] = array('keyword' => $keyword, 'articles' => $list);
    }

    if(count($results) > 0) {
        return $results;
    } else {
        return false;
    }
}

function weixinhost_get_content_first_image($content){
    if ( $content === false ) $content = get_the_content();

    preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $content, $images);

    if($images){
        return $images[1][0];
    }else{
        return false;
    }
}

function weixinhost_thumbnail_url($html) {
    $reg = '/src="(.+?)"/is';
    if(preg_match( $reg , $html , $out ))
    {
        return $out[1];
    }

    return false;
}

add_filter('plugin_action_links', 'add_weixinhost_settings_link', 10, 2 );
function add_weixinhost_settings_link($links, $file) {

    if ( $file == plugin_basename( __FILE__ ) ) {

        $args = array( 'page' => 'weixinhost-config' );

        $url = add_query_arg( $args, class_exists( 'Jetpack' ) ? admin_url( 'admin.php' ) : admin_url( 'options-general.php' ) );

        $links[] = '<a href="' . esc_url( $url ) . '">'.esc_html__( 'Settings' , 'akismet').'</a>';
    }

    return $links;
}

add_action( 'admin_menu', 'weixinhost_admin_menu', 5 );

function weixinhost_admin_menu() {
    if ( class_exists( 'Jetpack' ) )
        add_action( 'jetpack_admin_menu', 'weixinhost_load_menu' );
    else
        weixinhost_load_menu();
}

function weixinhost_load_menu() {
    if ( class_exists( 'Jetpack' ) )
        add_submenu_page( 'jetpack', '微信侯斯特伴侣', '微信侯斯特伴侣', 'manage_options', 'weixinhost-config', 'weixinhost_display_config_page' );
    else
        add_options_page( '微信侯斯特伴侣', '微信侯斯特伴侣', 'manage_options', 'weixinhost-config', 'weixinhost_display_config_page' );

}

function weixinhost_display_config_page() {

    $current_token = '';

    $notice = '';
    $token = '';

    if (isset($_POST['token'])) {
        $token = $_POST['token'];
        $token = trim($token);

        if (!empty($token)) {

            $wp_url = get_site_url();

            $url = 'http://api.weixinhost.com/3/addon/api?name=wordpress&action=bind_site';

            $post = array(
                'method' => 'POST',
                'timeout' => 30,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array( 'wp_token' => $token, 'wp_url' => $wp_url ),
                'cookies' => array()
            );

            $response = wp_remote_post($url, $post);

            if ( is_wp_error( $response ) ) {

                $error_message = $response->get_error_message();

                $notice = weixinhost_error($error_message);

            } else {

                $body = $response['body'];;
                $body = json_decode($body, true);

                if (!empty($body['err_code'])) {
                    $notice = weixinhost_error($body['err_msg']);
                } else {
                    $data = $body['data'];

                    if (empty($data['id'])) {
                        $notice = weixinhost_error('服务器返回数据异常，请重试。');
                    } else {

                        $current_token = get_option(WEIXINHOST__OPTION_NAME);

                        $r = false;

                        if (empty($current_token)) {
                            $r = add_option(WEIXINHOST__OPTION_NAME, $token, '', 'no');
                        } else {
                            $r = update_option(WEIXINHOST__OPTION_NAME, $token);
                        }

                        if (!$r) {
                            $notice = weixinhost_error('数据写入错误，请重试。');
                        } else {

                            $txt = '您已成功激活您的WordPress站点，开启侯斯特之旅吧！';

                            if (isset($data['coupon'])) {
                                $txt .= '<br><br><strong>我们赠送您“侯斯特50元代金卷”一张，请前往“侯斯特”登录使用进行充值：' . $data['coupon']['code'] . '</strong>';
                            }

                            $notice = weixinhost_success($txt);
                        }
                    }
                }
            }
        }
    } else {
        $token = get_option(WEIXINHOST__OPTION_NAME);

        if ($token) {

            $wp_url = get_site_url();

            $url = 'http://api.weixinhost.com/3/addon/api?name=wordpress&action=site_detail';

            $post = array(
                'method' => 'POST',
                'timeout' => 30,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => array( 'wp_token' => $token, 'wp_url' => $wp_url ),
                'cookies' => array()
            );

            $response = wp_remote_post($url, $post);

            if ( is_wp_error( $response ) ) {

                $error_message = $response->get_error_message();

                $notice = weixinhost_error($error_message);

                delete_option(WEIXINHOST__OPTION_NAME);
                $token = '';

            } else {

                $body = $response['body'];;
                $body = json_decode($body, true);

                if (!empty($body['err_code'])) {
                    $notice = weixinhost_error($body['err_msg']);

                    delete_option(WEIXINHOST__OPTION_NAME);
                    $token = '';

                } else {

                    $txt = '您的WordPress站点已经激活，继续侯斯特之旅吧！';

                    if (isset($body['data']['coupon'])) {
                        $txt .= '<br><br><strong>我们赠送您“侯斯特50元代金卷”一张，请前往“侯斯特”登录使用进行充值：' . $body['data']['coupon']['code'] . '</strong>';
                    }
                    $notice = weixinhost_success($txt);
                }
            }
        } else {
            $notice = weixinhost_error('您还没有绑定您的WordPress站点到“侯斯特”，请前往“侯斯特”注册并绑定您的微信公众号，并在侯斯特插件商店免费获取“Wordpress伴侣”插件来获取识别码。');
        }
    }

    $file = WEIXINHOST__PLUGIN_DIR . 'views/config.php';

    include( $file );

}

function weixinhost_error($msg) {

    $msg = str_replace('侯斯特', '<a href="http://www.weixinhost.com" target="_blank">侯斯特</a>', $msg);

    return "<div class='error'><p>$msg</p></div>";
}

function weixinhost_success($msg) {

    $msg = str_replace('侯斯特', '<a href="http://www.weixinhost.com" target="_blank">侯斯特</a>', $msg);

    return "<div class='updated'><p>$msg</p></div>";
}

?>
