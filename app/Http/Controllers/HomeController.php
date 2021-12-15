<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\{Banner as Banner, Comment as Comment, Contact as Contact, 
	Section as Section, Setting as Setting, Topic as Topic,
    TopicCategory as TopicCategory, TopicField as TopicField, User as User, Webmail as Webmail,
    WebmasterSection as WebmasterSection, WebmasterSetting as WebmasterSetting};
use App\Jobs\SendEmailJob;
use App; use Redirect; use Helper; use Auth; use Cache; use Config;

class HomeController extends Controller
{
    public function __construct()
    {
        // check if website is closed
        $this->close_check();
    }

    public function SEO($seo_url_slug = 0)
    {
        return $this->SEOByLang("", $seo_url_slug);
    }

    public function SEOByLang(?string $lang, $seo_url_slug = 0)
    {
        if ($lang) {
            // Set Language
            App::setLocale($lang);
            \Session::put('locale', $lang);
        }
        $seo_url_slug = Str::slug($seo_url_slug, '-');

        switch ($seo_url_slug) {
            case "home" :
                return $this->HomePage();
                break;
            case "about" :
                $id = 1;
                $section = 1;
                return $this->topic($section, $id);
                break;
            case "privacy" :
                return redirect()->away('https://eigrejas.com/privacidade');
                break;
        }
        // General Webmaster Settings
        $WebmasterSettings = Cache::rememberForever('WebmasterSetting', function () {
           return WebmasterSetting::find(1);
        });
        $Current_Slug = "seo_url_slug_" . @Helper::currentLanguage()->code;
        $Default_Slug = "seo_url_slug_" . Config::get('app.locale');
        $Current_Title = "title_" . @Helper::currentLanguage()->code;
        $Default_Title = "title_" . Config::get('app.locale');

        $WebmasterSection1 = WebmasterSection::where($Current_Slug, $seo_url_slug)->orwhere($Default_Slug, $seo_url_slug)->first();
        if (!empty($WebmasterSection1)) {
            // MAIN SITE SECTION
            $section = $WebmasterSection1->id;
            return $this->topics($section, 0);
        } else {
            $WebmasterSection2 = WebmasterSection::where($Current_Title, $seo_url_slug)->orwhere($Default_Title, $seo_url_slug)->first();
            if (empty($WebmasterSection2)) {
                $AllWebmasterSections = WebmasterSection::where('status', 1)->get();
                foreach ($AllWebmasterSections as $TWebmasterSection) {
                    if ($TWebmasterSection->$Current_Title != "") {
                        $TTitle = $TWebmasterSection->$Current_Title;
                    } else {
                        $TTitle = $TWebmasterSection->$Default_Title;
                    }
                    $TTitle_slug = Str::slug($TTitle, '-');
                    if ($TTitle_slug == $seo_url_slug) {
                        $WebmasterSection2 = $TWebmasterSection;
                        break;
                    }
                }
            }
            if (!empty($WebmasterSection2)) {
                // MAIN SITE SECTION
                $section = $WebmasterSection2->id;
                return $this->topics($section, 0);
            } else {
                $Section = Section::where('status', 1)->where($Current_Slug, $seo_url_slug)->orwhere($Default_Slug, $seo_url_slug)->first();
                if (empty($Section)) {
                    $AllSection = Section::where('status', 1)->get();
                    foreach ($AllSection as $TSection) {
                        if ($TSection->$Current_Title != "") {
                            $TTitle = $TSection->$Current_Title;
                        } else {
                            $TTitle = $TSection->$Default_Title;
                        }
                        $TTitle_slug = Str::slug($TTitle, '-');
                        if ($TTitle_slug == $seo_url_slug) {
                            $Section = $TSection;
                            break;
                        }
                    }
                }

                if (!empty($Section)) {
                    // SITE Category
                    $section = $Section->webmaster_id;
                    $cat = $Section->id;
                    return $this->topics($section, $cat);
                } else {
                    $Topic = Topic::where('status', 1)->where($Current_Slug, $seo_url_slug)->orwhere($Default_Slug, $seo_url_slug)->first();
                    if (empty($Topic)) {
                        $AllTopics = Topic::where('status', 1)->get();
                        foreach ($AllTopics as $TTopic) {
                            if ($TTopic->$Current_Title != "") {
                                $TTitle = $TTopic->$Current_Title;
                            } else {
                                $TTitle = $TTopic->$Default_Title;
                            }
                            $TTitle_slug = Str::slug($TTitle, '-');
                            if ($TTitle_slug == $seo_url_slug) {
                                $Topic = $TTopic;
                                break;
                            }
                        }
                    }
                    if (!empty($Topic)) {
                        // check contact page
                        if ($Topic->id == $WebmasterSettings->contact_page_id) {
                            return $this->ContactPage();
                        }

                        // SITE Topic
                        $section_id = $Topic->webmaster_id;
                        $WebmasterSection = WebmasterSection::find($section_id);
                        $section = $WebmasterSection->id;
                        $id = $Topic->id;
                        return $this->topic($section, $id);
                    } else {
                        // Not found
                        return redirect()->route("HomePage");
                    }
                }
            }
        }

    }

    public function HomePage()
    {
        return $this->HomePageByLang("");
    }

