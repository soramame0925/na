<?php
// セキュリティ対策：直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) exit;

// サイドバーを表示する設定なら表示
if ( SWELL_Theme::is_show_sidebar() ) {
	get_sidebar();
}
?>
</div> <!-- メインコンテンツエリアの閉じタグ -->

<?php
$SETTING = SWELL_Theme::get_setting();

// Pjax使用時、Barbaコンテナを閉じる
if ( SWELL_Theme::is_use( 'pjax' ) ) echo '</div>';

// ================================
// フッター手前のウィジェットエリア
// ================================
if ( is_active_sidebar( 'before_footer' ) ) :
	echo '<div id="before_footer_widget" class="w-beforeFooter">';
	// ajax_footer が無効な場合だけ出力（Pjaxで別途処理されることがあるため）
	if ( ! SWELL_Theme::is_use( 'ajax_footer' ) ) :
		SWELL_Theme::get_parts( 'parts/footer/before_footer' );
	endif;
	echo '</div>';
endif;

// ================================
// ぱんくずリスト（設定が "top" 以外のとき表示）
// ================================
if ( 'top' !== $SETTING['pos_breadcrumb'] ) :
	SWELL_Theme::get_parts( 'parts/breadcrumb' );
endif;
?>

<!-- ========================= -->
<!-- フッター本体 -->
<!-- ========================= -->
<footer id="footer" class="l-footer">
	<?php
	// Ajaxフッターでない場合にフッターコンテンツを出力
	if ( ! SWELL_Theme::is_use( 'ajax_footer' ) )
		SWELL_Theme::get_parts( 'parts/footer/footer_contents' );
	?>
</footer>

<?php
// 固定ボタン（お問い合わせやTOPへ戻るなど）
// ============================
SWELL_Theme::get_parts( 'parts/footer/fix_btns' );

// ============================
// モーダルウィンドウ関連の出力（ログイン・検索など）
// ============================
SWELL_Theme::get_parts( 'parts/footer/modals' );
?>

<!-- ============================ -->
<!-- フィルター用ライブラリの読み込み -->
<!-- ============================ -->

<!-- Ajaxフィルター関連のスクリプトは functions.php から読み込み -->




</div><!--/ #all_wrapp -->


<?php
get_template_part( 'parts/footer/fix_menu' );

// =========================
// WordPressのフッターフック
// =========================
wp_footer();

// SWELL設定による追加コード
echo $SETTING['foot_code']; // phpcs:ignore
?>
</body>
</html>