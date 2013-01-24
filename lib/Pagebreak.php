<?php
/**
 * @author Lukin <my@lukin.cn>
 * @version $Id$
 * @datetime 2011-11-05 22:09
 */

class Pagebreak {
    public $total;
    public $pages;
    public $page;
    public $size;
    public $length;
    
    public function __construct($size = null, $page = null) {
        $this->size = $size ? $size : 10;
        if ($page === null) {
            $this->page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
        } else {
            $this->page = $page;
        }
        $this->page = $this->page < 1 ? $page : $this->page;
        $this->size = $this->size < 1 ? $size : $this->size;
    }
    /**
     * 执行查询
     *
     * @param string $sql
     * @return mixed
     */
    public function query($sql) {
        $db   = get_conn();
        $sql  = rtrim($sql, ';');
        $csql = preg_replace_callback(
            '/SELECT (.+) FROM/iU',
            create_function('$matches', '
                if (preg_match(\'/distinct\s*\([^\)]+\)/i\',$matches[1], $match)) {
                    $field = $match[0];
                } else {
                    $field = "*";
                }
                return sprintf("select count(%s) from", $field);
            '),
            $sql, 1
        );
        $csql = preg_replace('/\sorder\s+by.+$/i', '', $csql, 1);

        $sql .= sprintf(' limit %d, %d;', ($this->page - 1) * $this->size, $this->size);
        // 执行结果
        $result = $db->query($sql);
        // 总记录数
        $this->total = $db->result($csql);
        $this->pages = ceil($this->total / $this->size);
        $this->pages = ((int) $this->pages == 0) ? 1 : $this->pages;
        if ((int) $this->page < (int) $this->pages) {
            $this->length = $this->size;
        } elseif ((int) $this->page == (int) $this->pages) {
            $this->length = $this->total - (($this->pages - 1) * $this->size);
        } else {
            $this->length = 0;
        }
        if ($this->total == 0 || $this->length == 0) $result = false;
        return $result;
    }

    /**
     * 取得数据集
     *
     * @param  $resource
     * @param int $type
     * @return array|null
     */
    public function fetch($resource, $type = 1) {
        if (is_resource($resource) || is_object($resource)) {
            return get_conn()->fetch($resource, $type);
        }
        return null;
    }
    /**
     * 分页信息
     *
     * @return array
     */
    public function info() {
        return array(
            'page' => $this->page,
            'size' => $this->size,
            'total' => $this->total,
            'pages' => $this->pages,
            'length' => $this->length,
        );
    }

    /**
     * 清理分页信息
     *
     * @return void
     */
    public function close() {
        $this->total = $this->pages = $this->length = 0;
    }
    /**
     * 分页函数
     *
     * @param string $url   url中必须包含$特殊字符，用来代替页数
     * @param string $mode  首页丢弃模式
     * @return string
     */
    public function lists($url, $mode = '$') {
        $this->page = abs(intval($this->page));
        $this->page = $this->page < 1 ? 1 : $this->page;
        $this->pages = abs(intval($this->pages));
        $this->length = abs(intval($this->length));
        
        if (strpos($url, '%24') !==false)
            $url = str_replace('%24', '$', $url);
        if (strpos($url, '$')===false)
            return '';
        
        $start = instr($mode, '!$,!_$') ? '' : 1;

        $html = '<div class="page-wrap">';
        $html.= '<span class="info">'.sprintf(__('共：%s 条 / %s 页'), $this->total, $this->pages).'</span>';
        // 首页
        if ($this->pages < 2 || $this->page < 2) {
            $html.= '<span class="start">'.__('首页').'</span>';
        } else {
            if ($mode == '!_$') {
                $html.= '<a class="start" href="' . str_replace('_$', $start, $url) . '">'.__('首页').'</a>';
            } else {
                $html.= '<a class="start" href="' . str_replace('$', $start, $url) . '">'.__('首页').'</a>';
            }

        }
        // 上一页
        if ($this->page < 2) {
            $html.=     '<span class="prev">'.__('上一页').'</span>';
        } elseif ($this->page > 2) {
            $html.= '<a class="prev" href="' . str_replace('$', $this->page - 1, $url) . '">'.__('上一页').'</a>';
        } elseif ($this->page == 2) {
            if ($mode == '!_$') {
                $html .= '<a class="prev" href="' . str_replace('_$', $start, $url) . '">'.__('上一页').'</a>';
            } else {
                $html .= '<a class="prev" href="' . str_replace('$', $start, $url) . '">'.__('上一页').'</a>';
            }
        }

        $before = $this->page - 3;
        $after  = $this->page < 4 ? (6 - $this->page) : 2;
        $length = $this->page + $after;
        for ($i = $before; $i <= $length; $i++) {
            if ($i >= 1 && $i <= $this->pages) {
                if ((int) $i == (int) $this->page) {
                    $html .= '<span class="page">' . $i . '</span>';
                } else {
                    if ($i == 1) {
                        if ($mode == '!_$') {
                            $html .= '<a class="page" href="' . str_replace('_$', $start, $url) . '">' . $i . '</a>';
                        } else {
                            $html .= '<a class="page" href="' . str_replace('$', $start, $url) . '">' . $i . '</a>';
                        }
                    } else {
                        $html .= '<a class="page" href="' . str_replace('$', $i, $url) . '">' . $i . '</a>';
                    }
                }
            }
        }

        if ($this->page < ($this->pages - $after)) {
            $html .= '<span class="ellipsis">&#8230;</span>';
        }

        // 下一页
        if ($this->page < $this->pages) {
            $html.= '<a class="next" href="' . str_replace('$', $this->page + 1, $url) . '">'.__('下一页').'</a>';
        } else {
            $html.= '<span class="next">'.__('下一页').'</span>';
        }
        // 末页
        if ($this->page < $this->pages) {
            $html.= '<a class="end" href="' . str_replace('$', $this->pages, $url) . '">'.__('尾页').'</a>';
        } else {
            $html.= '<span class="end">'.__('尾页').'</span>';
        }
        $html.= '</div>';
        return $html;
    }
}
