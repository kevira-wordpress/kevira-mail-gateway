<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Admin;

final class Menu {
	public function __construct( private readonly Page $page ) {}

	public function register(): void {
		if ( ! $this->hasKeviraMenu() ) {
			add_menu_page( 'Kevira', 'Kevira', 'manage_options', 'kevira', array( $this->page, 'render' ), 'dashicons-admin-generic', 58 );
		}
		add_submenu_page( 'kevira', __( 'Mail Gateway', 'kevira-mail-gateway' ), __( 'Mail Gateway', 'kevira-mail-gateway' ), 'manage_options', 'kevira-mail-gateway', array( $this->page, 'render' ), 35 );
	}

	/**
	 * @param list<string> $links Existing plugin links.
	 * @return list<string>
	 */
	public function actionLinks( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=kevira-mail-gateway' ) ) . '">' . esc_html__( 'Settings', 'kevira-mail-gateway' ) . '</a>' );
		return $links;
	}

	private function hasKeviraMenu(): bool {
		global $menu;
		foreach ( is_array( $menu ) ? $menu : array() as $item ) {
			if ( isset( $item[2] ) && 'kevira' === $item[2] ) {
				return true;
			}
		}
		return false;
	}
}
