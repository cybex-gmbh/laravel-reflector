<?php

namespace Cybex\ModelReflector;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

class ModelReflector
{
    /**
     * Used to cache the resolved Relations of a given Model.
     *
     * @var Collection
     */
    protected Collection $modelRelationsCache;

    /**
     * Used to cache resolved Models by class and key during runtime.
     *
     * @var Collection
     */
    protected Collection $modelRepository;

    /**
     * Used to cache all Models which exist in the Filesystem.
     *
     * @var Collection
     */
    protected Collection $filesystemModels;

    /**
     * Used to cache generic information about namespaces of Models.
     *
     * @var Collection
     */
    protected Collection $modelStructureInformation;

    public function __construct()
    {
        $this->modelRelationsCache       = collect();
        $this->modelRepository           = collect();
        $this->filesystemModels          = collect();
        $this->modelStructureInformation = collect();
    }

    /**
     * Get the Namespace which contains the Model Classes within the app.
     *
     * @return string
     */
    protected function getModelRootNamespace(): string
    {
        return $this->getModelsDirectory() ? sprintf('App\\%s', $this->getModelsDirectory()) : 'App';
    }

    /**
     * Get the Directory which contains the Model Classes within the app-Directory.
     *
     * @return string|null
     */
    protected function getModelsDirectory(): ?string
    {
        return config('modelReflector.model.directory');
    }

    /**
     * Returns a Collection of all relations of a Model, with additional information like the name of the relation,
     * relation type, related class and a empty base instance of the given Model from Eloquent.
     *
     * @param      $model
     * @param bool $forceRefresh
     *
     * @return Collection
     */
    public function getModelRelations($model, bool $forceRefresh = false): Collection
    {
        $modelInstance = $this->getModelInstance($model);

        $modelClass = get_class($modelInstance);

        if (!$forceRefresh && $this->modelRelationsCache->has($modelClass)) {
            return $this->modelRelationsCache->get($modelClass);
        }

        $reflectionClass   = new ReflectionClass($modelInstance);
        $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        $relations = collect();

        foreach ($reflectionMethods as $reflectionMethod) {
            if ($this->reflectionMethodIsRelation($reflectionMethod)) {
                $methodName   = $reflectionMethod->getName();
                $relation     = $modelInstance->{$methodName}();
                $relatedModel = $relation->getRelated();
                $relations->put($methodName, [
                    'relation'   => $methodName,
                    'returnType' => $reflectionMethod->getReturnType()->getName(),
                ]);

                // Not available for some polymorph relations.
                if (method_exists($relation, 'getForeignKeyName')) {
                    $relations->put($methodName, [
                        ...$relations->get($methodName),
                        'relatedClass'            => get_class($relatedModel),
                        'relatedModel'            => $relatedModel,
                        'relatedTable'            => $relatedModel->getTable(),
                        'foreignKeyName'          => $relation->getForeignKeyName(),
                        'qualifiedForeignKeyName' => $relation->getQualifiedForeignKeyName(),
                        'isRelationParent'        => sprintf('%s.%s', $modelInstance->getTable(), $relation->getForeignKeyName()) !== $relation->getQualifiedForeignKeyName(),
                    ]);
                }
            }
        }

        $this->modelRelationsCache->put($modelClass, $relations);

        return $relations;
    }

    /**
     * Returns the name of a Relation between the Model and the Target.
     *
     * @param $model
     * @param $target
     *
     * @return string
     * @throws Exception
     */
    public function getRelationByTarget($model, $target): string
    {
        $relation = $this->getModelRelations($model)->where('relatedClass', is_string($target) ? $target : get_class($target))->first();

        if (!$relation) {
            throw new Exception(
                sprintf(
                    'Relation to Target Model %s could not be found in the Model %s', is_string($target) ? $target : get_class($target), is_string($model) ? $model : get_class($model)
                )
            );
        }

        return $relation['relation'];
    }

    /**
     * Returns if a Model has a specific relation.
     *
     * @param        $model
     * @param string $relation
     *
     * @return bool
     */
    public function hasRelation($model, string $relation): bool
    {
        $modelInstance = $this->getModelInstance($model);

        return (is_subclass_of($this->getMethodReturnType($modelInstance, $relation), Relation::class));
    }

