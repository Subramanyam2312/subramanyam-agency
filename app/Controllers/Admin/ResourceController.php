<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sitemap;
use App\Core\Slugger;
use App\Core\Validator;

/**
 * Generic CRUD for the content modules.
 *
 * Every module in the brief has the same shape — list with search, filter and
 * pagination; create/edit form; delete with confirmation — so that shape lives here
 * once. A concrete module supplies its columns, validation rules and the mapping
 * from request to database row, and inherits everything else.
 *
 * The pay-off is that the interesting, module-specific logic is not buried in
 * hundreds of lines of identical pagination and redirect handling.
 */
abstract class ResourceController extends Controller
{
    /** @var class-string<\App\Core\Model> */
    protected string $model;

    /** Route prefix, e.g. '/admin/testimonials'. */
    protected string $route;

    /** View directory, e.g. 'admin/testimonials'. */
    protected string $views;

    protected string $singular = 'Item';

    protected string $plural = 'Items';

    protected string $order = 'id DESC';

    protected int $perPage = 20;

    /** Columns included in the search box's LIKE clause. */
    protected array $searchable = [];

    /** Enables the drag-to-reorder endpoint and hides the sort column from forms. */
    protected bool $sortable = false;

    /**
     * Module-specific form template. Left null, the shared form renders whatever
     * fields() returns — which is enough for most modules. Posts and services set
     * this because their forms need a bespoke two-column layout.
     */
    protected ?string $formView = null;

    /**
     * Set on modules whose records appear in sitemap.xml, so saving or deleting
     * one regenerates it. Cheaper and more reliable than a nightly-only rebuild:
     * a post published at 09:00 should be in the sitemap at 09:00.
     */
    protected bool $affectsSitemap = false;

    /** Column holding the slug, if this resource has one. */
    protected ?string $slugColumn = null;

    /** Column the slug is generated from when left blank. */
    protected string $slugSource = 'title';

    // ---------------------------------------------------------------- hooks

    /**
     * Validation rules. $id is null on create, the record id on update — pass it to
     * `unique:` so a record does not collide with itself.
     *
     * @return array<string,string>
     */
    abstract protected function rules(?int $id): array;

    /**
     * Maps the request onto database columns. Only what this returns is written.
     *
     * @return array<string,mixed>
     */
    abstract protected function payload(Request $request, ?int $id): array;

    /**
     * Column spec for the list view.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function columns(): array
    {
        return [];
    }

    /**
     * Dropdown filters for the list view: key => ['label' => ..., 'options' => [value => label]].
     *
     * @return array<string,array<string,mixed>>
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * Extra data the form view needs (select options, related rows).
     *
     * @param array<string,mixed>|null $record
     * @return array<string,mixed>
     */
    protected function formData(?array $record): array
    {
        return [];
    }

    /**
     * Field specs for the shared form template. Each entry is passed straight to
     * partials/field. Ignored by modules that set $formView.
     *
     * @param array<string,mixed>|null $record
     * @return array<int,array<string,mixed>>
     */
    protected function fields(?array $record): array
    {
        return [];
    }

    protected function afterSave(int $id, Request $request, bool $isNew): void
    {
    }

    /**
     * Return a message to refuse the delete, or null to allow it.
     *
     * @param array<string,mixed> $record
     */
    protected function beforeDelete(array $record): ?string
    {
        return null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{data:array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int}
     */
    protected function listQuery(array $filters, int $page): array
    {
        $model      = $this->model;
        $conditions = [];

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '' && $this->searchable !== []) {
            $quoted = Database::connection()->quote('%' . $search . '%');
            $parts  = [];

            foreach ($this->searchable as $column) {
                $parts[] = sprintf('`%s` LIKE %s', Database::identifier($column), $quoted);
            }

            $conditions['@raw'] = ['(' . implode(' OR ', $parts) . ')'];
        }

        foreach ($this->filters() as $key => $filter) {
            $value = $filters[$key] ?? '';

            if ($value !== '' && $value !== null) {
                $conditions[$key] = $value;
            }
        }

        return $model::paginate($conditions, $page, $this->perPage, $this->order);
    }

    // ---------------------------------------------------------------- actions

