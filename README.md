PHP script to merge multiple anime or manga lists (from myanimelist.net) into a single list. The generated list is sorted by the average rating of the shows, using the same weighting algorithm as mal.

#### Requirements
- PHP (cli) 5.4+
- Lynx command line browser
- Read- and writable directories
- Probably linux
- [Php-html-generator](https://github.com/Airmanbzh/php-html-generator)

#### Example
createList.php displays how to use the script, I'm running it as a Cronjob. Feel free change the template.html, following placeholders are available:
- {$TYPE} 'Anime' or 'Manga'
- {$USER} Table containing user informations (Html)
- {$LIST} Ranked Anime / Manga list (Html)
- {$PAGINATION} Pagination (Html)


[Anime list example](http://rmanimelist.thextor.de/anime.html)

[Manga list example](http://rmanimelist.thextor.de/manga.html)