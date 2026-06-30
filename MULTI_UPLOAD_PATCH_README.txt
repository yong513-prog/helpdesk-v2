Helpdesk Multi Upload Patch

覆盖文件到 C:\laragon\www\helpdesk 后，按 Ctrl + F5。

本补丁包含：
1. Create Ticket 支持一次选择多个附件。
2. Reply Ticket 支持一次选择多个附件。
3. 支持混合上传：图片、视频、语音、PDF、Word、Excel。
4. 文件选择后会显示已选择数量、总大小和文件名列表。
5. 后端继续使用 hd_ta_upload_many() 保存多附件。
6. 单个附件上限按 helper 设定显示为 50MB。
