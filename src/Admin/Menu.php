<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Admin;

final class Menu {
	private const PARENT_SLUG = 'kevira';
	private const PAGE_SLUG   = 'kevira-mail-gateway';

	public function __construct( private readonly Page $page ) {}

	public function register(): void {
		global $admin_page_hooks;
		if ( ! isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			add_menu_page( 'Kevira', 'Kevira', 'manage_options', self::PARENT_SLUG, '__return_null', $this->menuIcon(), 58 );
			remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
		}
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Kevira Mail Gateway', 'kevira-mail-gateway' ),
			__( 'Mail Gateway', 'kevira-mail-gateway' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this->page, 'render' ),
			35
		);
	}

	/**
	 * @param list<string> $links Existing plugin links.
	 * @return list<string>
	 */
	public function actionLinks( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'تنظیمات', 'kevira-mail-gateway' ) . '</a>' );
		return $links;
	}

	private function menuIcon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#fff" d="M5 3h3v5.1L13 3h3.8l-6.1 6.2L17 17h-3.9L8 10.7V17H5z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
