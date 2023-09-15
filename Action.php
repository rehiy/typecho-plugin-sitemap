<?php
class Sitemap_Action extends Typecho_Widget implements Widget_Interface_Do
{
	public function action()
	{
		$db = Typecho_Db::get();
		$options = Typecho_Widget::widget('Widget_Options');

		header('Content-Type: application/xml');

		// 文件头部

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// 文章列表

		$articles = $db->fetchAll($db->select()->from('table.contents')
			->where('table.contents.status = ?', 'publish')
			->where('table.contents.created < ?', $options->gmtTime)
			->where('table.contents.type = ?', 'post')
			->order('table.contents.created', Typecho_Db::SORT_DESC));

		foreach ($articles as $article) {
			$type = $article['type'];
			$article['categories'] = $db->fetchAll($db->select()->from('table.metas')
				->join('table.relationships', 'table.relationships.mid = table.metas.mid')
				->where('table.relationships.cid = ?', $article['cid'])
				->where('table.metas.type = ?', 'category')
				->order('table.metas.order', Typecho_Db::SORT_ASC));
			$article['category'] = urlencode(current(Typecho_Common::arrayFlatten($article['categories'], 'slug')));
			$article['slug'] = urlencode($article['slug']);
			$article['date'] = new Typecho_Date($article['created']);
			$article['year'] = $article['date']->year;
			$article['month'] = $article['date']->month;
			$article['day'] = $article['date']->day;
			$routeExists = (NULL != Typecho_Router::get($type));
			$article['pathinfo'] = $routeExists ? Typecho_Router::url($type, $article) : '#';
			$article['permalink'] = Typecho_Common::url($article['pathinfo'], $options->index);
			echo "\t<url>\n";
			echo "\t\t<loc>" . $article['permalink'] . "</loc>\n";
			echo "\t\t<lastmod>" . date('Y-m-d', $article['modified']) . "</lastmod>\n";
			echo "\t\t<changefreq>monthly</changefreq>\n";
			echo "\t\t<priority>0.6</priority>\n";
			echo "\t</url>\n";
		}

		// 单页列表

		$pages = $db->fetchAll($db->select()->from('table.contents')
			->where('table.contents.status = ?', 'publish')
			->where('table.contents.created < ?', $options->gmtTime)
			->where('table.contents.type = ?', 'page')
			->order('table.contents.created', Typecho_Db::SORT_DESC));

		foreach ($pages as $page) {
			$type = $page['type'];
			$routeExists = (NULL != Typecho_Router::get($type));
			$page['pathinfo'] = $routeExists ? Typecho_Router::url($type, $page) : '#';
			$page['permalink'] = Typecho_Common::url($page['pathinfo'], $options->index);
			echo "\t<url>\n";
			echo "\t\t<loc>" . $page['permalink'] . "</loc>\n";
			echo "\t\t<lastmod>" . date('Y-m-d', $page['modified']) . "</lastmod>\n";
			echo "\t\t<changefreq>monthly</changefreq>\n";
			echo "\t\t<priority>0.6</priority>\n";
			echo "\t</url>\n";
		}

		// 文章分类

		$categorys = $db->fetchAll($db->select()->from('table.metas')
			->where('table.metas.type = ?', 'category'));

		foreach ($categorys as $category) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . $this->getPermalinkCategory($category) . "</loc>\n";
			echo "\t\t<lastmod>" . date('Y-m-d', strtotime('-1 days')) . "</lastmod>\n";
			echo "\t\t<changefreq>weekly</changefreq>\n";
			echo "\t\t<priority>0.2</priority>\n";
			echo "\t</url>\n";
		}

		// 标签聚合

		$tags = $db->fetchAll($db->select()->from('table.metas')
			->where('table.metas.type = ?', 'tag'));

		foreach ($tags as $tag) {
			$type = $tag['type'];
			$routeExists = (NULL != Typecho_Router::get($type));
			$tag['pathinfo'] = $routeExists ? Typecho_Router::url($type, $tag) : '#';
			$tag['permalink'] = Typecho_Common::url($tag['pathinfo'], $options->index);
			echo "\t<url>\n";
			echo "\t\t<loc>" . $tag['permalink'] . "</loc>\n";
			echo "\t\t<lastmod>" . date('Y-m-d', strtotime('-1 days')) . "</lastmod>\n";
			echo "\t\t<changefreq>weekly</changefreq>\n";
			echo "\t\t<priority>0.2</priority>\n";
			echo "\t</url>\n";
		}

		// 站点首页

		echo "\t<url>\n";
		echo "\t\t<loc>" . $options->index . "</loc>\n";
		echo "\t\t<lastmod>" . date('Y-m-d') . "</lastmod>\n";
		echo "\t\t<changefreq>always</changefreq>\n";
		echo "\t\t<priority>0.8</priority>\n";
		echo "\t</url>\n";

		// 文件尾部

		echo "</urlset>";
	}

	//生成分类永久链接

	public function getPermalinkCategory($category)
	{
		$db = Typecho_Db::get();
		$options = Typecho_Widget::widget('Widget_Options');

		$type = $category['type'];
		$routeExists = (NULL != Typecho_Router::get($type));
		$category['pathinfo'] = $routeExists ? Typecho_Router::url($type, $category) : '#';
		$category['permalink'] = Typecho_Common::url($category['pathinfo'], $options->index);

		// 获取目录分类
		$directory = '';
		$parent = $db->fetchRow($db->select('table.metas.parent')
			->from('table.metas')
			->where('table.metas.type = ?', 'category')
			->where('table.metas.slug = ?', $category['slug'])
			->limit(1));
		while ($parent['parent'] != 0) {
			$parent = $db->fetchRow($db->select('table.metas.slug', 'table.metas.parent')
				->from('table.metas')
				->where('table.metas.type = ?', 'category')
				->where('table.metas.mid = ?', $parent['parent'])
				->limit(1));
			$directory = $parent['slug'] . '/' . $directory;
		}
		$directory = $directory . $category['slug'];

		return str_replace('{directory}', $directory, $category['permalink']);
	}
}
