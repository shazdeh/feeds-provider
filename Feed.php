<?php
/**
 * @version   $Id$
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2013 RocketTheme, LLC
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
 */

class RokSprocket_Provider_Feed extends RokSprocket_Provider_AbstarctWordpressBasedProvider
{
	/**
	 * @static
	 * @return bool
	 */
    public static function isAvailable()
   	{
		return class_exists( 'WP_Widget' );
   	}

	/**
	 * @param array $filters
	 * @param array $sort_filters
	 */
	public function __construct($filters = array(), $sort_filters = array())
	{
		parent::__construct('feed');
		$this->setFilterChoices($filters, $sort_filters);
	}

	public function getItems() {
		add_filter( 'wp_feed_cache_transient_lifetime', array( &$this, 'set_cache_time' ) );
		$feed = fetch_feed( $this->params->get( 'feed_source' ) );
		remove_filter( 'wp_feed_cache_transient_lifetime', array( &$this, 'set_cache_time' ) );

		$collection = new RokSprocket_ItemCollection();
		if( ! is_wp_error( $feed ) ) {
			$maxitems = $feed->get_item_quantity(0); // return all items 
			$rss_items = $feed->get_items(0, $maxitems);

			foreach( $rss_items as $raw_item ) {
				$item = $this->convertRawToItem( $raw_item );
				$collection[$item->getId()] = $item;
			}
		}

		$this->mapPerItemData($collection);
		return $collection;
	}

	public function set_cache_time( $seconds ) {
		return $this->params->get( 'feed_cache', 12 ) * 60 * 60;
	}

	protected function convertRawToItem($raw_item, $dborder = 0) {
		$item = new RokSprocket_Item();
		$item->setProvider('feed');
		$item->setId( md5($raw_item->get_ID()) ); // md5 the ID to remove weird characters
		$item->setAlias($raw_item->get_title());
		$item->setTitle($raw_item->get_title());
		$item->setDate($raw_item->get_date());
		$item->setPublished(true);
		$item->setText($raw_item->get_content());
		$item->setAuthor($raw_item->get_author());
		$item->setCategory($raw_item->get_category());
		$item->setHits(null);
		$item->setRating(null);
		$item->setMetaKey(null);
		$item->setMetaDesc(null);
		$item->setMetaData(null);

        //set up images array
        $images = array();
		preg_match_all( "/\<img.+?src=\"(.+?)\".+?\/>/", $raw_item->get_content(), $images_matches );
        if( isset( $images_matches[1][0] ) && ! empty( $images_matches[1][0] ) ) {
            $image = new RokSprocket_Item_Image();
            $source = $images_matches[1][0];
			if( $this->params->get( 'feed_download_images', 0 ) ) {
				$source = $this->download_image_copy( $source );
			}
			$image->setSource( $source );
            $image->setIdentifier('image_thumbnail');
            $image->setCaption( $this->get_image_caption( $images_matches[0][0] ) );
            $image->setAlttext( $this->get_image_caption( $images_matches[0][0] ) );
            $images[$image->getIdentifier()] = $image;
        }
        $item->setImages($images);
        $item->setPrimaryImage($images['image_thumbnail']);

		$primary_link = new RokSprocket_Item_Link();
		$primary_link->setUrl( $raw_item->get_permalink() );
		$primary_link->getIdentifier('article_link');
		$item->setPrimaryLink( $primary_link );

		return $item;
	}

	public function get_image_caption( $image_tag ) {
		preg_match( "/alt=\"(.+?)\"/", $image_tag, $matches );
		return isset( $matches[1] ) ? $matches[1] : '';
	}

	function download_image_copy( $url ) {
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');

		$tmp = download_url( $url );
		if( is_wp_error( $tmp ) ) {
			@unlink( $tmp );
			return $url;
		}

		$upload_dir = wp_upload_dir();
		$pathparts = pathinfo( $tmp );
		$_info = pathinfo( $url );

		// fix file extension
		$filename =  preg_replace( '|\..*$|', '.' . $_info['extension'], basename( $tmp ) );
		$result = copy( $file_array['tmp_name'], $upload_dir['path'] . DS . $filename );
		@unlink( $file_array['tmp_name'] );

		return ( $result ) ? trailingslashit( $upload_dir['url'] ) . $filename : $url;
	}

    /**
     * @param $id
     *
     * @return string
     */
    protected function getArticleEditUrl($id)
    {
        return;
    }

    /**
     * @return array the array of image type and label
     */
    public static function getImageTypes()
    {
        return;
    }

    /**
     * @return array the array of link types and label
     */
    public static function getLinkTypes()
    {
        return;
    }

    /**
     * @return array the array of link types and label
     */
    public static function getTextTypes()
    {
        return;
    }
}
