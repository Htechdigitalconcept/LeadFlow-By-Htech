<?php
use HTECH\LeadFlow\{Admin, Capture, Security, Settings, Store, Tools};
use PHPUnit\Framework\TestCase;

final class LeadFlowTest extends TestCase {
    protected function setUp(): void {
        global $wpdb;
        foreach (Store::TABLES as $table) {
            self::assertNotFalse($wpdb->query($wpdb->prepare('DELETE FROM %i',Store::table($table))));
        }
        update_option('htecl_options',Settings::defaults());
        wp_set_current_user(0);
    }

    public function testInstallationSchemaAndCapabilities(): void {
        global $wpdb;
        self::assertSame(Store::SCHEMA,get_option('htecl_schema'));
        foreach (Store::CAPS as $cap) { self::assertTrue(get_role('administrator')->has_cap('htecl_'.$cap)); }
        self::assertFalse(current_user_can('htecl_view'));
        self::assertNotFalse(wp_next_scheduled('htecl_cleanup'));
        foreach (['fingerprint','status_date','email','phone'] as $index) {
            self::assertNotEmpty($wpdb->get_results($wpdb->prepare('SHOW INDEX FROM %i WHERE Key_name=%s',Store::table('leads'),$index)));
        }
        self::assertSame(0,Settings::get()['purge']);
    }

    public function testCaptureValidationAndRendering(): void {
        $config=Capture::defaults();
        $id=Store::insert('forms',['title'=>'Consultations','active'=>1,'config'=>Store::json($config),'created'=>Store::now()]);
        self::assertGreaterThan(0,$id);
        self::assertStringContainsString('Full name',do_shortcode('[htech_lead_form id="'.$id.'"]'));
        self::assertStringContainsString('website_confirm',Capture::shortcode(['id'=>$id]));
        self::assertTrue(Store::update('forms',$id,['active'=>0]));
        self::assertStringContainsString('unavailable',Capture::shortcode(['id'=>$id]));
        self::assertIsString(Capture::validate($config['fields'],[]));
        self::assertIsString(Capture::validate($config['fields'],['full_name'=>'Example','email'=>'bad','enquiry'=>'Requested quote']));
        $data=['full_name'=>'<script>alert(1)</script>Ada','email'=>'ada@example.test','phone'=>'+2348021234567','enquiry'=>'Requested quote'];
        self::assertIsString(Capture::validate($config['fields'],array_replace($data,['phone'=>'080test'])));
        $values=Capture::validate($config['fields'],$data);
        self::assertSame('Ada',$values['name']);
        self::assertSame('+2348021234567',$values['phone']);
        self::assertIsString(Capture::config(['fields'=>[['type'=>'select','label'=>'Region']]]));
        self::assertIsString(Capture::config(['fields'=>[['type'=>'text','label'=>'Name']], 'redirect'=>'https://elsewhere.test']));
        self::assertIsString(Capture::validate([['type'=>'checkbox','key'=>'consent','label'=>'Consent','required'=>1]],[]));
    }

    public function testDatabaseDeduplicationAndDeletion(): void {
        $d=Capture::validate(Capture::defaults()['fields'],['full_name'=>'Ada','email'=>'ada@example.test','enquiry'=>'Need a quote']);
        $id=Capture::record(7,$d,['utm_source'=>'newsletter','utm_campaign'=>'launch']);
        self::assertGreaterThan(0,$id);
        self::assertSame(0,Capture::record(7,$d,[]));
        self::assertSame('newsletter',Store::get('leads',$id)['source']);
        self::assertSame('Lead created',Store::children('activities',$id)[0]['body']);
        Store::insert('notes',['lead_id'=>$id,'body'=>'Private','created'=>Store::now()]);
        Store::insert('followups',['lead_id'=>$id,'body'=>'Call','due'=>'2028-02-29','created'=>Store::now()]);
        self::assertTrue(Store::delete($id));
        self::assertNull(Store::get('leads',$id));
        foreach (['notes','followups','activities'] as $table) { self::assertSame([],Store::children($table,$id)); }
    }

    public function testWhatsAppTelephoneAndInputDefense(): void {
        self::assertSame('+2348021234567',Security::phone('0802 123-4567','234'));
        self::assertSame('+442071838750',Security::phone('00442071838750','234'));
        self::assertSame('https://wa.me/2348021234567?text=Hello%20%26%20welcome',Settings::whatsapp('+2348021234567','Hello & welcome'));
        self::assertSame('',Settings::whatsapp('','Test'));
        self::assertSame('',Settings::whatsapp('number','Test'));
        self::assertSame('',Security::url('javascript:alert(1)'));
        self::assertSame('',Security::local_url('https://somewhere-else.test/'));
        self::assertSame('',Security::date('2025-02-29'));
        self::assertSame('2028-02-29',Security::date('2028-02-29'));
        foreach (['=1','+1','-1','@a',"\t=1","\xE2\x80\x8B=1"] as $value) { self::assertSame("'".$value,Security::csv($value)); }
    }

    public function testNonceTimeAndRateLimit(): void {
        self::assertFalse(Security::ok_nonce('htecl_submit_3','no'));
        self::assertTrue(Security::ok_nonce('htecl_submit_3',wp_create_nonce('htecl_submit_3')));
        $now=time(); $issued=(string)($now-3);
        self::assertTrue(Security::time_ok($issued,Security::sign('3|'.$issued),3));
        self::assertFalse(Security::time_ok($issued,Security::sign('3|'.$issued),4));
        self::assertFalse(Security::time_ok((string)$now,Security::sign('3|'.$now),3));
        for ($i=0;$i<12;$i++) { self::assertTrue(Security::rate(3,'192.0.2.7',1800)); }
        self::assertFalse(Security::rate(3,'192.0.2.7',1800));
        self::assertTrue(Security::rate(3,'192.0.2.7',2400));
    }