    public function index(Request $request): Response
    {
        $filters = ['search' => (string) $request->query('search', '')];

        foreach (array_keys($this->filters()) as $key) {
            $filters[$key] = (string) $request->query($key, '');
        }

        $result = $this->listQuery($filters, max(1, $request->integer('page', 1)));

        return $this->view('admin/shared/index', [
            'resource'   => $this->config(),
            'columns'    => $this->columns(),
            'filterSpec' => $this->filters(),
            'filters'    => $filters,
            'rows'       => $result['data'],
            'pagination' => $result,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view($this->formView ?? 'admin/shared/form', array_merge([
            'resource' => $this->config(),
            'record'   => null,
            'fields'   => $this->fields(null),
        ], $this->formData(null)));
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), $this->rules(null));

        if ($validator->fails()) {
            return $this->redirectWithErrors($this->route . '/create', $validator->errors(), $request->all());
        }

        $model = $this->model;
        $data  = $this->applySlug($this->payload($request, null), $request, null);

        $id = $model::create($data);

        $this->afterSave($id, $request, true);
        $this->refreshSitemap();

        ActivityLogger::log(strtolower($this->views) . '.created', $model::table(), $id);
        $this->success($this->singular . ' created.');

        return $this->redirect($this->route);
    }

    public function edit(Request $request): Response
    {
        $record = $this->findOrFail($request->paramInt('id'));

        return $this->view($this->formView ?? 'admin/shared/form', array_merge([
            'resource' => $this->config(),
            'record'   => $record,
            'fields'   => $this->fields($record),
        ], $this->formData($record)));
    }

    public function update(Request $request): Response
    {
        $id     = $request->paramInt('id');
        $record = $this->findOrFail($id);

        $validator = Validator::make($request->all(), $this->rules($id));

        if ($validator->fails()) {
            return $this->redirectWithErrors($this->route . '/' . $id . '/edit', $validator->errors(), $request->all());
        }

        $model = $this->model;
        $data  = $this->applySlug($this->payload($request, $id), $request, $id);

        $model::updateById($id, $data);

        $this->afterSave($id, $request, false);
        $this->refreshSitemap();

        ActivityLogger::log(strtolower($this->views) . '.updated', $model::table(), $id);
        $this->success($this->singular . ' saved.');

        return $this->redirect($this->route);
    }

    public function destroy(Request $request): Response
    {
        $id     = $request->paramInt('id');
        $record = $this->findOrFail($id);

        $blocked = $this->beforeDelete($record);

        if ($blocked !== null) {
            $this->error($blocked);

            return $this->redirect($this->route);
        }

        $model = $this->model;
        $model::deleteById($id);

        $this->refreshSitemap();

        ActivityLogger::log(strtolower($this->views) . '.deleted', $model::table(), $id);
        $this->success($this->singular . ' deleted.');

        return $this->redirect($this->route);
    }

    /**
     * Persists a new manual ordering. Accepts the id list in display order.
     */
    public function reorder(Request $request): Response
    {
        if (!$this->sortable) {
            throw new HttpException(404);
        }

        $ids   = $request->input('order', []);
        $model = $this->model;

        if (!is_array($ids)) {
            return Response::json(['ok' => false, 'message' => 'Invalid order payload.'], 422);
        }

        foreach (array_values($ids) as $position => $id) {
            $model::updateById((int) $id, ['sort_order' => $position]);
        }

        ActivityLogger::log(strtolower($this->views) . '.reordered', $model::table(), null, ['count' => count($ids)]);

        return Response::json(['ok' => true]);
    }

    // ---------------------------------------------------------------- internals

    /**
     * Rebuilds sitemap.xml for modules that appear in it.
     *
     * Sitemap::generate() swallows and logs its own failures, so a write problem
     * can never turn a successful save into an error page for the editor.
     */
    private function refreshSitemap(): void
    {
        if ($this->affectsSitemap) {
            Sitemap::generate();
        }
    }

    /**
     * @return array<string,mixed>
     */
    protected function findOrFail(int $id): array
    {
        $model  = $this->model;
        $record = $model::find($id);

        if ($record === null) {
            throw new HttpException(404, $this->singular . ' not found.');
        }

        return $record;
    }

    /**
     * Fills the slug from its source column when the editor left it blank, and
     * guarantees uniqueness either way.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function applySlug(array $data, Request $request, ?int $id): array
    {
        if ($this->slugColumn === null) {
            return $data;
        }

        $column = $this->slugColumn;
        $model  = $this->model;

        $provided = trim((string) ($data[$column] ?? ''));
        $source   = $provided !== '' ? $provided : (string) ($data[$this->slugSource] ?? '');

        $data[$column] = Slugger::unique($source, $model::table(), $id, $column);

        return $data;
    }

    /**
     * Values the shared views need in order to render links and labels.
     *
     * @return array<string,mixed>
     */
    protected function config(): array
    {
        return [
            'route'    => $this->route,
            'views'    => $this->views,
            'singular' => $this->singular,
            'plural'   => $this->plural,
            'sortable' => $this->sortable,
        ];
    }
}
