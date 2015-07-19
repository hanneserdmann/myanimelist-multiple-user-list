<?php

namespace MALMultipleUserListGenerator;

use HtmlGenerator\HtmlTag;

require_once('html-generator/Markup.php');
require_once('html-generator/HtmlTag.php');

class UserListGenerator {
    const   MAL_XML_LINK        = 'http://myanimelist.net/malappinfo.php?u=',
            MAL_XML_LINK_PARAMS = '&status=all&type=',
            MAL_PROFILE_LINK    = 'http://myanimelist.net/profile/',
            MAL_ANIME_LINK      = 'http://myanimelist.net/anime/',
            MAL_MANGA_LINK      = 'http://myanimelist.net/manga/',
            CSS_PREFIX          = 'mal-';

    private $tempDir                    = '',
            $outputDir                  = '',
            $templateFile               = '',
            $maxParallelDownloads       = 3,
            $waitSecondsForFileSystem   = 1,
            $maxEntriesPerPage          = 200,
            $globalAvgScore             = 0.0,
            $userNames                  = [];

    /**
     * @param array $userNames Array with myanimelist.net usernames
     * @param string $tempDir path to the temp directory, must be read- and writable
     * @param string $outputDir path to the output directory, must be read- and writable
     * @param string $templateFile path to the template file
     * @throws \InvalidArgumentException
     */
    public function __construct(array $userNames, $tempDir, $outputDir, $templateFile){
        $this->userNames = array_unique($userNames);

        if  ($this->checkDirectoryPermissions($tempDir)) $this->tempDir = $tempDir;
        else throw new \InvalidArgumentException('The temp directory has to be writable and readable!');

        if  ($this->checkDirectoryPermissions($outputDir)) $this->outputDir = $outputDir;
        else throw new \InvalidArgumentException('The output directory has to be writable and readable!');

        if  (is_file($templateFile) && is_readable($templateFile)) $this->templateFile = $templateFile;
        else throw new \InvalidArgumentException('The template file is not accessible!');

        $this->deleteFilesInDirectory($this->tempDir);
    }

    /**
     * Empties the temp directory
     */
    public function __destruct(){
        $this->deleteFilesInDirectory($this->tempDir);
    }

    /**
     * @param int $downloads Number of parallel downloads
     */
    public function setMaxParallelDownload($downloads = 5){
        if ($downloads = (integer) abs($downloads) === 0){
            $downloads++;
        }

        $this->maxParallelDownloads = $downloads;
    }

    /**
     * @param int $seconds Seconds to wait for the file system after the download
     */
    public function setWaitSecondsForFileSystem($seconds = 1){
        $this->$waitSecondsForFileSystem = (integer) abs($seconds);
    }

    /**
     * @param int $entries max entries per page
     */
    public function setMaxEntriesPerPage($entries){
        $this->maxEntriesPerPage = (integer) abs($entries);
    }

    /**
     * Generate the anime list
     */
    public function generateAnimeList(){
        $this->downloadFiles('anime');
        $this->parseData('anime');
    }

    /**
     * Generate the manga list
     */
    public function generateMangaList(){
        $this->downloadFiles('manga');
        $this->parseData('manga');
    }

    private function calculateGlobalAvg(&$series){
        $score = $count = 0;
        foreach($series AS $show){
            if ($show->avgScore > 0){
                $score += $show->avgScore;
                $count++;
            }
        }
        $this->globalAvgScore = ($count > 0) ? ($score / $count) : 0;
    }

    private function calculateSeriesAvg(&$series){
        foreach ($series AS &$show){
            $score = $count = 0;
            foreach($show->watchedBy AS $userScore){
                if ($userScore->score > 0){
                    $score += $userScore->score;
                    $count++;
                }
            }
            $show->avgScore = ($count > 0) ? ($score / $count) : 0;
        }
    }

    private function calculateWeightedScore(&$series){
        foreach($series AS &$show){
            if ($show->avgScore > 0){

                # count only entries with a score
                $scoreCount = 0;
                foreach($show->watchedBy AS $user){
                    if ($user->score > 0) $scoreCount++;
                }

                $v = $scoreCount;               # number of votes
                $s = $show->avgScore;           # series avg
                $c = $this->globalAvgScore;     # global avg
                $m = 1;                         # min votes

                # http://myanimelist.net/info.php?go=topanime
                $show->avgWeighted = ($v / ($v + $m)) * $s + ($m / ($v + $m)) * $c;
            }
        }
    }

