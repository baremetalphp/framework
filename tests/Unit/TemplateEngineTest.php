<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\View\TemplateEngine;
use Tests\TestCase;

class TemplateEngineTest extends TestCase
{
    public function testCompilesRawEcho(): void
    {
        $template = '{!! $variable !!}';
        $compiled = TemplateEngine::compile($template);
        
        $this->assertStringContainsString('<?php echo $variable; ?>', $compiled);
    }

    public function testCompilesEscapedEcho(): void
    {
        $template = '{{ $variable }}';
        $compiled = TemplateEngine::compile($template);
        
        $this->assertStringContainsString('htmlspecialchars', $compiled);
        $this->assertStringContainsString('$variable', $compiled);
    }

    public function testCompilesIfStatement(): void
    {
        $template = "@if(\$condition)\nContent\n@endif";
        $compiled = TemplateEngine::compile($template);
        
        $this->assertStringContainsString('<?php if ($condition): ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);
    }

    public function testCompilesIfElseStatement(): void
    {
        $template = "@if(\$condition)\nTrue\n@else\nFalse\n@endif";
        $compiled = TemplateEngine::compile($template);
        
        $this->assertStringContainsString('<?php if ($condition): ?>', $compiled);
        $this->assertStringContainsString('<?php else: ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);
    }

    public function testCompilesIfElseifStatement(): void
    {
        $template = "@if(\$a)\nA\n@elseif(\$b)\nB\n@endif";
        $compiled = TemplateEngine::compile($template);
        
        $this->assertStringContainsString('<?php if ($a): ?>', $compiled);
        $this->assertStringContainsString('<?php elseif ($b): ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);
    }

    public function testCompilesForeachStatement(): void
    {
        $template = "@foreach(\$items as \$item)\nItem: {{ \$item }}\n@endforeach";
        $compiled = TemplateEngine::compile($template);
        
        $this->assertStringContainsString('<?php foreach ($items as $item): ?>', $compiled);
        $this->assertStringContainsString('<?php endforeach; ?>', $compiled);
    }

    public function testCompilesComplexTemplate(): void
    {
        $template = <<<BLADE
@if(\$user)
    <h1>Hello {{ \$user->name }}!</h1>
    @foreach(\$posts as \$post)
        <div>{!! \$post->content !!}</div>
    @endforeach
@else
    <p>No user</p>
@endif
BLADE;
        
        $compiled = TemplateEngine::compile($template);
        
        $this->assertStringContainsString('<?php if ($user): ?>', $compiled);
        $this->assertStringContainsString('htmlspecialchars', $compiled);
        $this->assertStringContainsString('<?php echo $post->content; ?>', $compiled);
        $this->assertStringContainsString('<?php foreach ($posts as $post): ?>', $compiled);
        $this->assertStringContainsString('<?php else: ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);
    }

    public function testHandlesIndentedDirectives(): void
    {
        $template = "    @if(\$condition)\n        Content\n    @endif";
        $compiled = TemplateEngine::compile($template);
        
        $this->assertStringContainsString('<?php if ($condition): ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);
    }

    public function testEscapesHtmlInEcho(): void
    {
        $template = '{{ "<script>alert(\'xss\')</script>" }}';
        $compiled = TemplateEngine::compile($template);
        
        // The compiled code should use htmlspecialchars
        $this->assertStringContainsString('htmlspecialchars', $compiled);
        
        // Execute the compiled code to verify it escapes
        $variable = "<script>alert('xss')</script>";
        ob_start();
        eval('?>' . $compiled);
        $output = ob_get_clean();
        
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }
}
