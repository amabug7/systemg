<?php

namespace app\admin\controller\platform;

use app\common\controller\Backend;
use think\Db;

class Material extends Backend
{
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\PlatformMaterial');
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    public function index()
    {
        $this->syncAttachmentToMaterial();
        return parent::index();
    }

    protected function syncAttachmentToMaterial()
    {
        $rows = Db::name('attachment')
            ->field('id,filename,url,mimetype,filesize,uploadtime,createtime')
            ->where('url', '<>', '')
            ->order('id desc')
            ->limit(2000)
            ->select();
        if (!$rows) {
            return;
        }

        $rows = collection($rows)->toArray();
        $urls = array_values(array_unique(array_filter(array_column($rows, 'url'))));
        if (!$urls) {
            return;
        }

        $existing = Db::name('platform_material')->where('url', 'in', $urls)->column('url');
        $existingMap = array_flip($existing ?: []);
        $now = time();
        $insertRows = [];
        foreach ($rows as $item) {
            $url = trim((string)($item['url'] ?? ''));
            if ($url === '' || isset($existingMap[$url])) {
                continue;
            }
            $mime = trim((string)($item['mimetype'] ?? ''));
            $type = 'file';
            if ($mime !== '' && strpos($mime, '/') !== false) {
                $type = explode('/', $mime)[0];
            }
            $filename = trim((string)($item['filename'] ?? ''));
            $path = parse_url($url, PHP_URL_PATH);
            $fallback = $path ? basename($path) : $url;
            $name = $filename !== '' ? $filename : $fallback;
            $created = (int)($item['uploadtime'] ?? 0);
            if ($created <= 0) {
                $created = (int)($item['createtime'] ?? 0);
            }
            if ($created <= 0) {
                $created = $now;
            }
            $insertRows[] = [
                'name' => mb_substr($name, 0, 120),
                'material_type' => mb_substr($type, 0, 30),
                'url' => $url,
                'thumb' => $type === 'image' ? $url : '',
                'size' => max(0, (int)($item['filesize'] ?? 0)),
                'mime' => mb_substr($mime, 0, 120),
                'tags' => '自动同步,附件库',
                'status' => 'normal',
                'weigh' => 0,
                'createtime' => $created,
                'updatetime' => $now,
            ];
            $existingMap[$url] = 1;
        }

        if ($insertRows) {
            Db::name('platform_material')->insertAll($insertRows);
        }
    }
}
