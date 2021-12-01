# Laravel Reflector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cybex/laravel-reflector.svg?style=flat-square)](https://packagist.org/packages/cybex/laravel-reflector)

This package allows you to get structural information for data models.

## Requirements

- Illuminate/support: ^8.0
- PHP: ^8.0

## Installation

You can install the package via composer:

```
composer require cybex/laravel-reflector
```

## Usage

### getModelRelations()

The ```getModelRelations()``` method returns a Collection of all relations of a Model, with additional information like the name of the relation, relation type, related class and 
an empty base instance of the given Model from Eloquent.

```
ModelReflector::getModelRelations(User::class);

// Returns 

Illuminate\Support\Collection {#5236
    all: [
        "ManagedUser" => [
             "relation" => "ManagedUser",
             "returnType" => "Illuminate\Database\Eloquent\Relations\HasOne",
             "relatedClass" => "App\Models\ManagedUser",
             "relatedModel" => App\Models\ManagedUser {#5237},
             "relatedTable" => "users",
             "foreignKeyName" => "id",
             "qualifiedForeignKeyName" => "users.id",
             "isRelationParent" => false,
         ]
    ]
}
```

### getRelationByTarget()

The ```getRelationByTarget()``` method returns the name of a Relation between the Model and the Target.

```
$user = new User;
$managedUser = new ManagedUser;

ModelReflector::getRelationByTarget($user, $managedUser);

// Returns 

'ManagedUser'
```

### hasRelation()

The ```hasRelation()``` method returns true if a Model has a specific relation.

```
ModelReflector::hasRelation(User::class, 'ManagedUser');

// Returns

true
```

### getMethodReturnType()

The ```getMethodReturnType()``` method returns the type of a specific method on a given Object or Model class. It returns false when the Method does not exist. It returns null if
no return type is type hinted.

```
$user = new User;

ModelReflector::getMethodReturnType($user, 'ManagedUser');

// Returns 

'Illuminate\Database\Eloquent\Relations\HasOne'

```

### getModelInstance()

The ```getModelInstance()``` method checks if the given model is an instance of a Model or the fully qualified class name of a Model, and returns the model or the empty Eloquent
base Model of the given class.

```
ModelReflector::getModelInstance('App\Models\User');

// Returns 

App\Models\User {#5246}
```

### getModelClass()

The ```getModelClass()``` method checks if the given model is an instance of a Model or the fully qualified class name of a Model, and returns the class of the given Model.

```
$user = new User;
ModelReflector::getModelClass($user);

// Returns

'App\Models\User'
```

### resolveModelObject()

The ```resolveModelObject()``` method resolves a Model based on a given Model or a Class and the according identifier. If no identifier is given, it returns an empty Builder-Model.
If the desired Model can not be found, it will return null.

```
ModelReflector::resolveModelObject(ManagedUser::class, 10);

// Returns the ManagedUser object with the key 10
```

### resolveRelatedModel()

The ```resolveRelatedModel()``` method resolves a related Model by the source and the given Relation. Currently, we only support HasOne or BelongsTo-Relations, as those only return
a single Model or null.

```
ModelReflector::resolveRelatedModel($user, 'Role');

// Returns the Role object that the User object belongs to

```

### resolveRelatedModelByTarget()

The ```resolveRelatedModelByTarget()``` method resolves a related Model by the source and the given TargetModel.

```
ModelReflector::resolveRelatedModelByTarget($user, Role::class);


// Returns the Role object that the User object belongs to 
```

### getModelShortName()

The ```getModelShortName()``` method returns the Short-Name of a Model.

```
$user = new User;

ModelReflector::getModelShortName($user);

// Returns

'User'
```

### getAllModels()

The ```getAllModels()``` method returns a Collection of all available Models via the Filesystem.

```
ModelReflector::getAllModels();

// Returns 

Illuminate\Support\Collection {#384
    all: [
    "App\Models\ManagedUser",
    "App\Models\Role",
    "App\Models\User",
    ],
}
```

### getAllInstantiatableModels()

The ```getAllInstantiatableModels()``` method returns a Collection of all instantiatable Model-Classes, which are not Abstract. It returns the full qualified Class-Name as key with
the according Short-Name as value.

```
ModelReflector::getAllInstantiatableModels();

// Returns

Illuminate\Support\Collection {#4929
    all: [
    "App\Models\ManagedUser" => App\Models\ManagedUser {#4925},
    "App\Models\Role" => App\Models\Role {#4928},
    "App\Models\User" => App\Models\User {#4921},
    ],
}
```

### getInstantiatableModelStructureInformation()

The ```getInstantiatableModelStructureInformation()``` method returns a Collection of structure information for all instantiatable Model-Classes, which include the fully qualified
name of the parent class and the child classes.

```
ModelReflector::getInstantiatableModelStructureInformation();

// Returns 

Illuminate\Support\Collection {#1469
    all: [
        "App\Models\Country" => Illuminate\Support\Collection {#1482
            all: [
                "parentClass" => null,
                "childClasses" => [
                 "App\Models\Country\Retailer",
                ],
            ],
        },
        "App\Models\Country\Retailer" => Illuminate\Support\Collection {#1483
            all: [
                "parentClass" => "App\Models\Country",
                "childClasses" => [
                 "App\Models\Country\Retailer\DefaultPublishedStore",
                 "App\Models\Country\Retailer\PublishedOnlineShop",
                 "App\Models\Country\Retailer\Store",
                ],
            ],
        }
    ],
}
```

### getClassFromMorphMap()

The ```getClassFromMorphMap()``` method returns the class name from the Morph-Map alias (reverse lookup), the alias or null (if strict is true).

```
ModelReflector::getClassFromMorphMap('roles');

// Returns

'App\Models\Role'
```

### getMorphAliasForClass()

The ```getMorphAliasForClass()``` method returns the morph alias for the specified Model.

```
ModelReflector::getMorphAliasForClass('App\Models\Role');

// Returns

'roles'
```

### modelHasTraits()

The ```modelHasTraits()``` method validates if a Model implements one or more specific Traits.

```
ModelReflector::modelHasTraits(User::class, 'Illuminate\Database\Eloquent\Concerns\HasAttributes');

// Returns 

true
```