    private function checkDirectoryPermissions($directory){
        return (is_writable($directory) && is_readable($directory) && is_dir($directory));
    }

    private function deleteFilesInDirectory($directory, $prefix = '', $suffix = ''){
        if ($this->checkDirectoryPermissions($directory)){
            $files = glob($directory . $prefix . '*' . $suffix);
            foreach($files as $file){
                if(is_file($file)){
                    unlink($file);
                }
            }
        }
    }

    /**
     * Downloads the xml containing the manga / anime informations from myanimelist. Because of
     * MAL's DDOS protection curl, file_get_contents, ect won't work.
     * @param string $type anime or manga
     */
    private function downloadFiles($type){
        if ($type !== 'anime' && $type !== 'manga'){
            return;
        }

        $this->deleteFilesInDirectory($this->tempDir);

        # for each user
        for ($i = 0, $k = sizeof($this->userNames), $cmd = ''; $i < $k; $cmd = ''){
            # parallel downloads
            for ($l = 0; $l < $this->maxParallelDownloads && $i < $k; $l++, $i++){
                $cmd .= (empty($cmd) ? '' : ' & ') .
                        ('lynx -source "' . self::MAL_XML_LINK . $this->userNames[$i] . self::MAL_XML_LINK_PARAMS . $type . '" ') .
                        ('> "' . $this->tempDir . uniqid() . '.xml" ');
            }
            exec($cmd);
            // Wait to avoid "too many requests" error
            sleep(2);
        }

        # wait for the file system to write the xmls completely
        # this caused a problem on my slow test server (raspi gen1)
        sleep($this->waitSecondsForFileSystem);
    }

    private function generateDetails(&$series, $type){
        $outerTr = HtmlTag::createElement('tr')
            ->set('class', self::CSS_PREFIX . 'main-table-details');
        $outerTd = HtmlTag::createElement('td')
            ->set('colspan', 4);
        $wrapper = HtmlTag::createElement('div')
            ->set('class', self::CSS_PREFIX . 'detail-wrapper');
        $image   = HtmlTag::createElement('img')
            ->set('class', self::CSS_PREFIX . 'detail-image')
            ->set('alt', $series->name)
            ->set('src', $series->image);
        $imageA  = HtmlTag::createElement('a')
            ->set('target', '_blank')
            ->set('title', $series->name)
            ->set('href', ($type === 'anime' ? self::MAL_ANIME_LINK : self::MAL_MANGA_LINK) . $series->id);
        $table = HtmlTag::createElement('table')
            ->set('class', self::CSS_PREFIX . 'detail-table');

        $outerTr->addElement($outerTd);
        $outerTd->addElement($wrapper);
        $wrapper->addElement($imageA);
        $wrapper->addElement($table);
        $imageA->addElement($image);

        $table->addElement($this->generateRow(['User', 'Status', ($type === 'anime' ? 'Seen' : 'Read'), 'Diff', 'Score'], true));

        foreach ($series->watchedBy AS $user){
            $diff       = $user->score - $series->avgWeighted;
            $diffClass  = ($user->score == 0 || $diff == 0 ? 'diff-equal' : ($diff < 0 ? 'diff-negative' : 'diff-positive'));
            $diffText   = $user->score == 0               ? '-'          : number_format(abs($diff), 2);

            $table->addElement($this->generateRow([
                $user->user->name,                                      # Name
                $this->getStatusMapping($user->status, $type),          # Status
                $this->getEpisodeChapterVolume($user, $series),         # Episodes / Volumes / Chapters
                HtmlTag::createElement('span')                          # Diff
                    ->attr('class', self::CSS_PREFIX . $diffClass)
                    ->text($diffText),
                $user->score == 0 ? '-' : $user->score                  # Score
            ]));
        }

        return $outerTr;
    }

    private function generatePagination($currPage, $maxPage, $type){
        $pagination = [
            'element'   => '',
            'filename'  => $type . '.html'
        ];

        # only 1 page -> pagination is not needed
        if ($maxPage === 1) return $pagination;

        $links = [];

        for($i = 0; $i < $maxPage; $i++){
            $links[$i] = [
                'href' => $type . (!$i ? '' : '_' . ($i + 1)) . '.html',
                'text' => ($i + 1)
            ];
        }

        $wrapper = HtmlTag::createElement('div')
            ->set('id', self::CSS_PREFIX . 'pagination-wrapper');
        $center  = HtmlTag::createElement('div')
            ->set('id', self::CSS_PREFIX . 'pagination-center');

        foreach($links AS $link){
            $pageLink = HtmlTag::createElement('a')
                ->set('class', self::CSS_PREFIX . 'pagination-entry' . ($currPage === $link['text'] ? ' active' : ''))
                ->set('href', $link['href'])
                ->text($link['text']);
            $center->addElement($pageLink);
        }

        $wrapper->addElement($center);
        $pagination['element']  = $wrapper;
        $pagination['filename'] = $links[$currPage - 1]['href'];

        return $pagination;
    }

