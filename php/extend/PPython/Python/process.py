# -*- coding: UTF-8 -*-

# -------------------------------------------------
#    请不要随意修改文件中的代码
# -------------------------------------------------


import sys,time,threading,socket,json
import importlib

isPy2 = False

if sys.getdefaultencoding() != 'utf-8':
    if( int(sys.version[0:1]) >= 3 ):
        isPy2 = True
        from imp import reload
    reload(sys)
    sys.setdefaultencoding('utf-8')

import php_python

REQUEST_MIN_LEN = 10    #合法的request消息包最小长度    
TIMEOUT = 180           #socket处理时间180秒

pc_dict = {}        #预编译字典，key:调用模块、函数、参数字符串，值是编译对象
global_env = {}     #global环境变量

def index(bytes, c, pos=0):
    """
    查找c字符在bytes中的位置(从0开始)，找不到返回-1
    pos: 查找起始位置
    """
    for i in range(len(bytes)):
        if (i <= pos):
            continue
        if bytes[i] == c:
            return i
            break
    else:
        return -1

def parse_php_req(p):
    params = json.loads(p)
    """
    解析PHP请求消息
    返回：元组（模块名，函数名，入参list）
    """
    modul_func = params[0]      #第一个元素是调用模块和函数名
    print("模块和函数名:%s" % modul_func)
    print("参数:%s" % params[1:])
    pos = modul_func.find("::")
    modul = modul_func[:pos]    #模块名
    func = modul_func[pos+2:]   #函数名
    return modul, func, params[1:]

class ProcessThread(threading.Thread):
    """
    preThread 处理线程
    """
    def __init__(self, socket):
        threading.Thread.__init__(self)

        #客户socket
        self._socket = socket

    def run(self):

        #---------------------------------------------------
        #    1.接收消息
        #---------------------------------------------------

        try:  
            self._socket.settimeout(TIMEOUT)                  #设置socket超时时间
            firstbuf = self._socket.recv(16 * 1024)           #接收第一个消息包(bytes)
            if len(firstbuf) < REQUEST_MIN_LEN:               #不够消息最小长度
                print ("非法包，小于最小长度: %s" % firstbuf)
                self._socket.close()
                return

            if isPy2:
                firstComma = index(firstbuf, ',')                #查找第一个","分割符  0x2c
            else:
                firstComma = index(firstbuf, 0x2c)

            totalLen = int(firstbuf[0:firstComma])            #消息包总长度
            print("消息长度:%d" % totalLen)
            reqMsg = firstbuf[firstComma+1:]
            while (len(reqMsg) < totalLen):    
                reqMsg = reqMsg + self._socket.recv(16 * 1024)

            #调试
            print ("请求包：%s" % reqMsg)

        except Exception as e:  
            print ('接收消息异常', e)
            self._socket.close()
            return

        #---------------------------------------------------
        #    2.调用模块、函数检查，预编译。
        #---------------------------------------------------

        #从消息包中解析出模块名、函数名、入参list
        modul, func, params = parse_php_req(reqMsg)
        # print(__import__ (modul,globals(), locals(), [], -1))
        if (modul not in pc_dict):   #预编译字典中没有此编译模块
            #检查模块、函数是否存在
            try:
                if isPy2:
                    callMod = __import__(modul,globals(),locals(),[],-1)    #根据module名，反射出module
                else:
                    callMod = importlib.import_module(modul)

                pc_dict[modul] = callMod        #预编译字典缓存此模块
            except Exception as e:
                print ('模块不存在:%s' % modul)
                self._socket.sendall(("F" + "module '%s' is not exist!" % modul).encode(php_python.CHARSET)) #异常
                self._socket.close()
                return
        else:
            callMod = pc_dict[modul]            #从预编译字典中获得模块对象

        try:
            callMethod = getattr(callMod, func)
        except Exception as e:
            print ('函数不存在:%s' % func)
            self._socket.sendall(("F" + "function '%s()' is not exist!" % func).encode(php_python.CHARSET)) #异常
            self._socket.close()
            return

        #---------------------------------------------------
        #    3.Python函数调用
        #---------------------------------------------------

        try: 
            params = ','.join([repr(x) for x in params])
            #加载函数
            compStr = "import %s\nret=%s(%s)" % (modul, modul+'.'+func, params)
            rpFunc = compile(compStr, "", "exec")
            
            if func not in global_env: 
                global_env[func] = rpFunc   
            local_env = {}
            exec (rpFunc, global_env, local_env)     #函数调用
        except Exception as e:  
            print ('调用Python业务函数异常', e )
            errType, errMsg, traceback = sys.exc_info()
            self._socket.sendall(("F%s" % errMsg).encode(php_python.CHARSET)) #异常信息返回
            self._socket.close()
            return

        #---------------------------------------------------
        #    4.结果返回给PHP
        #---------------------------------------------------
        rspStr = json.dumps(local_env['ret'])

        try:
            #加上成功前缀'S'
            rspStr = "S" + rspStr
            #调试
            #print ("返回包：%s" % rspStr)
            self._socket.sendall(rspStr.encode(php_python.CHARSET))
        except Exception as e:  
            print ('发送消息异常', e)
            errType, errMsg, traceback = sys.exc_info()
            self._socket.sendall(("F%s" % errMsg).encode(php_python.CHARSET)) #异常信息返回
        finally:
            self._socket.close()
            return

    def close(self):
        self._socket.close()
        return