    /**
     * Get the return type of a specific method on a given Object or Model class.
     * Returns false when the Method does not exist.
     * Returns null if no return type is type hinted.
     *
     * @param        $class
     * @param string $method
     *
     * @return string|null
     */
    public function getMethodReturnType($class, string $method): ?string
    {
        // We do not use getModelInstance here, since we might want to be able, to check ReturnTypes on Non-Model-Objects as well.
        if (!(is_object($class) || (is_string($class)) && !is_subclass_of($class, Model::class))) {
            throw new InvalidArgumentException(sprintf('Parameter $class (%s) passed to %s is not an Object or a Model Class', $class, __METHOD__));
        }

        $object = is_object($class) ? $class : $class::getModel();

        if (!method_exists($object, $method)) {
            return null;
        }

        $reflectionMethod = new ReflectionMethod(get_class($object), $method);

        if (!$reflectionMethod->hasReturnType()) {
            return null;
        }

        return $reflectionMethod->getReturnType()->getName();
    }

    /**
     * Checks if a ReflectionMethods ReturnType is a Relation.
     * This method might be used for occurrences, where you already have access to an Instance of ReflectionMethod.
     *
     * @param ReflectionMethod $reflectionMethod
     *
     * @return bool
     */
    protected function reflectionMethodIsRelation(ReflectionMethod $reflectionMethod): bool
    {
        $reflectionType = $reflectionMethod->getReturnType();

        // Relations are supposed to have only one return type.
        if ($reflectionType instanceof \ReflectionUnionType) {
            return false;
        }

        return is_subclass_of(optional($reflectionType)->getName(), Relation::class);
    }

    /**
     * Checks if the given $model is an instance of a Model or the fully qualified class name of a Model,
     * and returns the $model or the empty Eloquent base Model of the given class.
     *
     * @param Model|string $model
     *
     * @return Model
     */
    public function getModelInstance(Model|string $model): Model
    {
        if (!is_subclass_of($model, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('Parameter $class (%s) passed to %s is not a Model instance or Model class.', $model, __METHOD__));
        }

        return is_object($model) ? $model : $model::getModel();
    }

    /**
     * Checks if the given $model is an instance of a Model or the fully qualified class name of a Model,
     * and returns the class of the given Model
     *
     * @param Model|string $model
     *
     * @return Model
     */
    public function getModelClass(Model|string $model): string
    {
        if (!is_subclass_of($model, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('Parameter $class (%s) passed to %s is not a Model instance or Model class.', $model, __METHOD__));
        }

        return is_object($model) ? get_class($model) : $model;
    }

    /**
     * Resolves a Model based on a given Model or a Class and the according identifier.
     * If no identifier is given, it may return a empty Builder-Model.
     * If the desired Model can not be found, it will return null.
     *
     * @param Model|string $model
     * @param null         $identifier
     *
     * @return Model|null
     */
    public function resolveModelObject(Model|string $model, $identifier = null): ?Model
    {
        $class = $this->getModelClass($model);

        if ($identifier) {
            if (!$this->modelRepository->has($class)) {
                $this->modelRepository->put($class, collect());
            }

            if ($this->modelRepository->get($class)->has($identifier)) {
                return $this->modelRepository->get($class)->get($identifier);
            }

            $resolvedModel = $class::find($identifier);

            $this->modelRepository->get($class)->put($resolvedModel->getKey(), $resolvedModel);

            return $resolvedModel;
        }

        return $class::getModel();
    }

    /**
     * Resolves a related Model by the source and the given Relation. Currently, we only support HasOne or BelongsTo-Relations, as those only return a single Model or null.
     *
     * @param Model  $model
     * @param string $relationName
     *
     * @return Model|null
     */
    public function resolveRelatedModel(Model $model, string $relationName): ?Model
    {
        $relation = $this->getModelRelations($model)->get($relationName);

        return $this->resolveModelObject($relation['relatedClass'], $model->{$relation['foreignKeyName']});
    }

    /**
     * Resolves a Related Model by the source and the given TargetModel.
     *
     * @param Model        $model
     * @param Model|string $targetModel
     *
     * @return Model|null
     */
    public function resolveRelatedModelByTarget(Model $model, Model|string $targetModel): ?Model
    {
        return $this->resolveRelatedModel($model, $this->getRelationByTarget($model, $this->getModelClass($targetModel)));
    }

    /**
     * Returns the Short-Name of a Model.
     *
     * @param $model
     *
     * @return string
     */
    public function getModelShortName($model): string
    {
        $modelInstance = $this->getModelInstance($model);

        return (new ReflectionClass($modelInstance))->getShortName();
    }

