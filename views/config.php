<?php
$args = array( 'page' => 'weixinhost-config' );

$weixinhost_form_url = add_query_arg( $args, class_exists( 'Jetpack' ) ? admin_url( 'admin.php' ) : admin_url( 'options-general.php' ) );
?>

<div class="wrap">
<h2>侯斯特“WordPress伴侣”插件设置</h2>

<?php echo $notice;?>

<form method="post" action="<?php echo esc_url( $weixinhost_form_url ); ?>">
    <table class="form-table">
    <tbody>
    <tr>
        <th scope="row"><label for="token">识别码</label></th>
        <td><input name="token" type="text" id="token" value="<?php echo $token;?>" class="regular-text code">
        <p class="description">请前往“<a href="http://www.weixinhost.com" target="_blank">侯斯特</a>”注册并绑定您的微信公众号，并在侯斯特插件商店免费获取“Wordpress伴侣”插件来获取识别码。</p>
        </td>
    </tr>
    </tbody>
    </table>
    <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="保存更改"></p>
</form>
