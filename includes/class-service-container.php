<?php
/**
 * Service Container Class
 *
 * Dependency Injection Container for managing object creation and dependencies.
 * Provides centralized object management with singleton support.
 *
 * @package Penalis_Emailer
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Penalis_Service_Container
 *
 * Simple dependency injection container with automatic dependency resolution.
 */
class Penalis_Service_Container {
    
    /**
     * Singleton instances
     *
     * @var array
     */
    private static $instances = [];
    
    /**
     * Service bindings (class => factory callable)
     *
     * @var array
     */
    private static $bindings = [];
    
    /**
     * Singleton bindings (class => should be singleton)
     *
     * @var array
     */
    private static $singletons = [];
    
    /**
     * Get or create an instance of a class
     *
     * @param string $class Class name to instantiate
     * @return object Instance of the class
     * @throws Penalis_Container_Exception If class cannot be instantiated
     */
    public static function get(string $class) {
        // Check if singleton instance exists
        if (isset(self::$instances[$class])) {
            return self::$instances[$class];
        }
        
        // Create new instance
        $instance = self::create($class);
        
        // Store if singleton
        if (self::is_singleton($class)) {
            self::$instances[$class] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Bind a class to a factory callable
     *
     * @param string   $class   Class name
     * @param callable $factory Factory function that returns instance
     * @param bool     $singleton Whether to treat as singleton
     * @return void
     */
    public static function bind(string $class, callable $factory, bool $singleton = false): void {
        self::$bindings[$class] = $factory;
        
        if ($singleton) {
            self::$singletons[$class] = true;
        }
    }
    
    /**
     * Bind a class as singleton
     *
     * @param string   $class   Class name
     * @param callable $factory Factory function (optional)
     * @return void
     */
    public static function singleton(string $class, ?callable $factory = null): void {
        self::$singletons[$class] = true;
        
        if ($factory !== null) {
            self::$bindings[$class] = $factory;
        }
    }
    
    /**
     * Set an existing instance as singleton
     *
     * @param string $class    Class name
     * @param object $instance Instance to store
     * @return void
     */
    public static function instance(string $class, $instance): void {
        self::$instances[$class] = $instance;
        self::$singletons[$class] = true;
    }
    
    /**
     * Check if class is registered as singleton
     *
     * @param string $class Class name
     * @return bool True if singleton, false otherwise
     */
    private static function is_singleton(string $class): bool {
        return isset(self::$singletons[$class]) && self::$singletons[$class] === true;
    }
    
    /**
     * Create instance of a class with dependency resolution
     *
     * @param string $class Class name
     * @return object Instance of the class
     * @throws Penalis_Container_Exception If class cannot be instantiated
     */
    private static function create(string $class) {
        // Check if custom factory exists
        if (isset(self::$bindings[$class])) {
            return call_user_func(self::$bindings[$class]);
        }
        
        // Check if class exists
        if (!class_exists($class)) {
            throw new Penalis_Container_Exception(
                "Class does not exist",
                ['class' => $class, 'operation' => 'create']
            );
        }
        
        // Get reflection class
        $reflection = new ReflectionClass($class);
        
        // Check if class is instantiable
        if (!$reflection->isInstantiable()) {
            throw new Penalis_Container_Exception(
                "Class is not instantiable",
                ['class' => $class, 'operation' => 'create', 'reason' => 'abstract or interface']
            );
        }
        
        // Get constructor
        $constructor = $reflection->getConstructor();
        
        // If no constructor, just instantiate
        if ($constructor === null) {
            return new $class();
        }
        
        // Get constructor parameters
        $parameters = $constructor->getParameters();
        
        // If no parameters, just instantiate
        if (empty($parameters)) {
            return new $class();
        }
        
        // Resolve dependencies
        $dependencies = self::resolve_dependencies($parameters);
        
        // Create instance with dependencies
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Resolve constructor dependencies
     *
     * @param array $parameters ReflectionParameter array
     * @return array Resolved dependencies
     * @throws Penalis_Container_Exception If dependency cannot be resolved
     */
    private static function resolve_dependencies(array $parameters): array {
        $dependencies = [];
        
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getType();
            
            // If no type hint, check for default value
            if ($dependency === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Penalis_Container_Exception(
                        "Cannot resolve parameter without type hint or default value",
                        [
                            'parameter' => $parameter->getName(),
                            'operation' => 'resolve_dependencies'
                        ]
                    );
                }
                continue;
            }
            
            // Get type name (PHP 7.1+ compatibility)
            $dependency_name = $dependency instanceof ReflectionNamedType 
                ? $dependency->getName() 
                : (string) $dependency;
            
            // If primitive type, check for default value
            if ($dependency->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Penalis_Container_Exception(
                        "Cannot resolve primitive parameter",
                        [
                            'parameter' => $parameter->getName(),
                            'type' => $dependency_name,
                            'operation' => 'resolve_dependencies'
                        ]
                    );
                }
                continue;
            }
            
            // Resolve class dependency recursively
            try {
                $dependencies[] = self::get($dependency_name);
            } catch (Penalis_Container_Exception $e) {
                // If optional (nullable or has default), use null or default
                if ($parameter->allowsNull() || $parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->isDefaultValueAvailable() 
                        ? $parameter->getDefaultValue() 
                        : null;
                } else {
                    // Re-throw with additional context
                    throw new Penalis_Container_Exception(
                        "Cannot resolve class dependency: " . $e->getMessage(),
                        [
                            'parameter' => $parameter->getName(),
                            'dependency_class' => $dependency_name,
                            'operation' => 'resolve_dependencies',
                            'missing_dependencies' => [$dependency_name]
                        ],
                        $e
                    );
                }
            }
        }
        
        return $dependencies;
    }
    
