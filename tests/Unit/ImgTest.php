<?php

namespace Tests\Unit;

use App\Support\Img;
use PHPUnit\Framework\TestCase;

class ImgTest extends TestCase
{
    public function test_cloudinary_gets_responsive_transformation(): void
    {
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,c_limit,w_640/v1/shop/tee.jpg',
            Img::url('https://res.cloudinary.com/demo/image/upload/v1/shop/tee.jpg', 640),
        );
    }

    public function test_existing_cloudinary_transformation_is_preserved(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/w_300,c_fill/sample.jpg';
        $this->assertSame($url, Img::url($url, 640));
    }

    public function test_folder_names_that_look_like_params_are_not_mistaken_for_transformations(): void
    {
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,c_limit,w_640/my_folder/a.jpg',
            Img::url('https://res.cloudinary.com/demo/image/upload/my_folder/a.jpg', 640),
        );
    }

    public function test_unsplash_width_and_format_are_set(): void
    {
        $this->assertSame(
            'https://images.unsplash.com/photo-1?w=640&q=80&auto=format',
            Img::url('https://images.unsplash.com/photo-1?w=1600&q=80', 640),
        );
    }

    public function test_other_hosts_and_non_urls_are_untouched(): void
    {
        foreach (['https://example.com/a.jpg?x=1', '/assets/x.png', 'data:image/png;base64,AAA', 'https://res.cloudinary.com/demo/video/upload/x.mp4', '', 'not a url'] as $u) {
            $this->assertSame($u, Img::url($u, 640));
        }
        $this->assertNull(Img::url(null, 640));
    }

    public function test_srcset_only_for_transformable_urls(): void
    {
        $this->assertNull(Img::srcset('https://example.com/a.jpg', [320, 640]));
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,c_limit,w_320/a.jpg 320w, https://res.cloudinary.com/demo/image/upload/f_auto,q_auto,c_limit,w_640/a.jpg 640w',
            Img::srcset('https://res.cloudinary.com/demo/image/upload/a.jpg', [320, 640]),
        );
    }

    public function test_origin_for_preconnect(): void
    {
        $this->assertSame('https://res.cloudinary.com', Img::origin('https://res.cloudinary.com/demo/image/upload/a.jpg'));
        $this->assertNull(Img::origin('https://example.com/a.jpg'));
    }
}