    /**
     * Get a Collection of all available Models via the Filesystem.
     *
     * @param bool $withoutAbstract if true, do not include abstract classes in the Collection.
     *
     * @return Collection
     */
    public function getAllModels(bool $withoutAbstract = true): Collection
    {
        $abstractIdentifier = $withoutAbstract ? 'withoutAbstract' : 'withAbstract';

        if ($this->filesystemModels->has($abstractIdentifier)) {
            return $this->filesystemModels->get($abstractIdentifier);
        }

        $modelRootNamespace = $this->getModelRootNamespace();

        $this->filesystemModels->put(
            $abstractIdentifier, collect(File::allFiles(app_path($this->getModelsDirectory())))->map(function ($item) use ($withoutAbstract, $modelRootNamespace)
            {
                $class = sprintf('%s\%s', $modelRootNamespace, implode('\\', explode('/', Str::beforeLast($item->getRelativePathname(), '.'))));

                return class_exists($class) && is_subclass_of(
                    $class, Model::class
                ) && ($withoutAbstract === false || (new ReflectionClass($class))->isAbstract() === false) ? $class : null;
            })->filter()
        );

        return $this->filesystemModels->get($abstractIdentifier);
    }

    /**
     * Get a Collection of all instantiatable Model-Classes, which are not Abstract.
     * Returns the full qualified Class-Name as key with the according Short-Name as value.
     *
     * @param string|array ...$requiredTraits
     *
     * @return Collection
     */
    public function getAllInstantiatableModels(string|array ...$requiredTraits): Collection
    {
        $instantiatableModels = $this->getAllModels()->mapWithKeys(fn($class) => [$class => $class::getModel()]);

        if ($requiredTraits) {
            $instantiatableModels = $instantiatableModels->filter(fn($model, $class) => $this->modelHasTraits($class, $requiredTraits));
        }

        return $instantiatableModels;
    }

    public function getInstantiatableModelStructureInformation(): Collection
    {
        if ($this->modelStructureInformation->count()) {
            return $this->modelStructureInformation;
        }

        $modelRootNamespace = $this->getModelRootNamespace();

        // Determine the Parent-Class based on the Namespace.
        $parentStructureInformation = $this->getAllInstantiatableModels()->map(function ($model, $class) use ($modelRootNamespace)
            {
                $supposedParentClass = (new ReflectionClass($class))->getNamespaceName();

                return collect(['parentClass' => $supposedParentClass !== $modelRootNamespace && class_exists($supposedParentClass) ? $supposedParentClass : null]);
            });

        // Determine the Child-Classes based on the previously determined parents.
        $this->modelStructureInformation = $parentStructureInformation->map(function ($structureInformation, $class) use ($parentStructureInformation)
            {
                return $structureInformation->put('childClasses', $parentStructureInformation->where('parentClass', $class)->keys()->all());
            });

        return $this->modelStructureInformation;
    }

    /**
     * Returns the class name from the Morph-Map alias (reverse lookup), the alias or null (if strict is true).
     *
     * @param string $alias
     * @param bool   $strict
     *
     * @return string|null
     */
    public function getClassFromMorphMap(string $alias, bool $strict = false): ?string
    {
        return Relation::getMorphedModel($alias) ?? ($strict ? null : $alias);
    }

    /**
     * Returns the morph alias for the specified Model.
     *
     * @param $model
     *
     * @return string|null
     */
    public function getMorphAliasForClass($model): ?string
    {
        return Arr::get(array_flip(Relation::morphMap()), ltrim($this->getModelClass($model), '\\'));
    }

    /**
     * Validates if a Model implements one or more specific Traits.
     *
     * @param Model|string $model
     * @param string|mixed ...$traits
     *
     * @return bool
     * @throws Exception
     */
    public function modelHasTraits(Model|string $model, string|array ...$traits): bool
    {
        $classUses = class_uses_recursive($this->getModelClass($model));

        foreach (Arr::flatten($traits) as $trait) {
            if (!trait_exists($trait)) {
                throw new Exception(sprintf('Invalid Parameter given to %s. "%s" does not exist or is not a Trait.', __METHOD__, $trait));
            }

            if (!array_key_exists($trait, $classUses)) {
                return false;
            }
        }

        return true;
    }
}
