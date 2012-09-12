<?php
/**
*ִ���࣬�������
*����������һ��ʱ��������ˢ��ԤԼ����һֱ���趨��ʱ��μ�⵽�п�ԤԼ״̬���ύԤԼ��
*�����е���hasFreeTime�����������趨һ�켸��ԤԼʱ��ε����ȼ�
*@author bitpanda
*/

	include("configure.php");
	include("utils.php");

	$isSuccess = false;
	
	$configure = new configure();
	$LOG = new Log();
	
	$dfss = new dfss($LOG,$configure);

	while(!$isSuccess)
	{
		if(!$dfss->isLogin)
			$dfss->login();
		if(!$dfss->isLogin)
		{
			$LOG->log("Relogin failed,sleep 1s");
			sleep(1);
			continue;
		}
		
		$dfss->accessToData();
		$LOG->log("��Լ��ҳ��");
		while(!$isSuccess && $dfss->isLogin)
		{
			$LOG->log("ˢ��Լ��ҳ��");
			if($dfss->refreshData())
			{
				$LOG->log("�ж����޿���ʱ���");
				if($dfss->hasFreeTime())
				{
					$LOG->log("�ύ��");
					if($dfss->postData())
					{
						$LOG->log("Լ���ɹ����˳�");
						$isSuccess = true;
						break;
					}
					else
					{
						$LOG->log("Լ��ʧ�ܣ�POSTERROR");	
					}
				}
				$LOG->log("�޿���ʱ���,3s��ˢ��");
			//	sleep(3);
			}
			else
			{
				//$dfss->isLogin = false;
				$LOG->log("Refresh data error,sleep 5s");
				sleep(3);
				continue;
			}
		}
		sleep(3);
	}
	
class dfss
{	
	var $isLogin = false;

	public function __construct($log = "", $configure="")
    {
        $this->isLogin = false;
		$this->configure = $configure;
		$this->LOG = $log;
		$this->init();
    }
	
