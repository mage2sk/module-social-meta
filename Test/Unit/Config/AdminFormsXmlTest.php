<?php
declare(strict_types=1);

namespace Panth\SocialMeta\Test\Unit\Config;

use PHPUnit\Framework\TestCase;

class AdminFormsXmlTest extends TestCase
{
    private function load(string $relative): \SimpleXMLElement
    {
        $path = dirname(__DIR__, 3) . '/' . $relative;
        $this->assertTrue(is_file($path), $relative . ' exists');
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) file_get_contents($path));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml, $relative . ' is valid XML');
        return $xml;
    }

    public function testCategoryFormDeclaresOgFieldsInSeoFieldset(): void
    {
        $xml = $this->load('view/adminhtml/ui_component/category_form.xml');
        $fields = $xml->xpath('/form/fieldset[@name="search_engine_optimization"]/field');

        $names = array_map(static fn ($field) => (string) $field['name'], $fields);
        $this->assertSame(['og_title', 'og_description', 'og_image'], $names);

        foreach ($fields as $field) {
            $source = $field->xpath('argument[@name="data"]/item[@name="config"]/item[@name="source"]');
            $this->assertSame('category', (string) $source[0]);
            $this->assertNotSame('', trim((string) $field->settings->label));
            $this->assertNotSame('', trim((string) $field->settings->notice));
        }
        $this->assertSame('textarea', (string) $fields[1]['formElement']);
    }

    public function testProductFormModifierIsRegistered(): void
    {
        $xml = $this->load('etc/adminhtml/di.xml');
        $item = $xml->xpath(
            '/config/virtualType[@name="Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Pool"]'
            . '/arguments/argument[@name="modifiers"]/item[@name="panth_social_meta_og_fields"]'
        );

        $this->assertCount(1, $item);
        $this->assertSame(
            'Panth\SocialMeta\Ui\DataProvider\Product\Form\Modifier\OgFields',
            (string) $item[0]->xpath('item[@name="class"]')[0]
        );
    }

    public function testResolverReceivesFilesystem(): void
    {
        $xml = $this->load('etc/di.xml');
        $argument = $xml->xpath(
            '/config/type[@name="Panth\SocialMeta\Model\Social\OpenGraphResolver"]'
            . '/arguments/argument[@name="filesystem"]'
        );

        $this->assertSame('Magento\Framework\Filesystem', (string) $argument[0]);
    }

    public function testDependentConfigFieldsAndHelpText(): void
    {
        $xml = $this->load('etc/adminhtml/system.xml');
        $group = '/config/system/section[@id="panth_social_meta"]/group[@id="social"]';

        foreach (['twitter_card_type', 'twitter_site_handle'] as $id) {
            $depends = $xml->xpath($group . '/field[@id="' . $id . '"]/depends/field[@id="twitter_enabled"]');
            $this->assertSame('1', (string) $depends[0], $id . ' depends on twitter_enabled');
        }
        foreach (['article_enabled', 'article_registry_fallback'] as $id) {
            $depends = $xml->xpath($group . '/field[@id="' . $id . '"]/depends/field[@id="og_enabled"]');
            $this->assertSame('1', (string) $depends[0], $id . ' depends on og_enabled');
        }
        $comment = $xml->xpath($group . '/field[@id="default_og_image"]/comment');
        $this->assertStringContainsString('1200 x 630', (string) $comment[0]);
        $validate = $xml->xpath($group . '/field[@id="twitter_site_handle"]/validate');
        $this->assertSame('validate-no-html-tags', (string) $validate[0]);
    }
}
