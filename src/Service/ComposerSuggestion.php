<?php

declare(strict_types=1);

namespace App\Service;

final class ComposerSuggestion
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $label,
        public readonly string $token,
        public readonly string $url,
        public readonly string $markdown,
        public readonly string $meta = '',
        public readonly string $group = '',
        public readonly int $rank = 0,
    ) {
    }

    /** @return array{type:string,id:int,label:string,token:string,url:string,markdown:string,meta:string,group:string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'token' => $this->token,
            'url' => $this->url,
            'markdown' => $this->markdown,
            'meta' => $this->meta,
            'group' => $this->group,
        ];
    }
}
