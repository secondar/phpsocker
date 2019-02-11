using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;
using System.Net;
using System.Net.Sockets;
using CsharpJson;
using System.Threading;



namespace WindowsFormsApp1
{
    public partial class Form1 : Form
    {
        bool b = false;
        IPAddress ip = IPAddress.Parse("192.168.1.85");
        Socket clientSocket = new Socket(AddressFamily.InterNetwork, SocketType.Stream, ProtocolType.Tcp);
        public Form1()
        {
            InitializeComponent();
            Control.CheckForIllegalCrossThreadCalls = false;
        }

        private void Form1_Load(object sender, EventArgs e)
        {
            
            try
            {
                clientSocket.Connect(new IPEndPoint(ip, 8080)); //配置服务器IP与端口
                txt_msg.AppendText("连接服务器成功\n");
                Thread thread = new Thread(start);
                thread.Start();
                JsonObject obj = new JsonObject();
                obj.Add("type", "login");
                obj.Add("name", "c#");
                obj.Add("uid", 123);
                JsonDocument doc1 = new JsonDocument();
                doc1.Object = obj;
                string val = doc1.ToJson();
                clientSocket.Send(Encoding.Default.GetBytes(val));
                clientSocket.Send(Encoding.Default.GetBytes(val));
            }
            catch
            {
                txt_msg.AppendText("连接服务器失败\n");
                return;
            }
        }
        private void start()
        {
            int receiveLength = 0;
            while (true)
            {
                try
                {
                    byte[] result = new byte[1024];
                    receiveLength = clientSocket.Receive(result);
                    
                    if (receiveLength == 0)
                    {
                        continue;
                    }

                    JsonDocument doc = JsonDocument.FromString(Encoding.Default.GetString(result, 2, receiveLength));
                    
                    if (doc.IsObject())
                    {
                        
                        JsonObject jsobj = doc.Object;
                        
                        if (jsobj["type"].ToString()== "system")
                        {
                            string msg = "系统消息:";

                            if (jsobj["from"].ToString()== "login")
                            {
                                if (jsobj["content"].ToString() != "ok")
                                {
                                    txt_msg.AppendText(msg+"登录失败,可能由用户名或ID不可用" +"\n");
                                }
                                else
                                {
                                    
                                    this.txt_msg.Invoke(new Action(() =>
                                    {
                                        txt_msg.AppendText(msg + "登录成功" + "\n");
                                        txt_msg.AppendText(msg + "当前在线人数 " + jsobj["user_list_count"].ToInt() + "人\n");
                                        txt_msg.AppendText(msg + jsobj["user_list"].ToArray() + "人\n");
                                        var a = jsobj["user_list"].ToArray();
                                        for(int i = 0; i< a.Count;i++)
                                        {
                                            var b = a[i].ToObject();
                                            switch (b["uid"].Valuetype)
                                            {
                                                case JsonType.STRING:
                                                     txt_msg.AppendText(b["uid"].ToString() + "" + "\n");
                                                    break;
                                                case JsonType.NUMBER:
                                                    txt_msg.AppendText(b["uid"].ToInt() + "" + "\n");
                                                    break;
                                            }
                                            
                                        }
                                        

                                        //jsobj["user_list"].ToArray();
                                    }));
                                }
                                
                            }

                        }
                        else
                        {
                            string msg = "";
                            if (jsobj["type"].ToString() == "user_msg")
                            {
                                if (jsobj["msg_type"].Valuetype == JsonType.STRING)
                                {
                                    msg = unicode(jsobj["from"].ToString()) + " : ";
                                }
                                else
                                {
                                    msg = jsobj["from"].ToInt() + " : ";
                                }
                                if (jsobj["msg_type"].ToString()=="txt")
                                {
                                    if (jsobj["msg_type"].Valuetype == JsonType.STRING)
                                    {
                                        msg += unicode(jsobj["content"].ToString());
                                    }else if (jsobj["msg_type"].Valuetype == JsonType.NUMBER)
                                    {
                                        msg += jsobj["content"].ToString();
                                    }
                                    
                                }
                            }
                            txt_msg.AppendText(msg + "\r\n");
                        }
                        
                    }
                    else
                    {
                        Console.WriteLine(Encoding.Default.GetString(result, 2, receiveLength-2) + "\n");
                        //clientSocket.Send(buffer);
                    }
                }
               catch (Exception e)
                {
                    txt_msg.AppendText("与服务端断开连接\n");
                    break;
                }  
                //txt_msg.AppendText("接收服务器消息：{0}", Encoding.ASCII.GetString(result, 0, receiveLength));
            }
        }

        private void upui()
        {
            txt_msg.Text = DateTime.Now.ToLongTimeString();  //为同一线程则直接刷新label1
        }
        private string unicode (string str)
        {
            //UNICODE字符转为中文

            //str = "//u4e2d//u6587";
            string outStr = "";
            if (!string.IsNullOrEmpty(str))
            {
                string[] strlist = str.Replace("\\", "").Split('u');
                try
                {
                    for (int i = 1; i < strlist.Length; i++)
                    {
                        //将unicode字符转为10进制整数，然后转为char中文字符 
                        outStr += (char)int.Parse(strlist[i], System.Globalization.NumberStyles.HexNumber);
                    }
                }
                catch (FormatException ex)
                {
                    outStr = ex.Message;
                }
            }
            return outStr;
        }

        private void btn_sen_Click(object sender, EventArgs e)
        {
            //JsonObject obj = new JsonObject();
            //obj.Add("type", "login");
            //obj.Add("name", "c#");
            //obj.Add("uid", 121235673);
            //JsonDocument doc1 = new JsonDocument();
            //doc1.Object = obj;
            //string val = doc1.ToJson();
            //clientSocket.Send(Encoding.Default.GetBytes(val));
        }
    }
}