    public function testAnalyticsSearchAndDates(): void {
        self::assertSame(0.0,Store::conversion(0,0));
        $lead=Tools::import_values(['Adele','adele@example.test'],['name'=>0,'email'=>1]);
        $l1=Store::create($lead+['created'=>'2025-02-01 12:00:00']);
        Store::create(array_replace($lead,['status'=>'converted','email'=>'other@example.test','created'=>'2025-02-02 13:00:00']));
        self::assertGreaterThan(0,$l1);
        self::assertSame(2,Store::count());
        self::assertSame(1,Store::count(['status'=>'converted']));
        self::assertSame(50.0,Store::conversion(1,2));
        self::assertSame(1,Store::count(['from'=>'2025-02-02','to'=>'2025-02-02']));
        self::assertSame(2,Store::count(['q'=>'Adele']));
        self::assertSame(0,Store::count(['q'=>"%' OR 1=1 --"]));
        self::assertCount(2,Store::groups('status',[]));
        self::assertCount(1,Store::duplicates('adele@example.test',''));
    }

    public function testImportParsePrivacyAndRetention(): void {
        $file=tempnam(sys_get_temp_dir(),'htecl-csv-');
        try {
            file_put_contents($file,"name,email\nAda,ada@example.test\n");
            $parsed=Tools::parse_csv($file);
            self::assertSame(['name','email'],$parsed['headers']);
            self::assertIsString(Tools::import_values(['Ada','bad'],['name'=>0,'email'=>1]));
            file_put_contents($file,"name,email\n\0Bin,bad\n"); self::assertIsString(Tools::parse_csv($file));
            $values=Tools::import_values($parsed['rows'][0],['name'=>0,'email'=>1]);
            $id=Store::create($values);
            self::assertCount(1,Tools::privacy_export('ADA@example.test')['data']);
            self::assertTrue(Tools::privacy_erase('ada@example.test')['items_removed']);
            self::assertNull(Store::get('leads',$id));
            $old=Store::create($values+['created'=>'2020-01-01 00:00:00']);
            $new=Store::create($values);
            $s=Settings::get(); $s['retention']=30; update_option('htecl_options',$s);
            Tools::cleanup();
            self::assertNull(Store::get('leads',$old));
            self::assertNotNull(Store::get('leads',$new));
        } finally { unlink($file); }
    }

    public function testReactivationAndSchemaReapplyPreserveCustomerRecords(): void {
        $id=Store::create(Tools::import_values(['QA lead'],['name'=>0]));
        Store::deactivate();
        self::assertFalse(wp_next_scheduled('htecl_cleanup'));
        Store::activate();
        self::assertSame('QA lead',Store::get('leads',$id)['name']);
        self::assertNotFalse(wp_next_scheduled('htecl_cleanup'));
    }
    public function testAttributionDistinguishesEmptyFromUnavailableAndDropsQueryData(): void {
        $unknown=Capture::attribution([]);
        self::assertSame('Unavailable',$unknown['source']);
        $direct=Capture::attribution(['landing'=>home_url('/?page_id=8&token=sensitive'),'ref_state'=>'none']);
        self::assertSame('Direct / no referrer reported',$direct['source']);
        self::assertStringContainsString('page_id=8',$direct['landing']);
        self::assertStringNotContainsString('token',$direct['landing']);
        $external=Capture::attribution(['referrer'=>'https://referrer.example.test/start?secret=1#fragment']);
        self::assertSame('referrer.example.test',$external['source']);
        self::assertSame('https://referrer.example.test/start',Store::decode($external['attribution'])['referrer']);
    }

    public function testMailRecipientFieldSelectionAndFailureHandling(): void {
        $config=Capture::defaults();
        $config['recipient']='sales@example.test';
        $form=Store::insert('forms',['title'=>'Mail','active'=>1,'config'=>Store::json($config),'created'=>Store::now()]);
        $lead=Store::create(Tools::import_values(['Example','lead@example.test','Private message'],['name'=>0,'email'=>1,'message'=>2])+['form_id'=>$form]);
        $options=Settings::get();
        $options['notify']=1; $options['recipient']='office@example.test'; $options['mail_fields']=['name'];
        update_option('htecl_options',$options);
        $calls=[];
        $spy=static function ($result,$args) use (&$calls) { $calls[]=$args; return false; };
        add_filter('pre_wp_mail',$spy,10,2);
        try { Settings::notify($lead); } finally { remove_filter('pre_wp_mail',$spy,10); }
        self::assertCount(1,$calls);
        self::assertSame('sales@example.test',$calls[0]['to']);
        self::assertStringContainsString('Name: Example',$calls[0]['message']);
        self::assertStringContainsString('page=htecl-leads',$calls[0]['message']);
        self::assertStringNotContainsString('Private message',$calls[0]['message']);
        self::assertStringNotContainsString('lead@example.test',$calls[0]['message']);
        self::assertStringContainsString('did not accept',Store::children('activities',$lead)[0]['body']);
    }

    public function testFormEditorsCannotChangeNotificationRecipientWithoutSettingsCapability(): void {
        $input=['fields'=>[['type'=>'text','label'=>'Name']], 'recipient'=>'unapproved@example.test'];
        $existing=['config'=>Store::json(['recipient'=>'approved@example.test'])];
        self::assertSame('approved@example.test',Admin::form_config($input,$existing)['recipient']);
        self::assertSame('',Admin::form_config($input,null)['recipient']);
        $greatest=get_users(['role'=>'administrator','number'=>1,'fields'=>'ID']);
        wp_set_current_user((int)$greatest[0]);
        self::assertSame('unapproved@example.test',Admin::form_config($input,$existing)['recipient']);
    }

}
