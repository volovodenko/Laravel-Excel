<?php

namespace Maatwebsite\Excel;

use ArrayAccess;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Columns\Column;
use Maatwebsite\Excel\Columns\ColumnCollection;
use Maatwebsite\Excel\Columns\EmptyCell;
use Maatwebsite\Excel\Columns\Split;
use PhpOffice\PhpSpreadsheet\Worksheet\Row as SpreadsheetRow;

class Row implements ArrayAccess
{
    use DelegatedMacroable;

    /**
     * @var array
     */
    protected $headingRow = [];

    /**
     * @var \Closure
     */
    protected $preparationCallback;

    /**
     * @var SpreadsheetRow
     */
    protected $row;

    /**
     * @var array|null
     */
    protected $rowCache;

    /**
     * @param SpreadsheetRow $row
     * @param array          $headingRow
     */
    public function __construct(SpreadsheetRow $row, array $headingRow = [])
    {
        $this->row        = $row;
        $this->headingRow = $headingRow;
    }

    /**
     * @return SpreadsheetRow
     */
    public function getDelegate(): SpreadsheetRow
    {
        return $this->row;
    }

    public function toArrayWithColumns(ColumnCollection $columns): array
    {
        $row = [];
        foreach ($this->row->getCellIterator($columns->start() ?: 'A', $columns->end()) as $cell) {
            /** @var Column $column */
            foreach (Arr::wrap($columns->get($cell->getColumn())) as $column) {
                // Skip columns that were not requested.
                if (!$column) {
                    continue;
                }

                foreach ($column->columns() as $col) {
                    if ($col->title()) {
                        $row[$col->title()] = $col->read($cell);
                    }
                }
            }
        }

        return $row;
    }

    /**
     * @param null        $nullValue
     * @param bool        $calculateFormulas
     * @param bool        $formatData
     * @param string|null $endColumn
     *
     * @return Collection
     */
    public function toCollection($nullValue = null, $calculateFormulas = false, $formatData = true, ?string $endColumn = null): Collection
    {
        return new Collection($this->toArray($nullValue, $calculateFormulas, $formatData, $endColumn));
    }

    /**
     * @param null        $nullValue
     * @param bool        $calculateFormulas
     * @param bool        $formatData
     * @param string|null $endColumn
     *
     * @return array
     */
    public function toArray($nullValue = null, $calculateFormulas = false, $formatData = true, ?string $endColumn = null)
    {
        if (is_array($this->rowCache)) {
            return $this->rowCache;
        }

        $cells = [];

        $i = 0;
        foreach ($this->row->getCellIterator('A', $endColumn) as $cell) {
            $value = (new Cell($cell))->getValue($nullValue, $calculateFormulas, $formatData);

            if (isset($this->headingRow[$i])) {
                $cells[$this->headingRow[$i]] = $value;
            } else {
                $cells[] = $value;
            }

            $i++;
        }

        if (isset($this->preparationCallback)) {
            $cells = ($this->preparationCallback)($cells, $this->row->getRowIndex());
        }

        $this->rowCache = $cells;

        return $cells;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count(array_filter($this->toArray(null, false, false))) === 0;
    }

    /**
     * @return int
     */
    public function getIndex(): int
    {
        return $this->row->getRowIndex();
    }

    public function offsetExists($offset)
    {
        return isset(($this->toArray())[$offset]);
    }

    public function offsetGet($offset)
    {
        return ($this->toArray())[$offset];
    }

    public function offsetSet($offset, $value)
    {
        //
    }

    public function offsetUnset($offset)
    {
        //
    }

    /**
     * @param \Closure $preparationCallback
     *
     * @internal
     */
    public function setPreparationCallback(Closure $preparationCallback = null)
    {
        $this->preparationCallback = $preparationCallback;
    }
}
