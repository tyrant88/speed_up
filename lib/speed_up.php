<?php

class speed_up {
    
    private $urls = [];

    function __construct($profile = 'auto') { 

        $article_current = rex_article::getCurrent();
        $article_current_prio = $article_current->getPriority();

        $category_current = $article_current->getCategory();

        if($category_current) {
            $category_children = $category_current->getChildren(true);
            $category_parent = $category_current->getParent();
            $category_articles = $category_current->getArticles(true);
            $category_current_prio = $category_current->getPriority();    
        } else {
            $category_children = rex_category::getRootCategories(true);
            $category_parent = [];
            $category_articles = rex_article::getRootArticles(true);
            $category_current_prio = 0;
        }
        $mount_id = rex_yrewrite::getCurrentDomain()->getMountId();
        if(rex_category::get($mount_id)) {
            $category_mount_children = rex_category::get($mount_id)->getChildren(true);
        }
        $start_id = rex_yrewrite::getCurrentDomain()->getStartId();
        $current_id = $article_current->getId();

        $category_neighbours = [];

        if($category_parent != null && $category_current->getId() != $mount_id) {
            // Nur wenn wir uns nicht in Root befinden oder überhalb eines Mount-Points - andere YRewrite-Domains möchten wir nicht prefetchen.
            $category_neighbours = $category_parent->getChildren(true);
        }

        // Manuelle Einstellungen
        $article_prefetch_config = explode(",",speed_up::getConfig('prefetch_articles'));

        if(self::getConfig('profile') === 'auto') {

            // Mount-Point = oberste Navigationsebene (Startseite könnte auch in einer Unterkategorie sein)
            foreach($category_mount_children as $category) {
                $urls[$category->getId()] = $category->getUrl();
            }
            
            // Nur das erste Kind-Element
            foreach($category_children as $category) {
                $urls[$category->getId()] = $category->getUrl();
                continue;
            }

            $neighbours = 0;
            // Nur die Nachbar-Kategorien
            foreach($category_neighbours as $category) {
                if($category->getPriority() != $category_current_prio - 1 && $category->getPriority() != $category_current_prio + 1) {
                    continue;
                }
                $urls[$category->getId()] = $category->getUrl();
                // Nach 2 gefundenen Nachbarn aussteigen  
                if(++$neighbours == 2) {
                    break;
                };
            }

            $neighbours = 0;
            // Nur die Nachbar-Artikel
            foreach($category_articles as $article) {
                if($article->getPriority() != $article_current_prio - 1 && $article->getPriority() != $article_current_prio + 1) {
                    continue;
                }
                $urls[$article->getId()] = $article->getUrl();  
                if(++$neighbours == 2) {
                    break;
                };
            }

            if($category_current && $category_current->getId() != $start_id) {
                // Startseite hinzufügen
                $urls[$start_id] = rex_article::get($start_id)->getUrl();
            }

        }
        else if(self::getConfig('profile') === 'aggressive') {
            
            // Mount-Point = oberste Navigationsebene (Startseite könnte auch in einer Unterkategorie sein)
            foreach($category_mount_children as $category) {
                $urls[$category->getId()] = $category->getUrl();
            }
            
            foreach($category_children as $category) {
                $urls[$category->getId()] = $category->getUrl();  
            }

            foreach($category_articles as $article) {
                $urls[$article->getId()] = $article->getUrl();  
            }
            


            foreach($category_neighbours as $category) {
                $urls[$category->getId()] = $category->getUrl();
            }
            

            if($category_current && $category_current->getId() != $start_id) {
                // Startseite hinzufügen
                $urls[$start_id] = rex_article::get($start_id)->getUrl();
            }
        }
        
        foreach($article_prefetch_config as $article_id) {
            $article = rex_article::get($article_id);
            if($article) {
            $urls[$article_id] = $article->getUrl();                
            }
        }

        unset($urls[$current_id]);

        $urls = rex_extension::registerPoint(new rex_extension_point(
            'PREFETCH_URLS',
            $urls
        ));

        /*
        if(rex_addon::get('url')->isAvailable()) {
            // gut zu wissen, ob URL installiert ist - damit könnte man noch etwas anfangen.
            $is_url_addon_url = Url\Url::resolveCurrent();
        }
        */
        if(rex_addon::get('ycom')->isAvailable()) {
            // YCom-spezifische Seiten wie Logout sollten keinesfalls geladen werden.
            unset($urls[rex_config::get('ycom', 'article_id_jump_ok')]);
            unset($urls[rex_config::get('ycom', 'article_id_jump_not_ok')]);
            unset($urls[rex_config::get('ycom', 'article_id_jump_logout')]);
            unset($urls[rex_config::get('ycom', 'article_id_jump_denied')]);
            unset($urls[rex_config::get('ycom', 'article_id_jump_password')]);
            unset($urls[rex_config::get('ycom', 'article_id_jump_termsofuse')]);

            // Reicht das? Wie verhält sich das prefetching zwischen einem ein- und ausgeloggten Nutzer?
        }

        unset($urls[rex_yrewrite::getCurrentDomain()->getNotfoundId()]);
        
        $this->urls = $urls;

        return $this;
    }


    public function show() {

        if(self::getConfig('profile') === 'disabled') {
            return;
        }
        echo PHP_EOL;
        echo self::getConfig('preload').PHP_EOL;
        echo self::getConfig('prefetch').PHP_EOL;
        
        $preload_media_config = explode(",",speed_up::getConfig('preload_media'));
        
        foreach($preload_media_config as $file) {
            echo '<link rel="preload" href="'. rex_path::media($file) .'">'.PHP_EOL;
        }
        
        if(self::getConfig('profile') === "custom") {
            return;
        }

        foreach($this->urls as $url) {
            echo '<link rel="prefetch" href="'. $url .'">'.PHP_EOL;
        }

        return;

    }

    public static function install() {
        rex_metainfo_add_field('translate:art_speed_up_label', 'art_speed_up', '100', '', 5, '|1|', '1:translate:art_speed_up_label');
    }

    public static function getConfig($key) {
        return rex_config::get('speed_up', $key);
    }

}