    public function HomePageByLang($lang = "")
    {
        if ($lang) {
            // Set Language
            App::setLocale($lang);
            \Session::put('locale', $lang);
        }
        // General Webmaster Settings
        $WebmasterSettings = Cache::rememberForever('WebmasterSetting', function () {
           return WebmasterSetting::find(1);
        });

        // General for all pages
        $WebsiteSettings = Setting::find(1);

        $HomeContent = [];
        $HomeContent = Cache::remember('home'.$lang, 43200, function () use ($WebmasterSettings) {
            // Home topics
            $HomeTopics = Topic::where([['status', 1], ['webmaster_id', $WebmasterSettings->home_content1_section_id], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['status', 1], ['webmaster_id', $WebmasterSettings->home_content1_section_id], ['expire_date', null]])->orderby('row_no', Config::get('front.topics_order'))->limit(12)->get();
            // Home topics 2
            $HomeTopics2 = Topic::where([['status', 1], ['webmaster_id', $WebmasterSettings->home_content2_section_id], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['status', 1], ['webmaster_id', $WebmasterSettings->home_content2_section_id], ['expire_date', null]])->orderby('row_no', Config::get('front.topics_order'))->limit(12)->get();
            // Home photos
            $HomePhotos = Topic::where([['status', 1], ['webmaster_id', $WebmasterSettings->home_content_photo_section_id], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['status', 1], ['webmaster_id', $WebmasterSettings->home_content_photo_section_id], ['expire_date', null]])->orderby('row_no', Config::get('front.topics_order'))->limit(6)->get();
            // Home canal
            $HomeChannel = Topic::where([['status', 1], ['webmaster_id', $WebmasterSettings->home_content_channel_section_id], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['status', 1], ['webmaster_id', $WebmasterSettings->home_content_channel_section_id], ['expire_date', null]])->orderby('row_no', Config::get('front.topics_order'))->limit(6)->get();
            // Get Latest News
            $LatestNews = $this->latest_topics($WebmasterSettings->latest_news_section_id);
            // Get Home page slider banners
            $SliderBanners = Banner::where('section_id', $WebmasterSettings->home_banners_section_id)->where('status',
                1)->orderby('row_no', 'asc')->get();
            // Get Home page Test banners
            $TextBanners = Banner::where('section_id', $WebmasterSettings->home_text_banners_section_id)->where('status',
                1)->orderby('row_no', 'asc')->get();

            return [
                "HomeTopics" => $HomeTopics,
                "HomeTopics2" => $HomeTopics2,
                "HomePhotos" => $HomePhotos,
                "HomeChannel" => $HomeChannel,
                "LatestNews" => $LatestNews,
                "SliderBanners" => $SliderBanners,
                "TextBanners" => $TextBanners
            ];
        });

        if (!$HomeContent) { 
            Cache::tags(tenant('id'))->flush();
            return 'Tente novamente';
        }

        $HomeTopics = $HomeContent['HomeTopics'];
        $HomeTopics2 = $HomeContent['HomeTopics2'];
        $HomePhotos = $HomeContent['HomePhotos'];
        $HomeChannel = $HomeContent['HomeChannel'];
        $LatestNews = $HomeContent['LatestNews'];
        $SliderBanners = $HomeContent['SliderBanners'];
        $TextBanners = $HomeContent['TextBanners'];

        $Channel = '';
        $ChannelVideos = [];
        if (count($HomeChannel) > 0) {
            $Channel = $HomeChannel[0]->video_file;
            $ChannelVideos = Cache::remember('channel'.$lang, 43200, function () use ($Channel) {
                $token = config('app.google_token');
                $url = 'https://www.googleapis.com/youtube/v3/search?order=date&part=id,snippet&channelId='.$Channel.'&type=video&maxResults=6&regionCode=BR&key='.$token;
                $response = \Http::withOptions(['verify' => false])->get($url);
                if ($response->failed()) {
                    return [];
                }
        
                return $response->throw()->json();
            });
        }

        $site_desc_var = "site_desc_" . @Helper::currentLanguage()->code;
        $site_keywords_var = "site_keywords_" . @Helper::currentLanguage()->code;

        $PageTitle = ""; // will show default site Title
        $PageDescription = $WebsiteSettings->$site_desc_var;
        $AppBanner = $WebsiteSettings->site_app_banner;

        $HomePage = [];
        if ($WebmasterSettings->default_currency_id > 0) {
            $HomePage = Topic::where("status", 1)->find($WebmasterSettings->default_currency_id);
        }

        $HomePage2 = [];
        if ($WebmasterSettings->home_content3_section_id > 0) {
            $HomePage2 = Topic::where("status", 1)->find($WebmasterSettings->home_content3_section_id);
        }

        return view("frontEnd.home",
            compact(
                "WebsiteSettings",
                "WebmasterSettings",
                "SliderBanners",
                "TextBanners",
                "AppBanner",
                "PageTitle",
                "PageDescription",
                "HomePage",
                "HomeTopics",
                "HomeTopics2",
                "HomePage2",
                "HomePhotos",
                "Channel",
                "ChannelVideos",
                "LatestNews"));

    }

    public function links()
    {
        $PageTitle = ""; // will show default site Title

        return view("frontEnd.links",
            compact("PageTitle"));

    }

    public function topic($section = 0, $id = 0)
    {
        // check url slug
        if (!is_numeric($id)) {
            return $this->SEOByLang($section, $id);
        }

        $lang_dirs = array_filter(glob(App::langPath() . '/*'), 'is_dir');
        // check if this like "/ar/blog"
        if (in_array(App::langPath() . "/$section", $lang_dirs)) {
            return $this->topicsByLang($section, $id, 0);
        } else {
            return $this->topicByLang("", $section, $id);
        }
    }

