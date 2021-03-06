<?php

/**
 * Allow users to tidy + purify content when saved
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class CleanContentExtension extends DataExtension
{

    private static $db = array(
        'TidyHtml'            => 'Boolean',
        'FixUTF8'            => 'Boolean',
        'StripWordTags'        => 'Boolean',
        'PurifyHtml'        => 'Boolean',
        'CleanOnSave'        => 'Boolean',
        'UseSiteSettings'       => 'Boolean'
    );

    private static $defaults = array(
        'UseSiteSettings'   => 1
    );
    
    public function updateSettingsFields(FieldList $fields)
    {
        if (class_exists('Multisites') && $this->owner instanceof Site) {
            return;
        }
        $options = new FieldGroup(
            new CheckboxField('UseSiteSettings', _t('TidyContent.USE_SITE_SETTINGS', 'Use site settings')),
            new CheckboxField('CleanOnSave', _t('TidyContent.CLEAN_ON_SAVE', 'Clean this content whenever the page is saved')),
            new CheckboxField('TidyHtml', _t('TidyContent.TIDY_HTML', 'Tidy HTML')),
            new CheckboxField('PurifyHtml', _t('TidyContent.PURIFY_HTML', 'Purify HTML')),
            new CheckboxField('FixUTF8', _t('TidyContent.FIX_UTF8', 'Fix badly encoded UTF8 characters')),
            new CheckboxField('StripWordTags', _t('TidyContent.STRIP_WORD', 'Strip extraneous MS word tags'))
        );
        
        $options->setTitle('Cleaning:');
        $fields->addFieldToTab('Root.Cleaning', $options);
    }
    
    protected function getConf() {
        $conf = SiteConfig::current_site_config();
        if (class_exists('Multisites')) {
            $conf = Multisites::inst()->getCurrentSite();
        }
        return $conf;
    }

    public function onBeforeWrite()
    {
        $conf = $this->getConf();

        if ((!$this->owner->ID || $this->owner->UseSiteSettings) && $conf) {
            // get defaults
            $this->owner->TidyHtml = $conf->DefaultTidy;
            $this->owner->PurifyHtml = $conf->DefaultPurify;
            $this->owner->StripWordTags = $conf->DefaultStripWord;
            $this->owner->FixUTF8 = $conf->DefaultFixUTF8;
            if ($this->owner->TidyHtml || $this->owner->PurifyHtml) {
                $this->owner->CleanOnSave = true;
            }
        }

        if ($this->owner->CleanOnSave) {
            $content = $this->owner->Content;
            
            if ($this->owner->FixUTF8) {
                // Does some cleanup on unicode characters that fall outside printable range
                $content = singleton('CleanContentService')->fixUtf8($content);
            }
            
            if ($this->owner->PurifyHtml) {
                if ($this->owner->isChanged('Content')) {
                    $content = singleton('CleanContentService')->purify($content);
                }
            }
            
            if ($this->owner->FixUTF8) {
                // Does some cleanup on unicode characters that fall outside printable range
                $content = singleton('CleanContentService')->fixUtf8($content);
            }

            if ($this->owner->TidyHtml) {
                if ($this->owner->isChanged('Content', 2)) {
                    $content = singleton('CleanContentService')->tidy($content, $this->owner->StripWordTags);
                    $content = singleton('CleanContentService')->fixUtf8($content);
                }
            }
            
            if ($this->owner->FixUTF8) {
                // Does some cleanup on unicode characters that fall outside printable range
                $content = singleton('CleanContentService')->fixUtf8($content);
            }
            
            $this->owner->Content = $content;
        }
    }

    /**
     * Usable from templates if CleanOnSave isn't applicable
     */
    public function Clean($field = 'Content')
    {
        $content = $this->owner->$field;

        if ($this->owner->PurifyHtml) {
            if ($this->owner->isChanged('Content')) {
                $content = singleton('CleanContentService')->purify($content);
            }
        }

        if ($this->owner->TidyHtml) {
            if ($this->owner->isChanged('Content')) {
                $content = singleton('CleanContentService')->tidy($content);
            }
        }

        return DBField::create_field('HTMLVarchar', $content);
    }
}