    /**
     * Check if class is bound
     *
     * @param string $class Class name
     * @return bool True if bound, false otherwise
     */
    public static function has(string $class): bool {
        return isset(self::$bindings[$class]) || isset(self::$instances[$class]);
    }
    
    /**
     * Clear all instances and bindings (useful for testing)
     *
     * @return void
     */
    public static function clear(): void {
        self::$instances = [];
        self::$bindings = [];
        self::$singletons = [];
    }
    
    /**
     * Clear specific instance
     *
     * @param string $class Class name
     * @return void
     */
    public static function forget(string $class): void {
        unset(self::$instances[$class]);
        unset(self::$bindings[$class]);
        unset(self::$singletons[$class]);
    }
    
    /**
     * Get all registered singletons
     *
     * @return array Array of singleton class names
     */
    public static function get_singletons(): array {
        return array_keys(self::$singletons);
    }
    
    /**
     * Get all registered bindings
     *
     * @return array Array of binding class names
     */
    public static function get_bindings(): array {
        return array_keys(self::$bindings);
    }
    
    /**
     * Call a method with dependency injection
     *
     * @param object|string $class_or_object Class name or object instance
     * @param string        $method          Method name
     * @param array         $parameters      Additional parameters (indexed by parameter name or position)
     * @return mixed Method return value
     * @throws Penalis_Container_Exception If method cannot be called
     */
    public static function call($class_or_object, string $method, array $parameters = []) {
        // Get object instance
        $object = is_object($class_or_object) 
            ? $class_or_object 
            : self::get($class_or_object);
        
        // Get reflection method
        $reflection = new ReflectionMethod($object, $method);
        
        // Get method parameters
        $method_parameters = $reflection->getParameters();
        
        // If no parameters, just call
        if (empty($method_parameters)) {
            return $reflection->invoke($object);
        }
        
        // Resolve dependencies
        $dependencies = [];
        
        foreach ($method_parameters as $index => $parameter) {
            $param_name = $parameter->getName();
            
            // Check if parameter provided by name or index
            if (isset($parameters[$param_name])) {
                $dependencies[] = $parameters[$param_name];
                continue;
            } elseif (isset($parameters[$index])) {
                $dependencies[] = $parameters[$index];
                continue;
            }
            
            $dependency = $parameter->getType();
            
            // If no type hint, check for default value
            if ($dependency === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Penalis_Container_Exception(
                        "Cannot resolve method parameter without type hint or default value",
                        [
                            'method' => $method,
                            'parameter' => $parameter->getName(),
                            'operation' => 'call'
                        ]
                    );
                }
                continue;
            }
            
            // Get type name
            $dependency_name = $dependency instanceof ReflectionNamedType 
                ? $dependency->getName() 
                : (string) $dependency;
            
            // If primitive type, check for default value
            if ($dependency->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Penalis_Container_Exception(
                        "Cannot resolve primitive method parameter",
                        [
                            'method' => $method,
                            'parameter' => $parameter->getName(),
                            'type' => $dependency_name,
                            'operation' => 'call'
                        ]
                    );
                }
                continue;
            }
            
            // Resolve class dependency
            $dependencies[] = self::get($dependency_name);
        }
        
        // Call method with dependencies
        return $reflection->invokeArgs($object, $dependencies);
    }
}
