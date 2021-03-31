<?php

namespace Wengg\LaravelHelpers\Traits;

/**
 * 多层级模型复用，适用于laravel
 * @author mosquito <zwj1206_hi@163.com> 2020-10-28
 */
trait MultiLevelModelTrait
{
    //索引对照
    protected static $multiLevelMap = [];

    /**
     * 设置列信息
     * @param string $id
     * @param string $name
     * @param string $parent_id
     * @param string $parent_path
     * @return void
     * @author mosquito <zwj1206_hi@163.com> 2021-04-22
     */
    public static function setColumn(string $id = 'id', string $name = 'name', string $parent_id = 'parent_id', string $parent_path = 'parent_path')
    {
        static::$multiLevelMap = [
            'id'          => $id,
            'name'        => $name,
            'parent_id'   => $parent_id,
            'parent_path' => $parent_path,
        ];
    }

    /**
     * 获取id列
     * @return string
     * @author mosquito <zwj1206_hi@163.com> 2021-04-22
     */
    public static function getIdColumn()
    {
        return static::$multiLevelMap['id'] ?? 'id';
    }

    /**
     * 获取name列
     * @return string
     * @author mosquito <zwj1206_hi@163.com> 2021-04-22
     */
    public static function getNameColumn()
    {
        return static::$multiLevelMap['name'] ?? 'name';
    }

    /**
     * 获取parent_id列
     * @return string
     * @author mosquito <zwj1206_hi@163.com> 2021-04-22
     */
    public static function getParentIdColumn()
    {
        return static::$multiLevelMap['parent_id'] ?? 'parent_id';
    }

    /**
     * 获取parent_path列
     * @return string
     * @author mosquito <zwj1206_hi@163.com> 2021-04-22
     */
    public static function getParentPathColumn()
    {
        return static::$multiLevelMap['parent_path'] ?? 'parent_path';
    }

    /**
     * 父级关联
     * @return void
     * @author mosquito <zwj1206_hi@163.com> 2020-10-28
     */
    public function parent()
    {
        return $this->belongsTo(static::class, static::getParentIdColumn(), static::getIdColumn());
    }

    /**
     * 子级关联
     * @return void
     * @author mosquito <zwj1206_hi@163.com> 2020-10-28
     */
    public function children()
    {
        return $this->hasMany(static::class, static::getParentIdColumn(), static::getIdColumn());
    }

    /**
     * 获取父级列表
     * @param self|int $item
     * @param bool $self
     * @return \Illuminate\Support\Collection
     * @author mosquito <zwj1206_hi@163.com> 2020-10-28
     */
    public static function getParentList($item, bool $self = true)
    {
        if ($item instanceof static ) {
            //
        } else {
            $item = intval($item);
            $item = static::find($item);
        }
        if (!$item) {
            return collect();
        }
        //
        $parents  = collect();
        $path_arr = json_decode($item->{static::getParentPathColumn()}, true) ?: [];
        if ($path_arr) {
            $parents = static::find($path_arr);
        }
        return $self ? $parents->push($item) : $parents;
    }

    /**
     * 获取子级列表
     * @param self|int $item
     * @param bool $self
     * @return \Illuminate\Support\Collection
     * @author mosquito <zwj1206_hi@163.com> 2020-10-28
     */
    public static function getChildrenList($item, bool $self = true)
    {
        if ($item instanceof static ) {
            //
        } else {
            $item = intval($item);
            $item = static::find($item);
        }
        if (!$item) {
            return collect();
        }
        //
        $children = static::whereJsonContains(static::getParentPathColumn(), $item->{static::getIdColumn()})->get();
        return $self ? $children->prepend($item) : $children;
    }

    /**
     * 更新当前项的父级信息
     * @param self|int $item
     * @param self|int $parent
     * @return void
     * @author mosquito <zwj1206_hi@163.com> 2020-10-28
     */
    public static function updateItemParent($item, $parent = null)
    {
        if ($item instanceof static ) {
            //
        } else {
            $item = intval($item);
            $item = static::find($item);
        }
        if (!$item) {
            throw new \Exception('获取当前项失败');
        }

        //
        $parent_id = 0;
        if ($parent instanceof static ) {
            //
            $parent_id = $parent->{static::getIdColumn()};
        } elseif (is_null($parent)) {
            $parent_id = intval($item->{static::getParentIdColumn()});
        } elseif (is_numeric($parent)) {
            $parent_id = intval($parent);
        } else {
            throw new \Exception('父级信息错误');
        }
        if (!$parent && $parent_id > 0) {
            $parent = static::find($parent_id);
            if (!$parent) {
                throw new \Exception('父级信息错误');
            }
        }

        //更新当前项
        $old_path_arr = json_decode($item->{static::getParentPathColumn()}, true) ?: [];
        if ($parent_id == 0) {
            $item->{static::getParentIdColumn()}   = 0;
            $item->{static::getParentPathColumn()} = null;
        } else {
            $path_arr = json_decode($parent->{static::getParentPathColumn()}, true) ?: [];
            array_push($path_arr, $parent->{static::getIdColumn()});
            if (in_array($item->{static::getIdColumn()}, $path_arr)) {
                throw new \Exception('父级信息不能是当前项的子级');
            }
            $item->{static::getParentIdColumn()}   = $parent->{static::getIdColumn()};
            $item->{static::getParentPathColumn()} = json_encode($path_arr);
        }
        //
        if ($item->isDirty()) {
            $temp = static::where(static::getIdColumn(), $item->{static::getIdColumn()})->update($item->getDirty());
            if ($temp === false) {
                throw new \Exception('更新当前项失败');
            }
        }

        //更新当前项子级
        $new_path_arr = json_decode($item->{static::getParentPathColumn()}, true) ?: [];
        if ($old_path_arr != $new_path_arr) {
            $new_path_sql = static::getParentPathColumn();
            if ($old_path_arr) {
                $json_remove_arr = array_pad([], count($old_path_arr), '"$[0]"');
                $new_path_sql    = 'JSON_REMOVE(`' . static::getParentPathColumn() . '`, ' . implode(',', $json_remove_arr) . ')';
            }
            if ($new_path_arr) {
                $new_path_arr = array_reverse($new_path_arr);
                $new_path_sql = 'JSON_ARRAY_INSERT(' . $new_path_sql;
                foreach ($new_path_arr as $cpath) {
                    $new_path_sql .= ', "$[0]", ' . $cpath;
                }
                $new_path_sql .= ')';
            }
            //
            $temp = static::whereJsonContains(static::getParentPathColumn(), $item->{static::getIdColumn()})
                ->update([
                    static::getParentPathColumn() => \DB::raw($new_path_sql),
                ]);
            if ($temp === false) {
                throw new \Exception('更新当前项子级失败');
            }
        }
    }

    /**
     * 获取全名称
     * @param self|int $item
     * @param bool $self
     * @param string $glue
     * @return string
     * @author mosquito <zwj1206_hi@163.com> 2020-10-28
     */
    public static function getItemFullName($item, bool $self = true, string $glue = '/')
    {
        return implode($glue, array_column(static::getParentList($item, $self)->toArray(), static::getNameColumn()));
    }
}
