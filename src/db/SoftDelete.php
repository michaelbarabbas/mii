<?php declare(strict_types=1);

namespace mii\db;

/**
 *
 *  Table must have a column int(11) 'deleted' default NULL
 *
 */
trait SoftDelete
{
    protected static function prepareQuery(SelectQuery $query): SelectQuery
    {
        return parent::prepareQuery($query->where('deleted', 'is', null));
    }

    public static function findDeleted(): SelectQuery
    {
        // TODO:: order_by
        return (new SelectQuery(static::class))
            ->where('deleted', 'is not', null);
    }

    public function restore()
    {
        return static::query()
            ->update()
            ->set(['deleted' => null])
            ->where('id', '=', $this->id)
            ->execute();
    }


    public function delete(): void
    {
        if (!$this->loaded()) {
            throw new \Exception('Cannot delete a non-loaded model ' . \get_class($this) . '!', [], []);
        }

        $this->__loaded = false;

        static::query()
            ->update()
            ->set(['deleted' => \time()])
            ->where('id', '=', $this->id)
            ->execute();
    }

    public function realDelete(): void
    {
        parent::delete();
    }
}