	public function init()
	{
		$this->ch = curl_init();
		// ��ȡ����Ϣ���ļ�������ʽ���أ�������ֱ�������
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER,1);
		
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->configure->header);
		curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:11.0) Gecko/20100101 Firefox/11.0');
		curl_setopt($this->ch, CURLOPT_COOKIESESSION, 1);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 5);
		//get cookie info
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->configure->cookieFile);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->configure->cookieFile);
	}
	
	public function login()
	{
		$this->LOG->log("login....");
		$post = $this->getLoginParameters();
		
		if($post != null)
		{
			curl_setopt($this->ch, CURLOPT_URL, $this->configure->loginUrl);
		
			//����һ�������POST��������Ϊ��application/x-www-form-urlencoded��������ύ��һ����
			curl_setopt($this->ch, CURLOPT_POST, 1);
			curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
			
			$loginResult = curl_exec($this->ch);

			if ($loginResult == NULL) { 
				$this->LOG->log("��������ʧ��");
			}
			else
			{
				$this->LOG->saveFile($loginResult, $this->configure->login_result_html);
				$analyze = new analyze($loginResult);
				$this->isLogin = $analyze->isLoginSuccess();
			}
		}
	}
	
	public function getLoginParameters()
	{
		$u = $this->configure->username;
		$p = $this->configure->password;
		
		//���ӵ�½ҳ���ȡ��Ԫ�أ�����post����
		curl_setopt($this->ch, CURLOPT_URL, $this->configure->loginUrl);
		curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
		
        $loginHtml = curl_exec($this->ch);
		
		//��ȡ��֤��ͼƬ
		curl_setopt($this->ch, CURLOPT_URL, $this->configure->captchaUrl);
		curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
		
        $captchaImg = curl_exec($this->ch);
		
		if($loginHtml == null || $captchaImg == null)
		{
			$this->LOG->log("��������ʧ��");
			return null;
		}
		else
		{
			$this->LOG->log("��������ɹ�");
			$this->LOG->saveFile($loginHtml, $this->configure->login_html);
			$this->LOG->saveFile($captchaImg, $this->configure->captcha_png);
			
			$analyze = new analyze($loginHtml);
			
			$viewState = urlencode($analyze->getViewState());
			$eventValidation = urlencode($analyze->getEventValidation());
			$c = $this->decaptcha();

			return "__VIEWSTATE={$viewState}".
				"&txtname={$u}".
				"&txtpwd={$p}".
				"&yanzheng={$c}".
				"&button.x=0&button.y=0".
				"&__EVENTVALIDATION={$eventValidation}";
		}

	}
	
	public function decaptcha()
	{
	
		exec("{$this->configure->tesseract_path} {$this->configure->captcha_png} {$this->configure->decode_path}");
	
		$handle = fopen($this->configure->decode_path.'.txt', 'r'); 

		if($handle != null)
		{		
			$line = fgets($handle);
			$this->LOG->log("�Զ�ʶ����֤�룺".substr($line, 0, 5));
			return substr($line, 0, 5);
		}	
		exec($this->configure->captcha_png);
		$handle = fopen('php://stdin', 'r');  
		echo "��������֤�룺";  
		$line = fgets($handle);
		$this->LOG->log("�ֶ�������֤�룺".substr($line, 0, 5));
		return substr($line, 0, 5);
	}
	
	public function accessToData()
	{
		curl_setopt($this->ch, CURLOPT_URL, $this->configure->yuecheUrl);
		curl_setopt($this->ch, CURLOPT_HTTPGET, 1);

        $reserveHtml = curl_exec($this->ch);
		if($reserveHtml == null)
		{
			$this->LOG->log("Լ��ҳ���ʧ��");
			$this->isLogin = false;
		}
		else
		{
			$this->LOG->saveFile($reserveHtml, $this->configure->yueche_html);
			$analyze = new analyze($reserveHtml);
			if($analyze->isOpenReserveSuccess())
			{
				$this->reserve_V = urlencode($analyze->getViewState());
				$this->reserve_E = urlencode($analyze->getEventValidation());
			}
			else
			{
				$this->LOG->log("Լ��ҳ�����".$analyze->getErrorMsg());
				$this->isLogin = false;
			}
		}
	}
	
	public function refreshData()
	{
		$post = "__EVENTTARGET="
			."&__EVENTARGUMENT="
			."&__LASTFOCUS="
			."&__VIEWSTATE={$this->reserve_V}"
			."&RadioButtonList1={$this->configure->refreshField}"
			."&btnRefresh={$this->configure->refreshField}"
			."&__EVENTVALIDATION={$this->reserve_E}";

		curl_setopt($this->ch, CURLOPT_URL, $this->configure->yuecheUrl);
		curl_setopt($this->ch, CURLOPT_POST, 1);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);

        $refreshHtml = curl_exec($this->ch);	
		
		if($refreshHtml == null)
		{
			$this->LOG->log("ˢ��Լ����ʱʧ��");
			$this->isLogin = false;
			return false;
		}
		else
		{
			$this->LOG->saveFile($refreshHtml, $this->configure->refresh_html);
			$analyze = new analyze($refreshHtml);
			if($analyze->isOpenReserveSuccess())
			{
				$this->reserve_V = urlencode($analyze->getViewState());
				$this->reserve_E = urlencode($analyze->getEventValidation());
				
				$this->reserveArray = $analyze->getReserveArray();
				
				return true;
			}
			else
			{
				$this->LOG->log("ˢ��Լ��ҳ�����".$analyze->getErrorMsg());
				$this->isLogin = false;
				return false;
			}
		}
	}
	
	public function hasFreeTime()
	{
		$dateList = $this->configure->dateList;
		$dateInfoList = $this->reserveArray;
		
		$this->LOG->log("�����жϣ�");
		
		foreach($dateInfoList as $dateInfo)
		{
			print_r($dateInfo);
			
			foreach($dateList as $date)
			{
				if(strcmp("{$dateInfo->date}", "{$date}")==0)
				{
					$this->LOG->log($dateInfo->date." ".$date);
					if($dateInfo->isLeft())
					{
						$this->LOG->log($dateInfo->date."��ʣ��");
						if($dateInfo->value1 > 0)
						{
							$this->selectValue = urlencode($dateInfo->value1);
							$this->selectName = urlencode($dateInfo->name1);
						}
						elseif($dateInfo->value2 > 0)
						{
							$this->selectValue = urlencode($dateInfo->value2);
							$this->selectName = urlencode($dateInfo->name2);
						}
						elseif($dateInfo->value3 > 0)
						{
							$this->selectValue = urlencode($dateInfo->value3);
							$this->selectName = urlencode($dateInfo->name3);
						}
						elseif($dateInfo->value4 > 0)
						{
							$this->selectValue = urlencode($dateInfo->value4);
							$this->selectName = urlencode($dateInfo->name4);
						}
						elseif($dateInfo->value5 > 0)
						{
							$this->selectValue = urlencode($dateInfo->value5);
							$this->selectName = urlencode($dateInfo->name5);
							
							return false;
						}

						return true;
					}

				}
			}
		}
	
		return false;
	}
	
	public function postData()
	{
		$post = "__EVENTTARGET="
			."&__EVENTARGUMENT="
			."&__LASTFOCUS="
			."&__VIEWSTATE={$this->reserve_V}"
			."&RadioButtonList1={$this->configure->postField}"
			."&{$this->selectName}={$this->selectValue}"
			."&__EVENTVALIDATION={$this->reserve_E}";

		curl_setopt($this->ch, CURLOPT_URL, $this->configure->yuecheUrl);
		curl_setopt($this->ch, CURLOPT_POST, 1);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);

        $postResultHtml = curl_exec($this->ch);	
		
		if($postResultHtml == null)
		{
			$this->LOG->log("�ύ��ʱʧ�ܣ�ҳ��Ϊ��");
			return false;
		}
		else
		{
			$this->LOG->saveFile($postResultHtml, $this->configure->yueche_result_html);
			$analyze = new analyze($postResultHtml);
			if($analyze->isReserveSuccess())
			{
	//			$this->reserve_V = urlencode($analyze->getViewState());
	//			$this->reserve_E = urlencode($analyze->getEventValidation());
				
	//			$this->reserveArray = $analyze->getReserveArray();
				return true;
			}
			else
			{
				$this->LOG->log("�ύ������".$analyze->getErrorMsg());
				return false;
			}
		}
	}
	
}
	
?>