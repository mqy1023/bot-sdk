<?php
/**
 * 封装Bot对中控的返回结果
 * @author yuanpeng01@baidu.com
 **/
namespace Baidu\Duer\Botsdk;

class Response{
    /**
     * Requset 实例。中控的请求
     **/
    private $request;

    /**
     * Session
     **/
    private $session;

    /**
     * Nlu
     **/
    private $nlu;

    /**
     * 返回结果的标识。
     **/
    private $sourceType;

    /**
     * 对中控的confirm标识。标识是否需要对中控confirm
     **/
    private $confirm;

    /**
     * 多轮情况下，是否需要client停止对用户的等待输入
     **/
    private $shouldEndSession;

    /**
     * @param Request $request
     * @param Session $session
     * @param Nlu $nlu
     * @return null
     **/
    public function __construct($request, $session, $nlu){
        $this->request = $request;
        $this->session = $session;
        $this->nlu = $nlu;
        $this->sourceType = $this->request->getBotName();
    }

    /**
     * @param null
     * @return null
     **/
    public function setConfirm(){
        $this->confirm = 1; 
    }

    /**
     * @param null
     * @return null
     **/
    public function setShouldEndSession($val){
        if($val === false) {
            $this->shouldEndSession = false; 
        }
    }

    /**
     * @deprecated
     * @param array $views
     * @return array
     **/
    private function convertViews2ResultList($views){
        $sourceType = $this->sourceType;
        $resultList=array_map(function($view) use($sourceType){
            if($view['type']=="txt"){
                return [
                    'result_confidence' => 100,
                    'source_type' => $sourceType,
                    'voice' => $view['content'],
                    'result_type'=>"txt",
                    "result_content"=>[
                        'answer'=>$view['content'],
                    ],
                ];
            }
            if($view['type']=="list"){
                return [
                    "result_type"=>"multi_news",
                    'source_type' => $sourceType,
                    'voice' => $view['content'],
                    'result_confidence' => 100,
                    "result_content"=>[
                        "objects"=>array_map(function($item){
                            return array_filter([
                                "title"=>$item['title'],
                                "desc"=>$item['summary'],
                                "url"=>$item['url'],
                                "img_url"=>$item['image'],
                            ]);
                        }, $view['list']),
                    ],
                ];
            }
            return null;
        },$views);
        $resultList=array_values(array_filter($resultList));
        return $resultList;
    }
        

    /**
     * @desc 当没有结果时，返回默认值
     * @param null
     * @return null
     **/
    public function defaultResult(){
        return json_encode(['status'=>0, 'msg'=>null]);
    }

    /** 
     * @param array $data
     * $data =
     * card: 可选
     * directives: 可选
     * resource: 可选
     * outputSpeech: 必选
     *
     * @return string
     */
    public function build($data){
        if($this->shouldEndSession === false 
            || ($this->shouldEndSession !== true && $this->nlu && $this->nlu->hasAsk())){

            $this->should_end_session = false;
        }

        $directives = $data['directives'] ? $data['directives'] : [];
        if($this->nlu){
            $directives[] = $this->nlu->toDirective();
        }

        if(!$data['outputSpeech'] && $data['card'] && $data['card']['type'] == 'txt') {
            $data['outputSpeech'] = $data['card']['content'];
        }

        $ret = [
            'version' => '2.0',
            'context' => [
                'updateIntent' =>$this->nlu ? $this->nlu->toUpdateIntent() : null, 
            ],
            'session' => $this->session->toResponse(),
            'response' => [
                'needDetermine' => $this->confirm ? true : false,
                'directives' => $directives,
                'shouldEndSession' => $this->shouldEndSession,
                'card' => $data['card'],
                'resource' => $data['resource'],
                'outputSpeech' => $data['outputSpeech']?$this->formatSpeech($data['outputSpeech']):null,
                'reprompt' => $data['reprompt']?[
                    'outputSpeech' => $this->formatSpeech($data['reprompt']),
                ]:null
            ]
        ];

        
        $str=json_encode($ret, JSON_UNESCAPED_UNICODE);
        return $str;
    }

    /**
     * @desc 通过正则<speak>..</speak>，判断是纯文本还是ssml，生成对应的format
     * @param string|array $mix
     * @return array
     **/
    public function formatSpeech($mix){
        if(is_array($mix)) {
            return $mix; 
        }

        if(preg_match('/<speak>/', $mix)) {
            return [
                'type' => 'ssml',
                'ssml' => $mix,
            ]; 
        }

        return [
            'type' => 'text',
            'text' => $mix,
        ];
    }
}