    private function generateRow(array $data, $head=false, $trClass = ''){
        $row = HtmlTag::createElement('tr')
            ->set('class', $trClass);

        foreach($data AS $value){
            $cell = HtmlTag::createElement($head ? 'th' : 'td');
            if  (is_object($value)) $cell->addElement($value);
            else                    $cell->text($value);
            $row->addElement($cell);
        }

        return $row;
    }

    private function generateUserInfo(&$users){
        $table = HtmlTag::createElement('table')
            ->set('id', self::CSS_PREFIX . 'info-table');
        $table->addElement($this->generateRow(['Name', 'Series', 'Average Score', 'Diff', 'Profile'], true, self::CSS_PREFIX . 'info-table-head'));

        foreach($users AS $user){
            $avgText    = $user->avgScore > 0 ? number_format($user->avgScore, 2) : '';
            $diff       = $user->avgScore - $this->globalAvgScore;
            $diffClass  = self::CSS_PREFIX . ($diff == 0 ? 'diff-equal' : ($diff < 0 ? 'diff-negative' : 'diff-positive'));
            $diffText   = $user->avgScore > 0 ? number_format(abs($diff), 2) : '-';

            $table->addElement($this->generateRow([
                $user->name,                                                # Name
                $user->entryCount,                                          # Series
                $avgText,                                                   # AVG Score
                HtmlTag::createElement('span')                              # Diff
                    ->set('class', $diffClass)
                    ->text($diffText),
                HtmlTag::createElement('a')                                 # Profile link
                    ->set('title', 'MAL Profile')
                    ->set('href', self::MAL_PROFILE_LINK . $user->name)
                    ->set('target', '_blank')
                    ->text('animelist.net')
            ], false, self::CSS_PREFIX . 'info-table-data'));
        }

        return $table;
    }

    /**
     * Anime have episodes, mangas chapter or volumes. Return the correct one.
     * @param object $user
     * @param object $series
     * @return string
     */
    private function getEpisodeChapterVolume($user, $series){
        $returnVal = '-';

        if ($user->episodes > 0){
            $returnVal = $user->episodes . ' / ' . ($series->episodes > 0 ? $series->episodes : '-') . ' E';
        }
        else if ($user->chapters > 0){
            $returnVal = $user->chapters . ' / ' . ($series->chapters > 0 ? $series->chapters : '-') . ' C';
        }
        else if ($user->volumes > 0){
            $returnVal = $user->volumes  . ' / ' . ($series->volumes  > 0 ? $series->volumes  : '-') . ' V';
        }

        return $returnVal;
    }

    private function getStatusMapping($value, $type){
        $mapping = '';

        switch($value){
            case 1: $mapping = ($type === 'anime' ? 'Watching' : 'Reading');
                break;
            case 2: $mapping = 'Completed';
                break;
            case 3: $mapping = 'On-Hold';
                break;
            case 4: $mapping = 'Dropped';
                break;
            case 6: $mapping = ($type === 'anime' ? 'Plan to Watch' : 'Plan to Read');
                break;
        }

        return $mapping;
    }

