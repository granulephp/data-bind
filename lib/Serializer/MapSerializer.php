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
    DependencyResolver, DependencyResolverAware, TypeDeclaration, Serializer, Type
};
use Granule\Util\{Map, StrictTypedKey, StrictTypedValue};

class MapSerializer extends Serializer implements DependencyResolverAware {
    use KeyTypeExtraction;
    use ValueTypeExtraction;

    /** @var DependencyResolver */
    private $resolver;

    public function setResolver(DependencyResolver $resolver): void {
        $this->resolver = $resolver;
    }

    public function matches(Type $type): bool {
        return $type->is(Map::class);
    }

    /**
     * @param object $object
     * @return array
     */
    public function serialize($object) {
        $data = [];
        $valueSerializer = $keySerializer = null;
        if ($object instanceof StrictTypedValue) {
            $valueSerializer = $this->resolver->resolve(
                TypeDeclaration::fromName($object->getValueType())
            );
        }
        if ($object instanceof StrictTypedKey) {
            $keySerializer = $this->resolver->resolve(
                TypeDeclaration::fromName($object->getKeyType())
            );
        }

        foreach ($object as $k => $v) {
            $data[
                $keySerializer
                    ? $keySerializer->serialize($k)
                    : $this->resolver->resolve(
                        TypeDeclaration::fromData($k)
                    )->serialize($k)

            ] = $valueSerializer
                ? $valueSerializer->serialize($v)
                : $this->resolver->resolve(
                    TypeDeclaration::fromData($v)
                )->serialize($v);
        }

        return $data;
    }

    protected function unserializeItem($data, Type $type) {
        $kSerializer = $vSerializer = null;

        /** @var Map\MapBuilder $builder */
        $builder = call_user_func([$type->getName(), 'builder']);

        if ($kType = $this->getKeyType($type)) {
            $kSerializer = $this->resolver->resolve($kType);
        }

        if ($vType = $this->getValueType($type)) {
            $vSerializer = $this->resolver->resolve($vType);
        }

        foreach ($data as $k => $v) {
            $builder->add(
                $kSerializer
                    ? $kSerializer->unserialize($k, $kType)
                    : $this->resolver->resolve(
                        TypeDeclaration::fromData($k)
                )->unserialize($k, TypeDeclaration::fromData($k)),
                $vSerializer
                    ? $vSerializer->unserialize($v, $vType)
                    : $this->resolver->resolve(
                        TypeDeclaration::fromData($v)
                )->unserialize($v, TypeDeclaration::fromData($v))
            );
        }

        return $builder->build();
    }
}