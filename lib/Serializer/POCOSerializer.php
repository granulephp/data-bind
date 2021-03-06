<?php
/*
 * MIT License
 *
 * Copyright (c) 2017 Eugene Bogachov
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Granule\DataBind\Serializer;

use Granule\DataBind\{
    DependencyResolver, DependencyResolverAware, InvalidDataException, Serializer, Type
};

/**
 * Plain Old Control Object serializer
 */
class POCOSerializer extends Serializer implements DependencyResolverAware {
    /** @var TypeDetector */
    private $typeDetector;
    /** @var DependencyResolver */
    private $resolver;
    /** @var bool */
    private $skipNull;

    public function setResolver(DependencyResolver $resolver): void {
        $this->resolver = $resolver;
    }

    public function __construct(TypeDetector $typeDetector, $skipNull = false) {
        $this->typeDetector = $typeDetector;
        $this->skipNull = $skipNull;
    }

    public function matches(Type $type): bool {
        return class_exists($type->getName());
    }

    public function serialize($data) {
        $response = [];
        $reflectionClass = new \ReflectionClass($data);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if (!$reflectionProperty->isPublic()) {
                $reflectionProperty->setAccessible(true);
            }

            $value = $reflectionProperty->getValue($data);
            if ($value !== null) {
                $serializer = $this->resolver->resolve(Type::fromData($value));
                $response[$reflectionProperty->getName()] = $serializer->serialize($value);
            } elseif (!$this->skipNull) {
                $response[$reflectionProperty->getName()] = null;
            }
        }

        return $response;
    }

    protected function unserializeItem($data, Type $type) {
        $class = new \ReflectionClass($type->getName());
        $object = $class->newInstanceWithoutConstructor();

        if (!is_array($data)) {
            throw InvalidDataException::fromTypeAndData($type, $data);
        }

        foreach ($class->getProperties() as $property) {
            $key = $property->getName();
            $type = $this->typeDetector->detect($property);
            
            if (!array_key_exists($key, $data) || $data[$key] === null) {
                if ($type->isNullable()) {
                    continue;
                } else {
                    throw NullValueException::fromPropertyWithType($property, $type);
                }
            } else {
                $serializer = $this->resolver->resolve($type);

                if (!$property->isPublic()) {
                    $property->setAccessible(true);
                }

                $property->setValue($object, $serializer->unserialize($data[$key], $type));
            }
        }

        return $object;
    }
}