<?php

namespace App\Http\Controllers\Api;

use App\Models\College;
use App\Models\Dormitory;
use App\Models\ServiceExamMeta;
use App\Models\ServiceExamTime;
use App\Models\ServiceExamGkl;
use App\Models\ServiceHygiene;
use App\Models\ServiceNewStudent;
use App\Models\ServiceUser;
use Auth;
use Dingo\Api\Exception\StoreResourceFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use App\Libs\Base64;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use QL\QueryList;

class FeatureController extends Controller
{
    protected $cookie_file;

    public function authorization(Request $request) {
        $validator = app('validator')->make($request->all(),[
            'code' => 'required'
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException("参数不正确",$validator->errors());
        }
        $driver = Socialite::driver('weixin');
        $response = $driver->getAccessTokenResponse($request->code);
        $user = ServiceUser::where('openid',$response['openid'])->first();
        if (!$user) {
            $user = ServiceUser::create([
                'openid' => $response['openid'],
            ]);
        }
        if ($user->sdut_id != null) {
            //用户已绑定
            $data = array(
                'sdut_id' => $user->sdut_id,
                'name' => $user->name,
                'college'=>$user->college,
                'class' => $user->class,
                'dormitory' => $user->dormitory,
                'room' => $user->room,
                'password_jwc' => $user->password_jwc == null ? null : decrypt($user->password_jwc),
                'password_dt' => $user->password_dt == null ? null : decrypt($user->password_dt),
            );
            return $this->response->array(['data'=>$data,'meta'=>[
                'access_token' => Auth::guard('service')->fromUser($user),
                'token_type' => 'Bearer',
                'expires_in' => Auth::guard('service')->factory()->getTTL() * 60,
            ],'code' => 0])->setStatusCode(201);
        }else {
            return $this->response->array(['meta'=>[
                'access_token' => Auth::guard('service')->fromUser($user),
                'token_type' => 'Bearer',
                'expires_in' => Auth::guard('service')->factory()->getTTL() * 60,
            ],'code' => -1])->setStatusCode(201);
        }

    }

    public function index(){
        $user = Auth::guard('service')->user();
        if ($user == null) {
            return $this->response->errorUnauthorized("用户不存在");
        }
        $data = array([
            'sdut_id' => $user->sdut_id,
            'name' => $user->name,
            'college'=>$user->college,
            'class' => $user->class,
            'dormitory' => $user->dormitory,
            'room' => $user->room,
            'password_jwc' => $user->password_jwc == null ? null : decrypt($user->password_jwc),
            'password_dt' => $user->password_dt == null ? null : decrypt($user->password_dt),
        ]);
        return $this->response->array(['data'=>$data])->setStatusCode(200);
    }

    public function updateUser(Request $request) {
        $user = Auth::guard('service')->user();
        if ($user == null) {
            return $this->response->errorUnauthorized("用户不存在");
        }
        $validator = app('validator')->make($request->all(),[
            'sdut_id' => 'required|size:11',
            'college' => 'required|exists:colleges,id',
            'dormitory' => 'required|exists:dormitorys,id',
            'room' => 'required|numeric',
            'password_jwc' => 'required'
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException("信息错误！",$validator->errors());
        }
        //验证教务处密码是否正确
        $jar = $this->loginJWC($request->sdut_id,$request->password_jwc);
        if ($jar == null) {
            throw new StoreResourceFailedException("学号或教务处密码错误");
        }
        $client = new Client(['cookies'=>$jar]);
        $res = $client->request('GET','http://210.44.191.124/jwglxt/xtgl/index_cxYhxxIndex.html');
        $queryList = QueryList::html($res->getBody());
        $name = $queryList->find('.media-heading')->text();

        //验证网上服务大厅密码
        if ($request->password_dt != null) {
            $jar = $this->loginEhall($request->sdut_id,$request->password_dt);
            if ($jar == null) {
                throw new StoreResourceFailedException("学号或网上服务大厅密码错误");
            }
        }

        $user->sdut_id = $request->sdut_id;
        $user->name = $name;
        $user->college_id = $request->college;
        $user->dormitory_id = $request->dormitory;
        $user->class = $request->class;
        $user->room = $request->room;
        $user->password_jwc = $request->password_jwc == null ? null : encrypt($request->password_jwc);
        $user->password_dt = $request->password_dt == null ? null : encrypt($request->passwprd_dt);
        $user->save();
        return $this->response->noContent();
    }

    public function deleteUser() {
        $user = Auth::guard('service')->user();
        if ($user == null) {
            return $this->response->errorUnauthorized("用户不存在");
        }
        $user->delete();
        return $this->response->noContent();
    }

    public function newStudent(Request $request)
    {
        $num = $request->num;
        if(strlen($num)==11){
            $new_student = ServiceNewStudent::where('sdut_id',$num)->first();
        }else{
            $new_student = ServiceNewStudent::where('kaohao',$num)->first();
        }
       if($new_student)
       {
           $sheyou = ServiceNewStudent::where('dormitory',$new_student->dormitory)->where('room',$new_student->room)->where('bed','<>',$new_student->bed)->orderBy('bed','ASC')->get(['name','class','bed']);
           return $this->response->array([
               'name'=>$new_student->name,
               'sdut_id'=>$new_student->sdut_id,
               'college'=>$new_student->college,
               'major'=>$new_student->major,
               'class'=>$new_student->class,
               'school'=>$new_student->school,
               'dormitory'=>$new_student->dormitory,
               'room'=>$new_student->room,
               'bed'=>$new_student->bed,
               'roommate'=>$sheyou
           ])->setStatusCode(200);
       }else{
           return $this->response->errorNotFound('没有该学生信息');
       }
//       $url = 'http://211.64.28.125/sdut_q/query';
//       $this->get_cookie($url);
//       $data = $this->http_request_post($url,['ticketNumber'=>'18371202151375'],1);
//        return $data;
    }
    public function dormitory(){
        $dormitory = Dormitory::all();
        return $this->response->array(['data'=>$dormitory->toArray()])->setStatusCode(200);
    }

    public function college() {
        $colleges = College::all();
        return $this->response->array(['data'=>$colleges->toArray()])->setStatusCode(200);
    }

    //宿舍成绩
    public function hygiene()
    {
        $lroom = \request('dormitory');
        $croom = intval(\request('room'));
        $room = $lroom.$croom;
        $data = ServiceHygiene::where('room','=',$room)->orderBy('week','asc')->get();
        if(count($data)>0){
            return $this->response->array(['data'=>$data->toArray()])->setStatusCode(200);
        }else{
            return $this->response->errorNotFound("参数错误，未获取到房间为{$room}宿舍卫生信息");
        }
    }
    //考试时间
    public function exam(){
        $sdut_id = \request('sdut_id');
        $exam_times = ServiceExamTime::where('sdut_id',$sdut_id)/*->orderBy('date','ASC')*/->get();
        $data = array();
        foreach ($exam_times as $exam_time){
            $exam_meta = ServiceExamMeta::where('date',$exam_time->date)->where('classroom',$exam_time->classroom)->first();
            $gkl = ServiceExamGkl::where('course',$exam_time->course)->first();
//            $exam_time = $exam_time->toArray();
            $exam_time->meta = $exam_meta;
            $exam_time->gkl = $gkl == null?null:$gkl->gkl;
            array_push($data,$exam_time->toArray());
        }
        if(count($data)>0){
            return $this->response->array(['data'=>$data])->setStatusCode(200);
        }else{
            return $this->response->errorNotFound("对不起，未获取到学号为{$sdut_id}考试时间信息");
        }
    }

    public function elec(Request $request){
        $school = $request->school;
        $dormitory = $request->dormitory;
        $room = $request->room;
        $jar = new CookieJar();
        $client = new Client();

        $login_url = 'http://hqfw.sdut.edu.cn/login.aspx';  //获取登录参数
        $elec_url = 'http://hqfw.sdut.edu.cn/stu_elc.aspx';  //查询地址

        //获取登录参数
        $res = $client->request('GET',$login_url,['cookies'=>$jar]);
        $ql = QueryList::html($res->getBody());
        $viewstate =$ql->find('#__VIEWSTATE')->val();
        $event = $ql->find('#__EVENTVALIDATION')->val();
        $client->request('POST',$login_url,[
            'cookies'=>$jar,
            'form_params'=> [
                '__VIEWSTATE'=>$viewstate,
                '__EVENTVALIDATION'=>$event,
                'ctl00$MainContent$txtName'=>'孙骞',
                'ctl00$MainContent$txtID'=>'15110201098',
                'ctl00$MainContent$btnTijiao'=>'登录'
            ],
        ]);
        $res = $client->request('GET',$elec_url,['cookies'=>$jar]);
        $ql = QueryList::html($res->getBody());
        $viewstate =$ql->find('#__VIEWSTATE')->val();
        $event = $ql->find('#__EVENTVALIDATION')->val();
        if ($school == 1){
            //西校区
            $building='ctl00$MainContent$buildingwest';
            $campus='1';
        }else{
            $building='ctl00$MainContent$buildingeast';
            $campus='0';
            $post2=array(
                '__VIEWSTATE'=>$viewstate,
                '__EVENTVALIDATION'=>$event,
                'ctl00$MainContent$campus'=>$campus,
            );
            $res = $client->request('POST',$elec_url,[
                'cookies'=>$jar,
                'form_params'=>$post2
            ]);
            $ql = QueryList::html($res->getBody());
            $viewstate =$ql->find('#__VIEWSTATE')->val();
            $event = $ql->find('#__EVENTVALIDATION')->val();
        }

        $post2=array(
            '__VIEWSTATE'=>$viewstate,
            '__EVENTVALIDATION'=>$event,
            'ctl00$MainContent$campus'=>$campus,
            $building=>$dormitory,
            'ctl00$MainContent$roomnumber'=>$room,
            'ctl00$MainContent$Button1'=>'查询',
        );
        $res = $client->request('POST',$elec_url,[
            'cookies' => $jar,
            'form_params' => $post2,
            'http_errors' => false,
        ]);
        $res = $res->getBody();
        str_replace('/\r\n/','',$res);
        preg_match_all('#您所查询的房间为：([^<>]+)。\r\n 在([^<>]+)时，所余电量为：([^<>]+)度。\r\n 根据您的用电规律，所余电量可用 ([^<>]+)天。\r\n 当前用电状态为：([^<>]+)。#', $res, $value);
        if (isset($value[0])&&!empty($value[0])){
            return $this->response->array(['data'=>['room'=>$value[1][0],'time'=>$value[2][0],'elec'=>$value[3][0],'remain'=>$value[4][0],'status'=>$value[5][0]]]);
        }else{
            return $this->response->error('所查询房间不存在或服务器错误',404);
        }


    }

    public function test(Request $request){

//        $this->loginJWC($request->sdut_id,$request->password_jwc);
//        echo $this->loginEhall($request->sdut_id,$request->password_jwc);
        //创建cookie

    }

    public function cetGet(Request $request) {
        $validator = app('validator')->make($request->all(),[
            'number'=>'required|size:15'
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException("请输入完整的准考证号",$validator->errors());
        }
        $cookie = new CookieJar();
        $client = new Client();
        //获取客户端访问ip
        $client_ip = $this->getip();
        $cetUrl = "http://cet.neea.edu.cn/cet";
        //获取输入的准考证号
        $number = $request->number;
        $url = "http://cache.neea.edu.cn/Imgs.do?c=CET&ik={$number}&t=".rand();
        $res = $client->request('GET',$url,[
            'cookies' => $cookie,
            'headers' => [
                'Referer' => $cetUrl,
                'CLIENT-IP'=> $client_ip,
                'X-FORWARDED-FOR'=> $client_ip,
            ],
            'http_errors' => false,
        ]);
        preg_match("/result.imgs\(\"([^<>]+)\"\);/",$res->getBody(),$values);
        return $this->response->array([
            'data'=>[
                'img' => $values[1],
            ],
            'cookies' => $cookie->toArray(),
        ])->setStatusCode(200);
    }

    public function cetPost(Request $request)
    {
        //准考证对应的代码，地址：http://cet.neea.edu.cn/cet/js/query.js  第5行
        $zRule = ["","CET4-D","CET6-D","CJT4-D","CJT6-D","PHS4-D","PHS6-D","CRT4-D","CRT6-D","TFU4-D"];
        $validator = app('validator')->make($request->all(), [
            'number' => 'required|size:15',
            'name' => 'required',
            'code' => 'required',
        ]);
        if ($validator->fails()) {
            throw new StoreResourceFailedException("请输入完整的准考证号", $validator->errors());
        }
        $cookies = json_decode($request->header('Cookies'), true);
        $jar = new CookieJar(false, $cookies);
        $client = new Client(['cookies' => $jar]);
        //获取客户端访问ip
        $client_ip = $this->getip();
        $dataUrl = "http://cet.neea.edu.cn/cet/js/data.js";
        $res = $client->request('GET',$dataUrl);
        if ($res->getStatusCode() != 200) {
            return $this->response->error('源服务器错误，请联系管理员',$res->getStatusCode());
        }
        preg_match("/var dq=([^<>]+);/",$res->getBody(),$dq);
        $dq = json_decode($dq[1]);
        //查看准考证类型
        $idx = -1;
        $t = $request->number[0];
        if($t=="F"){
            $idx = 1;
        }else if($t=="S"){
            $idx = 2;
        }else{
            $t = (int)$request->number[9];
            if($t) {
                $idx = $t;
            }
        }
        if($idx!=-1){
            $code = $zRule[$idx];
        }else {
        	throw new StoreResourceFailedException("准考证有误");
        }
        $index = -1;
        foreach ($dq->rdsub as $key => $value) {
            if ($value->code == $code) {
                $index = $key;
                break;
            }
        }
        if ($index == -1) {
            throw new StoreResourceFailedException('准考证有误');
        }
        $c = $dq->rdsub[$index]->tab;


        $cetUrl = "http://cache.neea.edu.cn/cet/query";
        $res = $client->request('POST', $cetUrl, [
            'form_params' => [
                'data' => $c.',' . $request->number . ',' . $request->name,
                'v' => $request->code,
            ],
            'headers' => [
                'Referer' => "http://cache.neea.edu.cn/cet",
                'CLIENT-IP' => $client_ip,
                'X-FORWARDED-FOR' => $client_ip,
                'Origin' => "http://cet.neea.edu.cn",
            ]
        ]);


        if ($res->getStatusCode() != 200) {
            return $this->response->error('源服务器错误，请联系管理员',$res->getStatusCode());
        }
        preg_match("/parent.result.callback\(\"([^<>]+)\"\);/", $res->getBody(), $value);
        if (sizeof($value) < 2) {
            return $this->response->error("服务器错误，请联系管理员",500);
        }
        //解析非标准json
        $data = $this->ext_json_decode($value[1]);
        if (property_exists($data,"error")) {
            throw new StoreResourceFailedException($data->error);
        }
        $data->type = $dq->rdsub[$index]->name;
        return $this->response->array(['data'=>$data]);
    }


    //非标准JSON字符串转化为对象：键没有用双引号包裹，值用单引号包裹
    protected function ext_json_decode($str, $mode=false){
        //将键用双引号包裹
        if(preg_match('/\w:/', $str)){
            $str = preg_replace('/(\w+):/is', '"$1":', $str);
        }
        //把双引号变为单引号
        $str = preg_replace('/\'/', '"', $str);
        return json_decode($str, $mode);
    }


    protected function loginJWC($sdut_id,$password) {
        $jar = new CookieJar();
        $client = new Client();
        $res = $client->request('GET',"http://210.44.191.124/jwglxt/xtgl/login_slogin.html",[
            'cookies' => $jar,
        ]);
        $csrf = QueryList::html($res->getBody())->find('#csrftoken')->val();
        $rsakey = $client->request('GET','http://210.44.191.124/jwglxt/xtgl/login_getPublicKey.html',[
            'cookies' => $jar,
        ]);
        $rsainfo = json_decode($rsakey->getBody());
        $rsa = new RSA();
        $publicKey = array(
            'n' => new BigInteger(Base64::b64tohex($rsainfo->modulus), 16),
            'e' => new BigInteger(Base64::b64tohex($rsainfo->exponent), 16),
        );
        $rsa->loadKey($publicKey);
        $rsa->setEncryptionMode(2);
        $en_pwd = $rsa->encrypt($password);
        $en_pwd = bin2hex($en_pwd);
        $password = Base64::hex2b64($en_pwd);
//        return $password;
        $res = $client->request('POST',"http://210.44.191.124/jwglxt/xtgl/login_slogin.html",[
            'cookies' => $jar,
            'form_params' => [
                'csrftoken' => $csrf,
                'yhm' => $sdut_id,
                'mm' => $password,
                'mm' => $password,
            ],
            'http_errors'=>false,
        ]);
        if (preg_match("/修改密码/",$res->getBody()) == 1) {
            //证明登录成功，返回cookie
            return $jar;
        }else  {
            //未匹配成功，登录失败
            return null;
        }
    }

    protected function loginEhall($sdut_id,$password) {
        $jar = new CookieJar();
        $client = new Client(['cookies'=>$jar]);
        $login_url = "http://authserver.sdut.edu.cn/authserver/login?service=http%3A%2F%2Fehall.sdut.edu.cn%2Flogin%3Fservice%3Dhttp%3A%2F%2Fehall.sdut.edu.cn%2Fnew%2Fehall.html";
        $res = $client->request('GET',$login_url);
        if ($res->getStatusCode()!=200) {
            return $this->response->error('源服务器错误',500);
        }
        $ql = QueryList::html($res->getBody());
        $lt = $ql->find("input[name='lt']")->val();
        $dtlt = $ql->find("input[name='dllt']")->val();
        $execution = $ql->find("input[name='execution']")->val();
        $_evenId = $ql->find("input[name='_eventId']")->val();
        $rmShown = $ql->find("input[name='rmShown']")->val();
        $client->request('POST',$login_url,[
            'form_params'=>[
                'username' => $sdut_id,
                'password' => $password,
                'lt' => $lt,
                'dllt' => $dtlt,
                'execution' => $execution,
                '_eventId' => $_evenId,
                'rmShown' => $rmShown,
            ],
            'http_errors'=>false,
        ]);
        $result = $client->request('GET',"http://ehall.sdut.edu.cn/xsfw/sys/swpubapp/userinfo/getConfigUserInfo.do");
        //登录成功将返回json字符串，失败返回html文件
        $data = json_decode($result->getBody());
        //如果正常解析，证明登录成功，返回jar
        if (($data && (is_object($data))) || (is_array($data) && !empty($data))) {
            return $jar;
        }else {
            return null;
        }

//        $client->request('GET','http://ehall.sdut.edu.cn/xsfw/sys/emappagelog/config/sswsapp.do');
//        $result = $client->request('GET',"http://ehall.sdut.edu.cn/xsfw/sys/sswsapp/modules/dorm_health_student/sswsxs_sswsxsbg.do",[
//            'http_errors'=>false,
//        ]);
//        return $result->getBody();
    }

    protected  function getip(){
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){
            $cip = $_SERVER['HTTP_CLIENT_IP'];
        }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $cip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }elseif(!empty($_SERVER['REMOTE_ADDR'])){
            $cip = $_SERVER['REMOTE_ADDR'];
        }else{
            $cip = '';
        }
        return $cip;
    }
}