    private function parseData($type){
        if ($type !== 'anime' && $type !== 'manga'){
            return;
        }

        $user   = [];
        $series = [];
        $files  = glob($this->tempDir . '*');

        foreach($files AS $xmlPath){
            $parser         = simplexml_load_file($xmlPath);
            $seriesElements = $type === 'anime' ? $parser->anime : $parser->manga;
            $userObject     = (object) [
                'name'          => (string)  $parser->myinfo->user_name,
                'entryCount'    => 0,
                'avgScore'      => 0.0,
                'id'            => (integer) $parser->myinfo->user_id
            ];

            foreach($seriesElements AS $show){

                $seriesId = (integer) ($type === 'anime' ? $show->series_animedb_id : $show->series_mangadb_id);

                if (empty($series[$seriesId])){
                    $series[$seriesId] = (object) [
                        'name'        => (string)  ($show->series_title),
                        'synonyms'    => (string)  ($show->series_synonyms),
                        'id'          =>           ($seriesId),
                        'image'       => (string)  ($show->series_image),
                        'episodes'    => (integer) ($type === 'anime' ? $show->series_episodes : 0),
                        'chapters'    => (integer) ($type === 'anime' ? 0 : $show->series_chapters),
                        'volumes'     => (integer) ($type === 'anime' ? 0 : $show->series_volumes),
                        'type'        => (string)  ($type),
                        'avgScore'    => (double)  (0.0),
                        'avgWeighted' => (double)  (0.0),
                        'watchedBy'   => []
                    ];
                }

                $series[$seriesId]->watchedBy[] = (object) [
                    'user' => $userObject,
                    'episodes'  => (integer) ($type === 'anime' ? $show->my_watched_episodes : 0),
                    'chapters'  => (integer) ($type === 'anime' ? 0 : $show->my_read_chapters),
                    'volumes'   => (integer) ($type === 'anime' ? 0 : $show->my_read_volumes),
                    'score'     => (integer) ($show->my_score),
                    'status'    => (integer) ($show->my_status)
                ];

                if ((integer) $show->my_score > 0){
                    $userObject->entryCount++;
                    $userObject->avgScore += (integer) $show->my_score;
                }
            }

            $userObject->avgScore = ($userObject->entryCount > 0) ? ($userObject->avgScore / $userObject->entryCount) : 0;
            $user[$userObject->id] = $userObject;
        }

        $this->calculateSeriesAvg($series);
        $this->calculateGlobalAvg($series);
        $this->calculateWeightedScore($series);
        $this->sortData($series, $user);
        $this->deleteFilesInDirectory($this->outputDir, $type, '.html');
        $this->saveList($series, $user, $type);
    }

    private function saveList(&$series, &$user, $type){
        reset($series);
        $seriesData     = current($series);
        $templateData   = file_get_contents($this->templateFile);
        $userInfo       = $this->generateUserInfo($user);

        for ($currPage    = 1,
             $seriesCount = sizeof($series),
             $pages       = ceil($seriesCount / $this->maxEntriesPerPage); $currPage <= $pages; $currPage++){

            $mainTable =  HtmlTag::createElement('table')->set('id', self::CSS_PREFIX . 'main-table');
            $mainTable -> addElement($this->generateRow(['Rank', 'Name', 'Score', 'Weighted Score'], true, self::CSS_PREFIX . 'main-table-head'));

            for ($i = (($currPage - 1) * $this->maxEntriesPerPage),
                 $k = $i + $this->maxEntriesPerPage; $i < $k && $i < $seriesCount; $i++){

                $mainTable->addElement($this->generateRow([
                    ($i + 1),                                // Rank
                    htmlspecialchars($seriesData->name),    // Name
                    $seriesData->avgScore    > 0 ? number_format($seriesData->avgScore, 2)    : '-',  // Score
                    $seriesData->avgWeighted > 0 ? number_format($seriesData->avgWeighted, 2) : '-'   // WeightedScore
                ], false, self::CSS_PREFIX . 'main-table-data'));

                $mainTable->addElement($this->generateDetails($seriesData, $type));
                $seriesData = next($series);
            }

            $pagination = $this->generatePagination($currPage, $pages, $type);
            file_put_contents($this->outputDir . $pagination['filename'], str_replace(
                ['{$TYPE}',         '{$LIST}',      '{$PAGINATION}',          '{$USER}'],
                [ucfirst($type),    $mainTable,     $pagination['element'],   $userInfo],
                $templateData
            ));
        }
    }

    private function sortData(&$series, &$user){
        usort($series, array($this, 'sortSeriesByWeightedScore'));
        usort($user, array($this, 'sortUserSeriesCount'));
        foreach($series AS &$show){
            usort($show->watchedBy, array($this, 'sortSeenByUserByScore'));
        }
    }

    private function sortSeenByUserByScore($a, $b){
        if ($a->score === $b->score) return 0;
        return ($a->score < $b->score) ? 1 : -1;
    }

     private function sortSeriesByWeightedScore($a, $b){
        if ($a->avgWeighted === $b->avgWeighted) return 0;
        return ($a->avgWeighted < $b->avgWeighted) ? 1 : -1;
    }

    private function sortUserSeriesCount($a, $b){
        if ($a->entryCount === $b->entryCount) return 0;
        return ($a->entryCount < $b->entryCount) ? 1 : -1;
    }
}