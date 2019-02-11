namespace WindowsFormsApp1
{
    partial class Form1
    {
        /// <summary>
        /// 必需的设计器变量。
        /// </summary>
        private System.ComponentModel.IContainer components = null;

        /// <summary>
        /// 清理所有正在使用的资源。
        /// </summary>
        /// <param name="disposing">如果应释放托管资源，为 true；否则为 false。</param>
        protected override void Dispose(bool disposing)
        {
            if (disposing && (components != null))
            {
                components.Dispose();
            }
            base.Dispose(disposing);
        }

        #region Windows 窗体设计器生成的代码

        /// <summary>
        /// 设计器支持所需的方法 - 不要修改
        /// 使用代码编辑器修改此方法的内容。
        /// </summary>
        private void InitializeComponent()
        {
            this.tv_list = new System.Windows.Forms.TreeView();
            this.txt_msg = new System.Windows.Forms.TextBox();
            this.txt_sen = new System.Windows.Forms.TextBox();
            this.btn_sen = new System.Windows.Forms.Button();
            this.SuspendLayout();
            // 
            // tv_list
            // 
            this.tv_list.Location = new System.Drawing.Point(1, 1);
            this.tv_list.Name = "tv_list";
            this.tv_list.Size = new System.Drawing.Size(98, 459);
            this.tv_list.TabIndex = 31;
            // 
            // txt_msg
            // 
            this.txt_msg.Location = new System.Drawing.Point(105, 1);
            this.txt_msg.Multiline = true;
            this.txt_msg.Name = "txt_msg";
            this.txt_msg.Size = new System.Drawing.Size(406, 324);
            this.txt_msg.TabIndex = 32;
            // 
            // txt_sen
            // 
            this.txt_sen.Location = new System.Drawing.Point(105, 340);
            this.txt_sen.Multiline = true;
            this.txt_sen.Name = "txt_sen";
            this.txt_sen.Size = new System.Drawing.Size(406, 75);
            this.txt_sen.TabIndex = 33;
            // 
            // btn_sen
            // 
            this.btn_sen.Location = new System.Drawing.Point(105, 421);
            this.btn_sen.Name = "btn_sen";
            this.btn_sen.Size = new System.Drawing.Size(395, 39);
            this.btn_sen.TabIndex = 34;
            this.btn_sen.Text = "发送";
            this.btn_sen.UseVisualStyleBackColor = true;
            this.btn_sen.Click += new System.EventHandler(this.btn_sen_Click);
            // 
            // Form1
            // 
            this.AutoScaleDimensions = new System.Drawing.SizeF(6F, 12F);
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.ClientSize = new System.Drawing.Size(512, 463);
            this.Controls.Add(this.btn_sen);
            this.Controls.Add(this.txt_sen);
            this.Controls.Add(this.txt_msg);
            this.Controls.Add(this.tv_list);
            this.Name = "Form1";
            this.Text = "Form1";
            this.Load += new System.EventHandler(this.Form1_Load);
            this.ResumeLayout(false);
            this.PerformLayout();

        }

        #endregion

        private System.Windows.Forms.TreeView tv_list;
        private System.Windows.Forms.TextBox txt_msg;
        private System.Windows.Forms.TextBox txt_sen;
        private System.Windows.Forms.Button btn_sen;
    }
}

