<?php

if (!function_exists('laravel_batch_update')) {
    /**
     * laravel数据库单表批量更新，适用于laravel
     * @param string $table
     * @param array $list_data
     * @param int $chunk_size
     * @return int
     * @author mosquito <zwj1206_hi@163.com> 2020-10-21
     */
    function laravel_batch_update(string $table, array $list_data, int $chunk_size = 200)
    {
        if (count($list_data) < 1) {
            throw new \Exception('更新数量不能小于1');
        }
        if ($chunk_size < 1) {
            throw new \Exception('分切数量不能小于1');
        }
        $chunk_list = array_chunk($list_data, $chunk_size);
        $count      = 0;
        foreach ($chunk_list as $list_item) {
            $first_row  = current($list_item);
            $update_col = array_keys($first_row);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            $reference_col = isset($first_row['id']) ? 'id' : current($update_col);
            unset($update_col[0]);
            // 拼接sql语句
            $update_sql = 'UPDATE ' . $table . ' SET ';
            $sets       = [];
            $bindings   = [];
            foreach ($update_col as $u_col) {
                $set_sql = '`' . $u_col . '` = CASE ';
                foreach ($list_item as $item) {
                    $set_sql .= 'WHEN `' . $reference_col . '` = ? THEN ';
                    $bindings[] = $item[$reference_col];
                    if ($item[$u_col] instanceof \Illuminate\Database\Query\Expression) {
                        $set_sql .= $item[$u_col]->getValue() . ' ';
                    } else {
                        $set_sql .= '? ';
                        $bindings[] = $item[$u_col];
                    }
                }
                $set_sql .= 'ELSE `' . $u_col . '` END ';
                $sets[] = $set_sql;
            }
            $update_sql .= implode(', ', $sets);
            $where_in   = collect($list_item)->pluck($reference_col)->values()->all();
            $bindings   = array_merge($bindings, $where_in);
            $where_in   = rtrim(str_repeat('?,', count($where_in)), ',');
            $update_sql = rtrim($update_sql, ', ') . ' WHERE `' . $reference_col . '` IN (' . $where_in . ')';
            //
            $count += \DB::update($update_sql, $bindings);
        }
        return $count;
    }
}

if (!function_exists('laravel_paginate')) {
    /**
     * laravel分页查询兼容group，如果total不为null则使用虚拟查询即不查询总数，适用于laravel
     * @param \Illuminate\Database\Query\Builder $builder
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @param int|null $total
     * @return \Illuminate\Pagination\LengthAwarePaginator
     * @author mosquito <zwj1206_hi@163.com> 2020-10-21
     */
    function laravel_paginate(\Illuminate\Database\Query\Builder $builder, int $perPage = 15, array $columns = ['*'], string $pageName = 'page', int $page = null, int $total = null)
    {
        if (!is_null($total)) {
            $page    = $page ?: \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
            $results = $builder->forPage($page, $perPage)->get($columns);
        } else {
            if (!$builder->groups) {
                return $builder->paginate($perPage, $columns, $pageName, $page);
            }
            $page      = $page ?: \Illuminate\Pagination\Paginator::resolveCurrentPage($pageName);
            $c_builder = clone $builder;
            if (!$c_builder->columns) {
                $c_builder->select($columns);
            }
            $sql = $c_builder->cloneWithout(['orders', 'limit', 'offset'])
                ->cloneWithoutBindings(['select', 'order'])->toSql();
            $total = \DB::Connection($c_builder->getConnection()->getName())
                ->select('select count(1) as counts from (' . $sql . ') as t',
                    $c_builder->getBindings())[0]->counts;
            $results = $total > 0 ? $builder->forPage($page, $perPage)->get($columns) : collect();
        }
        return new \Illuminate\Pagination\LengthAwarePaginator($results, $total, $perPage, $page, [
            'path'     => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }
}

if (!function_exists('res_url')) {
    /**
     * 获取资源地址，兼容三方包oss问题，适用于laravel
     * @param string $str
     * @param string $disk
     * @return string
     * @author mosquito <zwj1206_hi@163.com> 2020-10-23
     */
    function res_url(string $str = null, string $disk = null)
    {
        if (filter_var($str, FILTER_VALIDATE_URL)) {
            return $str;
        }
        $result = '';
        $disk   = $disk ?? config('filesystems.default');
        if ($disk == 'oss' && !\Storage::disk($disk)->exists($str)) {
            $oss_conf = config('filesystems.disks.oss');
            $url      = $oss_conf['ssl'] ? 'https://' : 'http://';
            if ($oss_conf['isCName']) {
                $url .= $oss_conf['cdnDomain'];
            } else {
                $url .= $oss_conf['bucket'] . '.' . $oss_conf['endpoint'];
            }
            $result = implode('/', [rtrim($url, '/'), ltrim($str, '/')]);
        } else {
            $result = \Storage::disk($disk)->url($str);
        }
        return rtrim($result, '/');
    }
}