    public function topicsByLang(?string $lang, $section = 0, $cat = 0)
    {
        if (!is_numeric($cat)) {
            return $this->topicsByLang($section, $cat, 0);
        }
        if ($lang) {
            // Set Language
            App::setLocale($lang);
            \Session::put('locale', $lang);
        }

        // General Webmaster Settings
        $WebmasterSettings = Cache::rememberForever('WebmasterSetting', function () {
           return WebmasterSetting::find(1);
        });


        // get Webmaster section settings by name
        $Current_Slug = "seo_url_slug_" . @Helper::currentLanguage()->code;
        $Default_Slug = "seo_url_slug_" . Config::get('app.locale');
        $WebmasterSection = WebmasterSection::where($Current_Slug, $section)->orwhere($Default_Slug, $section)->first();
        if (empty($WebmasterSection)) {
            // get Webmaster section settings by ID
            $WebmasterSection = WebmasterSection::find($section);
        }
        if (!empty($WebmasterSection)) {

            // if private redirect back to home
            if ($WebmasterSection->type == 4) {
                return redirect()->route("HomePage");
            }

            // count topics by Category
            $category_and_topics_count = array();
            $AllSections = Section::where('webmaster_id', '=', $WebmasterSection->id)->where('status', 1)->orderby('row_no', 'asc')->get();
            if (count($AllSections) > 0) {
                foreach ($AllSections as $AllSection) {
                    $category_topics = array();
                    $TopicCategories = TopicCategory::where('section_id', $AllSection->id)->get();
                    foreach ($TopicCategories as $category) {
                        $category_topics[] = $category->topic_id;
                    }

                    $Topics = Topic::where([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orWhere([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', null]])->whereIn('id', $category_topics)->orderby('row_no', Config::get('front.topics_order'))->get();
                    $category_and_topics_count[$AllSection->id] = count($Topics);
                }
            }

            // Get current Category Section details
            $CurrentCategory = Section::find($cat);
            // Get a list of all Category ( for side bar )
            $Categories = Section::where('webmaster_id', '=', $WebmasterSection->id)->where('father_id', '=',
                '0')->where('status', 1)->orderby('webmaster_id', 'asc')->orderby('row_no', 'asc')->get();

            if (!empty($CurrentCategory)) {
                $category_topics = array();
                $TopicCategories = TopicCategory::where('section_id', $cat)->get();
                foreach ($TopicCategories as $category) {
                    $category_topics[] = $category->topic_id;
                }
                // update visits
                $CurrentCategory->visits = $CurrentCategory->visits + 1;
                $CurrentCategory->save();
                // Topics by Cat_ID

                $Topics = Topic::where(function ($q) use ($WebmasterSection) {
                    $q->where([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orWhere([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', null]]);
                })->whereIn('id', $category_topics)->orderby('row_no', Config::get('front.topics_order'))->paginate(Config::get('front.frontend_pagination'));

                // Get Most Viewed Topics fot this Category
                $TopicsMostViewed = Topic::where([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orWhere([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', null]])->whereIn('id', $category_topics)->orderby('visits', 'desc')->limit(3)->get();
            } else {
                // Topics if NO Cat_ID
                $Topics = Topic::where([['webmaster_id', '=', $WebmasterSection->id], ['status',
                    1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orWhere([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', null]])->orderby('row_no', Config::get('front.topics_order'))->paginate(Config::get('front.frontend_pagination'));
                // Get Most Viewed
                $TopicsMostViewed = Topic::where([['webmaster_id', '=', $WebmasterSection->id], ['status',
                    1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orWhere([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', null]])->orderby('visits', 'desc')->limit(3)->get();
            }

            // General for all pages
            $WebsiteSettings = Setting::find(1);

            $SideBanners = Banner::where('section_id', $WebmasterSettings->side_banners_section_id)->where('status',
                1)->orderby('row_no', 'asc')->get();


            // Get Latest News
            $LatestNews = $this->latest_topics($WebmasterSettings->latest_news_section_id);

            // Page Title, Description, Keywords
            if (!empty($CurrentCategory)) {
                $seo_title_var = "seo_title_" . @Helper::currentLanguage()->code;
                $seo_description_var = "seo_description_" . @Helper::currentLanguage()->code;
                $tpc_title_var = "title_" . @Helper::currentLanguage()->code;
                $site_desc_var = "site_desc_" . @Helper::currentLanguage()->code;
                $site_keywords_var = "site_keywords_" . @Helper::currentLanguage()->code;
                if ($CurrentCategory->$seo_title_var != "") {
                    $PageTitle = $CurrentCategory->$seo_title_var;
                } else {
                    $PageTitle = $CurrentCategory->$tpc_title_var;
                }
                if ($CurrentCategory->$seo_description_var != "") {
                    $PageDescription = $CurrentCategory->$seo_description_var;
                } else {
                    $PageDescription = $WebsiteSettings->$site_desc_var;
                }
            } else {
                $site_desc_var = "site_desc_" . @Helper::currentLanguage()->code;
                $site_keywords_var = "site_keywords_" . @Helper::currentLanguage()->code;

                $title_var = "title_" . @Helper::currentLanguage()->code;
                $title_var2 = "title_" . Config::get('app.locale');
                if ($WebmasterSection->$title_var != "") {
                    $PageTitle = $WebmasterSection->$title_var;
                } else {
                    $PageTitle = $WebmasterSection->$title_var2;
                }

                $PageDescription = $WebsiteSettings->$site_desc_var;

            }
            // .. end of .. Page Title, Description, Keywords

            // Send all to the view
            $view = "topics";
            if ($WebmasterSection->type == 5) {
                $view = "table";
            }
            $statics = [];
            foreach ($WebmasterSection->customFields as $customField) {
                if ($customField->in_statics && ($customField->type == 6 || $customField->type == 7)) {
                    $cf_details_var = "details_" . @Helper::currentLanguage()->code;
                    $cf_details_var2 = "details_en" . Config::get('app.locale');
                    if ($customField->$cf_details_var != "") {
                        $cf_details = $customField->$cf_details_var;
                    } else {
                        $cf_details = $customField->$cf_details_var2;
                    }
                    $cf_details_lines = preg_split('/\r\n|[\r\n]/', $cf_details);
                    $line_num = 1;
                    $statics_row = [];
                    foreach ($cf_details_lines as $cf_details_line) {
                        if ($customField->type == 6) {
                            $tids = TopicField::select("topic_id")->where("field_id", $customField->id)->where("field_value", $line_num);
                        } else {
                            $tids = TopicField::select("topic_id")->where("field_id", $customField->id)->where("field_value", 'like', '%' . $line_num . '%');
                        }
                        $Topics_count = Topic::where('webmaster_id', '=', $WebmasterSection->id)->wherein('id', $tids)->count();
                        $statics_row[$line_num] = $Topics_count;
                        $line_num++;
                    }
                    $statics[$customField->id] = $statics_row;
                }
            }

            return view("frontEnd." . $view,
                compact("WebsiteSettings",
                    "WebmasterSettings",
                    "LatestNews",
                    "SideBanners",
                    "WebmasterSection",
                    "Categories",
                    "Topics",
                    "CurrentCategory",
                    "PageTitle",
                    "PageDescription",
                    "TopicsMostViewed",
                    "category_and_topics_count",
                    "statics"));

        } else {

            return $this->SEOByLang($lang, $section);
        }

    }

    public function topicByLang(?string $lang, $section = 0, $id = 0)
    {

        if ($lang) {
            // Set Language
            App::setLocale($lang);
            \Session::put('locale', $lang);
        }

        // General Webmaster Settings
        $WebmasterSettings = Cache::rememberForever('WebmasterSetting', function () {
           return WebmasterSetting::find(1);
        });

        // check for pages called by name not id
        switch ($section) {
            case "about" :
                $id = 1;
                $section = 1;
                break;
            case "privacy" :
                $id = 3;
                $section = 1;
                break;
            case "terms" :
                $id = 4;
                $section = 1;
                break;
        }


        // get Webmaster section settings by name
        $Current_Slug = "seo_url_slug_" . @Helper::currentLanguage()->code;
        $Default_Slug = "seo_url_slug_" . Config::get('app.locale');
        $WebmasterSection = WebmasterSection::where($Current_Slug, $section)->orwhere($Default_Slug, $section)->first();
        if (empty($WebmasterSection)) {
            // get Webmaster section settings by ID
            $WebmasterSection = WebmasterSection::find($section);
        }
        if (!empty($WebmasterSection)) {

            // if private redirect back to home
            if ($WebmasterSection->type == 4) {
                return redirect()->route("HomePage");
            }

            // count topics by Category
            $category_and_topics_count = array();
            $AllSections = Section::where('webmaster_id', '=', $WebmasterSection->id)->where('status', 1)->orderby('row_no', 'asc')->get();
            if (count($AllSections) > 0) {
                foreach ($AllSections as $AllSection) {
                    $category_topics = array();
                    $TopicCategories = TopicCategory::where('section_id', $AllSection->id)->get();
                    foreach ($TopicCategories as $category) {
                        $category_topics[] = $category->topic_id;
                    }

                    $Topics = Topic::where([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orWhere([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', null]])->whereIn('id', $category_topics)->orderby('row_no', Config::get('front.topics_order'))->get();
                    $category_and_topics_count[$AllSection->id] = count($Topics);
                }
            }

            $Topic = Topic::where('status', 1)->find($id);


            if (!empty($Topic) && ($Topic->expire_date == '' || ($Topic->expire_date != '' && $Topic->expire_date >= date("Y-m-d")))) {
                // update visits
                $Topic->visits = $Topic->visits + 1;
                $Topic->save();

                // Get current Category Section details
                $CurrentCategory = array();
                $TopicCategory = TopicCategory::where('topic_id', $Topic->id)->first();
                if (!empty($TopicCategory)) {
                    $CurrentCategory = Section::find($TopicCategory->section_id);
                }
                // Get a list of all Category ( for side bar )
                $Categories = Section::where('webmaster_id', '=', $WebmasterSection->id)->where('status',
                    1)->where('father_id', '=', '0')->orderby('webmaster_id', 'asc')->orderby('row_no', 'asc')->get();

                // Get Most Viewed
                $TopicsMostViewed = Topic::where([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', null]])->orderby('visits', 'desc')->limit(3)->get();

                // General for all pages
                $WebsiteSettings = Setting::find(1);

                $SideBanners = Banner::where('section_id', $WebmasterSettings->side_banners_section_id)->where('status',
                    1)->orderby('row_no', 'asc')->get();

                // Get Latest News
                $LatestNews = $this->latest_topics($WebmasterSettings->latest_news_section_id);

                // Page Title, Description, Keywords
                $seo_title_var = "seo_title_" . @Helper::currentLanguage()->code;
                $seo_description_var = "seo_description_" . @Helper::currentLanguage()->code;
                $tpc_title_var = "title_" . @Helper::currentLanguage()->code;
                $site_desc_var = "site_desc_" . @Helper::currentLanguage()->code;
                $site_keywords_var = "site_keywords_" . @Helper::currentLanguage()->code;
                if ($Topic->$seo_title_var != "") {
                    $PageTitle = $Topic->$seo_title_var;
                } else {
                    $PageTitle = $Topic->$tpc_title_var;
                }
                if ($Topic->$seo_description_var != "") {
                    $PageDescription = $Topic->$seo_description_var;
                } else {
                    $PageDescription = $WebsiteSettings->$site_desc_var;
                }
                // .. end of .. Page Title, Description, Keywords


                return view("frontEnd.topic",
                    compact("WebsiteSettings",
                        "WebmasterSettings",
                        "LatestNews",
                        "Topic",
                        "SideBanners",
                        "WebmasterSection",
                        "Categories",
                        "CurrentCategory",
                        "PageTitle",
                        "PageDescription",
                        "TopicsMostViewed",
                        "category_and_topics_count"));

            } else {
                return redirect()->action('HomeController@HomePage');
            }
        } else {
            return redirect()->action('HomeController@HomePage');
        }
    }

    public function topics($section = 0, $cat = 0)
    {
        $lang_dirs = array_filter(glob(App::langPath() . '/*'), 'is_dir');
        // check if this like "/pt/blog"
        if (in_array(App::langPath() . "/$section", $lang_dirs)) {
            return $this->topicsByLang($section, $cat, 0);
        } else {
            return $this->topicsByLang("", $section, $cat);
        }
    }

    public function userTopics($id)
    {
        return $this->userTopicsByLang("", $id);
    }

    public function userTopicsByLang(?string $lang, $id=0)
    {

        if ($lang) {
            // Set Language
            App::setLocale($lang);
            \Session::put('locale', $lang);
        }

        // General Webmaster Settings
        $WebmasterSettings = Cache::rememberForever('WebmasterSetting', function () {
           return WebmasterSetting::find(1);
        });

        // get User Details
        $User = User::find($id);
        if (!empty($User)) {


            // count topics by Category
            $category_and_topics_count = array();
            $AllSections = Section::where('status', 1)->orderby('row_no', 'asc')->get();
            if (!empty($AllSections)) {
                foreach ($AllSections as $AllSection) {
                    $category_topics = array();
                    $TopicCategories = TopicCategory::where('section_id', $AllSection->id)->get();
                    foreach ($TopicCategories as $category) {
                        $category_topics[] = $category->topic_id;
                    }

                    $Topics = Topic::where([['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orWhere([['status', 1], ['expire_date', null]])->whereIn('id', $category_topics)->orderby('row_no', Config::get('front.topics_order'))->get();
                    $category_and_topics_count[$AllSection->id] = count($Topics);
                }
            }

            // Get current Category Section details
            $CurrentCategory = "none";
            $WebmasterSection = "none";
            // Get a list of all Category ( for side bar )
            $Categories = Section::where('father_id', '=',
                '0')->where('status', 1)->orderby('webmaster_id', 'asc')->orderby('row_no', 'asc')->get();

            // Topics if NO Cat_ID
            $Topics = Topic::where([['created_by', $User->id], ['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['created_by', $User->id], ['status', 1], ['expire_date', null]])->orderby('row_no', 'asc')->paginate(Config::get('front.frontend_pagination'));
            // Get Most Viewed
            $TopicsMostViewed = Topic::where([['created_by', $User->id], ['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['created_by', $User->id], ['status', 1], ['expire_date', null]])->orderby('visits', 'desc')->limit(3)->get();

            // General for all pages
            $WebsiteSettings = Setting::find(1);

            $SideBanners = Banner::where('section_id', $WebmasterSettings->side_banners_section_id)->where('status',
                1)->orderby('row_no', 'asc')->get();


            // Get Latest News
            $LatestNews = $this->latest_topics($WebmasterSettings->latest_news_section_id);

            // Page Title, Description, Keywords
            $site_desc_var = "site_desc_" . @Helper::currentLanguage()->code;
            $site_keywords_var = "site_keywords_" . @Helper::currentLanguage()->code;

            $PageTitle = $User->name;
            $PageDescription = $WebsiteSettings->$site_desc_var;

            // .. end of .. Page Title, Description, Keywords

            // Send all to the view
            return view("frontEnd.topics",
                compact("WebsiteSettings",
                    "WebmasterSettings",
                    "LatestNews",
                    "User",
                    "SideBanners",
                    "WebmasterSection",
                    "Categories",
                    "Topics",
                    "CurrentCategory",
                    "PageTitle",
                    "PageDescription",
                    "TopicsMostViewed",
                    "category_and_topics_count"));

        } else {
            // If no section name/ID go back to home
            return redirect()->action('HomeController@HomePage');
        }

    }

    public function searchTopics()
    {

        // General Webmaster Settings
        $WebmasterSettings = Cache::rememberForever('WebmasterSetting', function () {
           return WebmasterSetting::find(1);
        });

        $search_word = request()->input("q");
        $section = request()->input("section");

        if ($search_word != "") {

            $WebmasterSection = WebmasterSection::find($section);

            // count topics by Category
            $category_and_topics_count = array();
            $AllSections = Section::where('status', 1)->orderby('row_no', 'asc')->get();
            if (!empty($AllSections)) {
                foreach ($AllSections as $AllSection) {
                    $category_topics = array();
                    $TopicCategories = TopicCategory::where('section_id', $AllSection->id)->get();
                    foreach ($TopicCategories as $category) {
                        $category_topics[] = $category->topic_id;
                    }

                    $Topics = Topic::where([['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orWhere([['status', 1], ['expire_date', null]])->whereIn('id', $category_topics)->orderby('row_no', Config::get('front.topics_order'))->get();
                    $category_and_topics_count[$AllSection->id] = count($Topics);
                }
            }

            // Get current Category Section details
            $CurrentCategory = "none";
            // Get a list of all Category ( for side bar )
            $Categories = Section::where('father_id', '=',
                '0')->where('status', 1)->orderby('webmaster_id', 'asc')->orderby('row_no', 'asc')->get();

            // Topics if NO Cat_ID
            $Topics = Topic::select("id")->where('title_' . Helper::currentLanguage()->code, 'like', '%' . $search_word . '%')
                ->orwhere('seo_title_' . Helper::currentLanguage()->code, 'like', '%' . $search_word . '%')
                ->orwhere('details_' . Helper::currentLanguage()->code, 'like', '%' . $search_word . '%');

            $topics_ids = TopicField::select("topic_id")->where("field_value" , 'like', '%' . $search_word . '%');
            $Topics = $Topics->orwherein("id", $topics_ids);
            if (!empty($WebmasterSection)) {
                $Topics = Topic::where("webmaster_id", $WebmasterSection->id)->where("status", true)->wherein("id", $Topics)->orderby('id', 'desc')->paginate(env('FRONTEND_PAGINATION'));
            }else {
                $Topics = Topic::where("status", true)->wherein("id", $Topics)->orderby('id', 'desc')->paginate(env('FRONTEND_PAGINATION'));
            }
            // Get Most Viewed
            $TopicsMostViewed = Topic::where([['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['status', 1], ['expire_date', null]])->orderby('visits', 'desc')->limit(3)->get();

            // General for all pages
            $WebsiteSettings = Setting::find(1);

            $SideBanners = Banner::where('section_id', $WebmasterSettings->side_banners_section_id)->where('status',
                1)->orderby('row_no', 'asc')->get();


            // Get Latest News
            $LatestNews = Topic::where([['status', 1], ['webmaster_id', $WebmasterSettings->latest_news_section_id], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['status', 1], ['webmaster_id', $WebmasterSettings->latest_news_section_id], ['expire_date', null]])->orderby('row_no', 'desc')->limit(3)->get();

            // Page Title, Description, Keywords
            $site_desc_var = "site_desc_" . @Helper::currentLanguage()->code;
            $site_keywords_var = "site_keywords_" . @Helper::currentLanguage()->code;

            $PageTitle = $search_word;
            $PageDescription = $WebsiteSettings->$site_desc_var;

            // .. end of .. Page Title, Description, Keywords

            // Send all to the view
            $view = "topics";
            if (!empty($WebmasterSection)) {
                if (@$WebmasterSection->type == 5) {
                    $view = "table";
                }
            }

            return view("frontEnd.".$view,
                compact("WebsiteSettings",
                    "WebmasterSettings",
                    "LatestNews",
                    "search_word",
                    "SideBanners",
                    "WebmasterSection",
                    "Categories",
                    "Topics",
                    "CurrentCategory",
                    "PageTitle",
                    "PageDescription",
                    "TopicsMostViewed",
                    "category_and_topics_count"));

        } else {
            // If no section name/ID go back to home
            return redirect()->action('HomeController@HomePage');
        }

    }

    public function openSearch()
    {
        $content = view('frontEnd.opensearch')->render();
        return response($content, 200)->header('Content-Type', 'text/xml');
    }

    public function ContactPage()
    {
        return $this->ContactPageByLang("");
    }

    public function ContactPageByLang($lang = "")
    {

        if ($lang) {
            // Set Language
            App::setLocale($lang);
            \Session::put('locale', $lang);
        }
        // General Webmaster Settings
        $WebmasterSettings = Cache::rememberForever('WebmasterSetting', function () {
           return WebmasterSetting::find(1);
        });

        $id = $WebmasterSettings->contact_page_id;
        $Topic = Topic::where('status', 1)->find($id);


        if (!empty($Topic) && ($Topic->expire_date == '' || ($Topic->expire_date != '' && $Topic->expire_date >= date("Y-m-d")))) {

            // update visits
            $Topic->visits = $Topic->visits + 1;
            $Topic->save();

            // get Webmaster section settings by ID
            $WebmasterSection = WebmasterSection::find($Topic->webmaster_id);

            if (!empty($WebmasterSection)) {

                // Get current Category Section details
                $CurrentCategory = Section::find($Topic->section_id);
                // Get a list of all Category ( for side bar )
                $Categories = Section::where('webmaster_id', '=', $WebmasterSection->id)->where('father_id', '=',
                    '0')->where('status', 1)->orderby('webmaster_id', 'asc')->orderby('row_no', 'asc')->get();

                // Get Most Viewed
                $TopicsMostViewed = Topic::where([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['webmaster_id', '=', $WebmasterSection->id], ['status', 1], ['expire_date', null]])->orderby('visits', 'desc')->limit(3)->get();

                // General for all pages
                $WebsiteSettings = Setting::find(1);

                $SideBanners = Banner::where('section_id', $WebmasterSettings->side_banners_section_id)->where('status',
                    1)->orderby('row_no', 'asc')->get();

                // Get Latest News
                $LatestNews = $this->latest_topics($WebmasterSettings->latest_news_section_id);


                // Page Title, Description, Keywords
                $seo_title_var = "seo_title_" . @Helper::currentLanguage()->code;
                $seo_description_var = "seo_description_" . @Helper::currentLanguage()->code;
                $tpc_title_var = "title_" . @Helper::currentLanguage()->code;
                $site_desc_var = "site_desc_" . @Helper::currentLanguage()->code;
                $site_keywords_var = "site_keywords_" . @Helper::currentLanguage()->code;
                if ($Topic->$seo_title_var != "") {
                    $PageTitle = $Topic->$seo_title_var;
                } else {
                    $PageTitle = $Topic->$tpc_title_var;
                }
                if ($Topic->$seo_description_var != "") {
                    $PageDescription = $Topic->$seo_description_var;
                } else {
                    $PageDescription = $WebsiteSettings->$site_desc_var;
                }
                // .. end of .. Page Title, Description, Keywords

                return view("frontEnd.contact",
                    compact("WebsiteSettings",
                        "WebmasterSettings",
                        "LatestNews",
                        "Topic",
                        "SideBanners",
                        "WebmasterSection",
                        "Categories",
                        "CurrentCategory",
                        "PageTitle",
                        "PageDescription",
                        "TopicsMostViewed"));

            } else {
                return redirect()->action('HomeController@HomePage');
            }
        } else {
            return redirect()->action('HomeController@HomePage');
        }

    }

    public function ContactPageSubmit(Request $request)
    {

        $this->validate($request, [
            'contact_name' => 'required',
            'contact_email' => 'required|email',
            'contact_subject' => 'required',
            'contact_message' => 'required'
        ]);

        if (env('NOCAPTCHA_STATUS', false)) {
            $this->validate($request, [
                'g-recaptcha-response' => 'required|captcha'
            ]);
        }

        $site_title_var = "site_title_" . @Helper::currentLanguage()->code;
        $site_email = @Helper::GeneralSiteSettings("site_webmails");

        $Webmail = new Webmail;
        $Webmail->cat_id = 0;
        $Webmail->group_id = null;
        $Webmail->title = $request->contact_subject;
        $Webmail->details = $request->contact_message;
        $Webmail->date = date("Y-m-d H:i:s");
        $Webmail->from_email = $request->contact_email;
        $Webmail->from_name = $request->contact_name;
        $Webmail->from_phone = $request->contact_phone;
        $Webmail->to_email = $site_email;
        $Webmail->to_name = @Helper::GeneralSiteSettings($site_title_var);
        $Webmail->status = 0;
        $Webmail->flag = 0;
        $Webmail->save();

        // SEND Notification Email
        if (@Helper::GeneralSiteSettings('notify_messages_status')) {
            try {
                $recipient = explode(",", str_replace(" ", "", $site_email));
                $message_details = __('frontend.name') . ": " . $request->contact_name . "<hr>" . __('frontend.phone') . ": " 
                . $request->contact_phone . "<hr>" . __('frontend.email') . ": " . $request->contact_email . "<hr>" . __('frontend.message') . ":<br>" . nl2br($request->contact_message);
    
                $attributes = ['to' 		=> $recipient,
                            'title' 		=> $request->contact_subject,
                            'details' 		=> $message_details,
                            'from_email'	=> $request->contact_email,
                            'from_name'	    => $request->contact_name];
    
                SendEmailJob::dispatchAfterResponse($attributes);
            } catch (\Exception $e) {
                return response()->json(['info' => 'error', 'msg' => "Erro, sua mensagem não foi enviada. Tente contato pelo telefone."]);
            }
        }

        return response()->json(['info' => 'success', 'msg' => "Sua mensagem foi enviada. Obrigado!"]);
    }

    public function subscribeSubmit(Request $request)
    {


        $this->validate($request, [
            'subscribe_name' => 'required',
            'subscribe_email' => 'required|email'
        ]);

        // General Webmaster Settings
        $WebmasterSettings = Cache::rememberForever('WebmasterSetting', function () {
           return WebmasterSetting::find(1);
        });

        $Contacts = Contact::where('email', $request->subscribe_email)->get();
        if (count($Contacts) > 0) {
            return __('frontend.subscribeToOurNewsletterError');
        } else {
            $subscribe_names = explode(' ', $request->subscribe_name, 2);

            $Contact = new Contact;
            $Contact->group_id = $WebmasterSettings->newsletter_contacts_group;
            $Contact->first_name = @$subscribe_names[0];
            $Contact->last_name = @$subscribe_names[1];
            $Contact->email = $request->subscribe_email;
            $Contact->status = 1;
            $Contact->save();

            return "OK";
        }
    }

    public function commentSubmit(Request $request)
    {

        $this->validate($request, [
            'comment_name' => 'required',
            'comment_message' => 'required',
            'topic_id' => 'required',
            'comment_email' => 'required|email'
        ]);

        if (env('NOCAPTCHA_STATUS', false)) {
            $this->validate($request, [
                'g-recaptcha-response' => 'required|captcha'
            ]);
        }
        // Topic details
        $Topic = Topic::where('status', 1)->find($request->topic_id);
        if (!empty($Topic)) {
            $next_nor_no = Comment::where('topic_id', '=', $request->topic_id)->max('row_no');
            if ($next_nor_no < 1) {
                $next_nor_no = 1;
            } else {
                $next_nor_no++;
            }

            $Comment = new Comment;
            $Comment->row_no = $next_nor_no;
            $Comment->name = $request->comment_name;
            $Comment->email = $request->comment_email;
            $Comment->comment = $request->comment_message;
            $Comment->topic_id = $request->topic_id;
            $Comment->date = date("Y-m-d H:i:s");
            $Comment->status = (@Helper::GeneralWebmasterSettings('new_comments_status')) ? 1 : 0;
            $Comment->save();


            $site_email = @Helper::GeneralSiteSettings("site_webmails");

            $tpc_title_var = "title_" . @Helper::currentLanguage()->code;
            $tpc_title = $Topic->$tpc_title_var;

            // SEND Notification Email
            if (@Helper::GeneralSiteSettings('notify_comments_status')) {
                try {
                    $recipient = explode(",", str_replace(" ", "", $site_email));
                    $message_details = __('frontend.name') . ": " . $request->comment_name . "<hr>" . __('frontend.email') . ": " . $request->comment_email . "<hr>" . __('frontend.comment') . ":<br>" . nl2br($request->comment_message);

                    $attributes = ['to' 	=> $recipient,
                        "title" => "Comment: " . $tpc_title,
                        "details" => $message_details,
                        "from_email" => $request->comment_email,
                        "from_name" => $request->comment_name];
    
                    SendEmailJob::dispatchAfterResponse($attributes);
                } catch (\Exception $e) {

                }
            }
        }

        return "OK";
    }

    public function orderSubmit(Request $request)
    {

        $this->validate($request, [
            'order_name' => 'required',
            'order_phone' => 'required',
            'topic_id' => 'required',
            'order_email' => 'required|email'
        ]);

        if (env('NOCAPTCHA_STATUS', false)) {
            $this->validate($request, [
                'g-recaptcha-response' => 'required|captcha'
            ]);
        }

        $site_title_var = "site_title_" . @Helper::currentLanguage()->code;
        $site_email = @Helper::GeneralSiteSettings("site_webmails");

        $Topic = Topic::where('status', 1)->find($request->topic_id);
        if (!empty($Topic)) {
            $tpc_title_var = "title_" . @Helper::currentLanguage()->code;
            $tpc_title = $Topic->$tpc_title_var;

            $Webmail = new Webmail;
            $Webmail->cat_id = 0;
            $Webmail->group_id = 2;
            $Webmail->contact_id = null;
            $Webmail->father_id = null;
            $Webmail->title = "ORDER: " . $Topic->$tpc_title_var;
            $Webmail->details = $request->order_message;
            $Webmail->date = date("Y-m-d H:i:s");
            $Webmail->from_email = $request->order_email;
            $Webmail->from_name = $request->order_name;
            $Webmail->from_phone = $request->order_phone;
            $Webmail->to_email = $site_email;
            $Webmail->to_name = @Helper::GeneralSiteSettings($site_title_var);
            $Webmail->status = 0;
            $Webmail->flag = 0;
            $Webmail->save();


            // SEND Notification Email
            if (@Helper::GeneralSiteSettings('notify_orders_status')) {
                try {
                    $recipient = explode(",", str_replace(" ", "", $site_email));
                    $message_details = __('frontend.name') . ": " . $request->order_name . "<hr>" . __('frontend.phone') . ": " . $request->order_phone . "<hr>" . __('frontend.email') . ": " . $request->order_email . "<hr>" . __('frontend.notes') . ":<br>" . nl2br($request->order_message);

                    $attributes = ['to' 	=> $recipient,
                        "title" => "Order: " . $tpc_title,
                        "details" => $message_details,
                        "from_email" => $request->order_email,
                        "from_name" => $request->order_name];
    
                    SendEmailJob::dispatchAfterResponse($attributes);
                } catch (\Exception $e) {

                }
            }

        }

        return "OK";
    }

    public function latest_topics($section_id, $limit = 3)
    {
        return Topic::where([['status', 1], ['webmaster_id', $section_id], ['expire_date', '>=', date("Y-m-d")], ['expire_date', '<>', null]])->orwhere([['status', 1], ['webmaster_id', $section_id], ['expire_date', null]])->orderby('row_no', 'desc')->limit($limit)->get();
    }

    public function close_check()
    {
        // Check the website Status
        $WebsiteSettings = Setting::find(1);
        if (!$WebsiteSettings) {
            return;
        }

        $site_status = $WebsiteSettings->site_status;
        $site_msg = $WebsiteSettings->close_msg;
        if (!@Auth::check()) {
            if ($site_status == 0) {
                // close the website
                $site_title = $WebsiteSettings->{'site_title_' . Helper::currentLanguage()->code};
                $site_desc = $WebsiteSettings->{'site_desc_' . Helper::currentLanguage()->code};
                echo "<!DOCTYPE html>
                <html>
                <head>
                <meta charset=\"utf-8\">
                <title>$site_title</title>
                <meta name=\"description\" content=\"$site_desc\"/>
                <body>
                <br>
                <div style='text-align: center;'>
                <p>$site_msg</p>
                </div>
                </body>
                </html>
                ";
                exit();
            }
        }
    }
}